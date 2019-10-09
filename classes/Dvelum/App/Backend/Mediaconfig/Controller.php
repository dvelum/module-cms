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

namespace Dvelum\App\Backend\Mediaconfig;

use Dvelum\App\Backend;
use Dvelum\BackgroundTask\Log\File;
use Dvelum\BackgroundTask\Manager;
use Dvelum\BackgroundTask\Storage\Orm;
use Dvelum\Orm\Model;
use Dvelum\Filter;
use Dvelum\Config;

/**
 * Medialibrary configuration module controller
 * Backoffice UI
 */
class Controller extends Backend\Ui\Controller
{

    public function getModule(): string
    {
        return 'Mediaconfig';
    }

    public function getObjectName(): string
    {
        return '';
    }

    public function indexAction()
    {
        $this->resource->addJs('/resources/dvelum-module-cms/js/Mediaconfig.js', 4);
        $this->resource->addJs('/resources/dvelum-module-cms/js/crud/mediaconfig.js', 5);

        $this->resource->addInlineJs('
            var canEdit = ' . ((boolean)$this->user->getModuleAcl()->canEdit($this->getModule())) . ';
            var canDelete = ' . ((boolean)$this->user->getModuleAcl()->canDelete($this->getModule())) . ';
        ');
    }

    /**
     * Get configuration list
     */
    public function listAction()
    {
        $media = Model::factory('Medialib');
        $config = $media->getConfig()->__toArray();

        $result = [];

        foreach ($config['image']['sizes'] as $code => $item) {
            $resize = 'crop';
            if (isset($config['image']['thumb_types'][$code])) {
                $resize = $config['image']['thumb_types'][$code];
            }

            $result[] = [
                'code' => $code,
                'resize' => $resize,
                'width' => $item[0],
                'height' => $item[1]
            ];
        }
        $this->response->success($result);
    }

    /**
     * Update configuration
     */
    public function updateAction()
    {
        if (!$this->checkCanEdit()) {
            return;
        }

        $data = $this->request->post('data', 'raw', false);

        if ($data === false) {
            $this->response->success();
            return;
        }

        $dataType = json_decode($data);
        if (!is_array($dataType)) {
            $data = array(json_decode($data, true));
        } else {
            $data = json_decode($data, true);
        }

        $media = Model::factory('Medialib');
        $configImage = $media->getConfig()->get('image');

        foreach ($data as $item) {
            $code = Filter::filterValue('pagecode', $item['code']);
            $configImage['sizes'][$code] = array(intval($item['width']), intval($item['height']));
            $configImage['thumb_types'][$code] = $item['resize'];
        }

        $config = $media->getConfig();
        $config->set('image', $configImage);

        if (!Config::storage()->save($config)) {
            $this->response->error($this->lang->get('CANT_WRITE_FS'));
            return;
        }

        $this->response->success();
    }

    /**
     * Remove configuration record
     */
    public function deleteAction()
    {
        if (!$this->checkCanDelete()) {
            return;
        }

        $data = $this->request->post('data', 'raw', false);

        if ($data === false) {
            $this->response->success();
            return;
        }


        $dataType = json_decode($data);
        if (!is_array($dataType)) {
            $data = array(json_decode($data, true));
        } else {
            $data = json_decode($data, true);
        }

        $media = Model::factory('Medialib');
        $configImage = $media->getConfig()->get('image');

        foreach ($data as $item) {
            $code = Filter::filterValue('pagecode', $item['code']);
            unset($configImage['sizes'][$code]);
            unset($configImage['thumb_types'][$code]);
        }

        $config = $media->getConfig();
        $config->set('image', $configImage);

        if (!$config->save()) {
            $this->response->error($this->lang->get('CANT_WRITE_FS'));
            return;
        }

        $this->response->success();
    }

    /**
     * Recrop
     */
    public function startCropAction()
    {
        if (!$this->checkCanEdit()) {
            return;
        }

        $notCroped = $this->request->post('notcroped', 'boolean', false);
        $sizes = $this->request->post('size', 'array', []);

        if (empty($sizes) || !is_array($sizes)) {
            $this->response->error($this->lang->get('MSG_SELECT_SIZE'));
            return;
        }

        $mediaConfig = Model::factory('Medialib')->getConfig()->__toArray();
        $acceptedSizes = array_keys($mediaConfig['image']['sizes']);
        $sizeToCrop = [];

        foreach ($sizes as $key => $item) {
            if (in_array($item, $acceptedSizes, true)) {
                $sizeToCrop[] = $item;
            }
        }

        if (empty($sizeToCrop)) {
            $this->response->error($this->lang->get('MSG_SELECT_SIZE'));
            return;
        }

        //Model::factory('bgtask')->getDbConnection()->getProfiler()->profilerFinish();

        $bgStorage = new Orm(Model::factory('bgtask'), Model::factory('Bgtask_Signal'));
        $logger = new File($this->appConfig->get('task_log_path') . 'recrop_' . date('d_m_Y__H_i_s'));
        $tm = Manager::factory();
        $tm->setStorage($bgStorage);
        $tm->setLogger($logger);
        $tm->launch(Manager::LAUNCHER_JSON, '\\Dvelum\\App\\Task\\Recrop', ['types' => $sizeToCrop, 'notCroped' => $notCroped]);
    }

    /**
     * Get desktop module info
     */
    public function desktopModuleInfo()
    {
        $projectData = [];
        $projectData['includes']['js'][] = '/js/app/system/Mediaconfig.js';
        /*
         * Module bootstrap
         */
        if (file_exists($this->appConfig->get('jsPath') . 'app/system/desktop/' . strtolower($this->getModule()) . '.js')) {
            $projectData['includes']['js'][] = '/js/app/system/desktop/' . strtolower($this->getModule()) . '.js';
        }

        return $projectData;
    }
}
