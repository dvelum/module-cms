<?php
/**
 *  DVelum project https://github.com/dvelum/dvelum
 *  Copyright (C) 2011-2017  Kirill Yegorov
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace Dvelum\App\Backend\Medialib;

use Dvelum\App\Backend;
use Dvelum\App\Model\Medialib;
use Dvelum\App\Upload\Uploader;
use Dvelum\Orm\Model;
use Dvelum\App\Controller\EventManager;
use Dvelum\App\Controller\Event;
use Dvelum\Response;
use Dvelum\Utils;

class Controller extends Backend\Ui\Controller
{
    public function getModule(): string
    {
        return 'Medialib';
    }

    public function getObjectName(): string
    {
        return 'Medialib';
    }

    public function initListeners()
    {
        $apiRequest = $this->apiRequest;
        $apiRequest->setObjectName($this->getObjectName());

        $this->eventManager->on(EventManager::BEFORE_LIST, function (Event $event) use ($apiRequest) {
            $category = $apiRequest->getFilter('category');
            if (empty($category)) {
                $apiRequest->addFilter('category', null);
            }
        });
        $this->eventManager->on(EventManager::AFTER_LIST, [$this, 'prepareList']);
    }

    public function prepareList(Event $event)
    {
        /**
         * @var Medialib $model
         */
        $model = Model::factory('Medialib');
        $data = &$event->getData()->data;

        $wwwRoot = $this->appConfig->get('wwwRoot');

        if (!empty($data)) {
            foreach ($data as &$v) {
                if ($v['type'] == 'image') {
                    $v['srcpath'] = $model->addWebRoot(str_replace($v['ext'], '', $v['path']));
                    $v['thumbnail'] = $model->getImgPath($v['path'], $v['ext'], 'thumbnail', true);
                    $v['icon'] = $model->getImgPath($v['path'], $v['ext'], 'icon', true);
                } else {
                    $v['icon'] = $wwwRoot . 'i/unknown.png';
                    $v['thumbnail'] = $wwwRoot . 'i/unknown.png';
                    $v['srcpath'] = '';
                }
                $v['path'] = $model->addWebRoot($v['path']);
            }
            unset($v);
        }
    }

    public function indexAction()
    {
        parent::indexAction();

        /**
         * @var Medialib $mediaModel
         */
        $mediaModel = Model::factory('Medialib');
        $mediaModel->includeScripts($this->resource);

        $this->resource->addJs('/resources/dvelum-module-cms/js/crud/medialib.js');
    }

    /**
     * Upload images to media library
     */
    public function uploadAction()
    {
        $uploadCategory = $this->request->getPart(3);

        if (!$uploadCategory) {
            $uploadCategory = null;
        }

        if (!$this->checkCanEdit()) {
            return;
        }

        $docRoot = $this->appConfig->get('wwwPath');
        /**
         * @var Medialib $mediaModel
         */
        $mediaModel = Model::factory('Medialib');
        $mediaCfg = $mediaModel->getConfig();

        $path = $this->appConfig->get('uploads') . date('Y') . '/' . date('m') . '/' . date('d') . '/';

        if (!is_dir($path) && !@mkdir($path, 0775, true)) {
            $this->response->error($this->lang->get('CANT_WRITE_FS'));
        }

        $files = $this->request->files();

        $uploader = new Uploader($mediaCfg->__toArray());

        if (empty($files)) {
            $this->response->error($this->lang->get('NOT_UPLOADED'));
            return;
        }

        $uploaded = $uploader->start($files, $path);

        if (empty($uploaded)) {
            $this->response->error($this->lang->get('NOT_UPLOADED'));
            return;
        }

        $data = [];

        foreach ($uploaded as &$v) {
            $path = str_replace($docRoot, '/', $v['path']);

            $id = $mediaModel->addItem($v['title'], $path, $v['size'], $v['type'], $v['ext'], $uploadCategory);

            $item = Model::factory('Medialib')->getItem($id);

            if ($item['type'] == 'image') {
                $item['srcpath'] = $mediaModel->addWebRoot(str_replace($item['ext'], '', $item['path']));
            } else {
                $item['srcPath'] = '';
            }

            $item['thumbnail'] = $mediaModel->getImgPath($item['path'], $item['ext'], 'thumbnail', true);
            $item['icon'] = $mediaModel->getImgPath($item['path'], $item['ext'], 'icon', true);
            $item['path'] = $mediaModel->addWebRoot($item['path']);

            $data[] = $item;
        }
        $this->response->setFormat(Response::FORMAT_JSON);
        $this->response->success($data);
    }

    /**
     * Crop image
     */
    public function cropAction()
    {
        if (!$this->checkCanEdit()) {
            return;
        }

        $id = $this->request->post('id', 'integer', false);
        $x = $this->request->post('x', 'integer', false);
        $y = $this->request->post('y', 'integer', false);
        $w = $this->request->post('w', 'integer', false);
        $h = $this->request->post('h', 'integer', false);
        $type = $this->request->post('type', 'string', false);

        if (!$id || !$w || !$h || !$type) {
            $this->response->error($this->lang->get('WRONG_REQUEST'));
        }

        /**
         * @var Medialib $mediaModel
         */
        $mediaModel = Model::factory('Medialib');
        $item = $mediaModel->getItem($id);

        if (!$item) {
            $this->response->error($this->lang->get('WRONG_REQUEST'));
        }

        if ($mediaModel->cropAndResize($item, $x, $y, $w, $h, $type)) {
            $mediaModel->updateModifyDate($id);
            $mediaModel->markCroped($id);
            $this->response->success();
        } else {
            $this->response->error($this->lang->get('CANT_EXEC'));
        }
    }

    /**
     * Remove image
     */
    public function removeAction()
    {
        if (!$this->checkCanDelete()) {
            return;
        }

        $id = $this->request->post('id', 'integer', false);

        if (!$id) {
            $this->response->error($this->lang->get('WRONG_REQUEST'));
        }

        $media = Model::factory('Medialib');
        if ($media->remove($id)) {
            $this->response->success();
        } else {
            $this->response->error($this->lang->get('WRONG_REQUEST'));
        }
    }

    /**
     * Update image info
     */
    public function updateAction()
    {
        if (!$this->checkCanEdit()) {
            return;
        }

        $id = $this->request->post('id', 'integer', false);

        if (!$id) {
            $this->response->error($this->lang->get('WRONG_REQUEST'));
        }

        $fields = ['title', 'alttext', 'caption', 'description'];
        $data = [];

        foreach ($fields as $v) {
            if ($v == 'caption') {
                $data[$v] = $this->request->post($v, 'raw', '');
            } elseif ($v == 'category') {
                $data[$v] = $this->request->post($v, 'integer', null);
            } else {
                $data[$v] = $this->request->post($v, 'string', '');
            }
        }

        if (!strlen($data['title'])) {
            $this->response->error($this->lang->get('FILL_FORM'), array('title' => $this->lang->get('CANT_BE_EMPTY')));
        }

        /**
         * @var Medialib $media
         */
        $media = Model::factory('Medialib');

        if ($media->update($id, $data)) {
            $this->response->success();
        } else {
            $this->response->error($this->lang->get('CANT_EXEC'));
        }
    }

    /**
     * Get item data
     */
    public function getItemAction()
    {
        $id = $this->request->post('id', 'integer', false);

        if (!$id) {
            $this->response->error($this->lang->get('WRONG_REQUEST'));
        }

        /**
         * @var Medialib $model
         */
        $model = Model::factory('Medialib');

        $item = $model->getItem($id);

        if ($item['type'] == 'image') {
            $item['srcpath'] = $model->addWebRoot(str_replace($item['ext'], '', $item['path']));
        } else {
            $item['srcPath'] = '';
        }

        $item['thumbnail'] = $model->getImgPath($item['path'], $item['ext'], 'thumbnail', true);
        $item['icon'] = $model->getImgPath($item['path'], $item['ext'], 'icon', true);
        $item['path'] = $model->addWebRoot($item['path']);

        $this->response->success($item);
    }

    /**
     * Get item info for media item field
     */
    public function infoAction()
    {
        $id = $this->request->post('id', 'integer', false);
        /**
         * @var Medialib $model
         */
        $model = Model::factory('Medialib');

        if (!$id) {
            $this->response->success(['exists' => false]);
            return;
        }

        $item = Model::factory('Medialib')->getItem($id);

        if (empty($item)) {
            $this->response->success(array('exists' => false));
        }

        if ($item['type'] == 'image') {
            $stamp = 1;

            if (!empty($item['modified'])) {
                $stamp = date('ymdhis', strtotime($item['modified']));
            }

            $icon = $model->getImgPath($item['path'], $item['ext'], 'thumbnail', true) . '?m=' . $stamp;

        } else {
            $icon = $this->appConfig->get('wwwroot') . 'i/unknown.png';
        }

        $this->response->success([
            'exists' => true,
            'type' => $item['type'],
            'icon' => $icon,
            'title' => $item['title'],
            'size' => $item['size'] . ' Mb'
        ]);
    }

    /**
     * Get access permissions for current user
     */
    public function rightsAction()
    {
        $results = array(
            'canEdit' => $this->moduleAcl->canEdit($this->module),
            'canDelete' => $this->moduleAcl->canDelete($this->module),
        );
        $this->response->success($results);
    }

    /**
     * Dev. method. Compile JavaScript sources
     */
    public function compileAction()
    {
        $sources = array(
            'resources/dvelum-module-cms/js/medialib/Category.js',
            'resources/dvelum-module-cms/js/medialib/Panel.js',
            'resources/dvelum-module-cms/js/medialib/Models.js',
            'resources/dvelum-module-cms/js/medialib/FileUploadWindow.js',
            'resources/dvelum-module-cms/js/medialib/ImageSizeWindow.js',
            'resources/dvelum-module-cms/js/medialib/SelectMediaItemWindow.js',
            'resources/dvelum-module-cms/js/medialib/ItemField.js',
            'resources/dvelum-module-cms/js/medialib/EditWindow.js',
            'resources/dvelum-module-cms/js/medialib/CropWindow.js'
        );

        if (!$this->appConfig->get('development')) {
            $this->response->put('Use development mode');
            $this->response->send();
        }

        $s = '';
        $totalSize = 0;

        $wwwPath = $this->appConfig->get('wwwPath');

        foreach ($sources as $filePath) {
            $s .= file_get_contents($wwwPath . '/' . $filePath) . "\n";
            $totalSize += filesize($wwwPath . '/' . $filePath);
        }

        $time = microtime(true);
        file_put_contents($wwwPath . '/js/app/system/Medialib.js', \Dvelum\App\Code\Minify\Minify::factory()->minifyJs($s));
        echo '
      			Compilation time: ' . number_format(microtime(true) - $time, 5) . ' sec<br>
      			Files compiled: ' . sizeof($sources) . ' <br>
      			Total size: ' . Utils::formatFileSize($totalSize) . '<br>
      			Compiled File size: ' . Utils::formatFileSize(filesize($wwwPath . '/js/app/system/Medialib.js')) . ' <br>
      		';
        exit;
    }
}