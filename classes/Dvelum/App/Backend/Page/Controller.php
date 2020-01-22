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

namespace Dvelum\App\Backend\Page;

use Dvelum\App\Backend;
use Dvelum\App\Model\Historylog;
use Dvelum\App\Model\Medialib;
use Dvelum\App\Model\Vc;
use Dvelum\App\Module\Manager\Frontend;
use Dvelum\Orm;
use Dvelum\Orm\Model;
use Dvelum\Config;
use Dvelum\App\Controller\Event;
use Dvelum\App\Controller\EventManager;
use Dvelum\Utils;
use Dvelum\View;
use Dvelum\File;
use Dvelum\Filter;
use Dvelum\Config\ConfigInterface;
use Dvelum\Orm\RecordInterface;

class Controller extends Backend\Ui\Controller
{
    protected $listFields = [
        'id',
        'parent_id',
        'menu_title',
        'published',
        'code',
        'date_updated',
        'date_created',
        'published_version'
    ];

    protected $listLinks = [
        'user' => 'author_id',
        'updater' => 'editor_id'
    ];

    public function getModule(): string
    {
        return 'Page';
    }

    public function getObjectName(): string
    {
        return 'Page';
    }


    /**
     * Get controller configuration
     * @return ConfigInterface
     */
    protected function getConfig(): ConfigInterface
    {
        return Config::storage()->get('backend/controller/page.php');
    }

    public function initListeners()
    {
        $apiRequest = $this->apiRequest;
        $apiRequest->setObjectName($this->getObjectName());

        $this->eventManager->on(EventManager::BEFORE_LIST, function (Event $event) use ($apiRequest) {
            if ($this->user->getModuleAcl()->onlyOwnRecords($this->getModule())) {
                $apiRequest->addFilter('author_id', $this->user->getId());
            }
            $apiRequest->addSort('order_no', 'ASC');
            $apiRequest->addLimit(null, null);
        });

        $this->eventManager->on(EventManager::AFTER_LIST, [$this, 'prepareList']);
        $this->eventManager->on(EventManager::AFTER_LOAD, [$this, 'prepareData']);
    }

    public function indexAction()
    {
        parent::indexAction();

        $this->resource->addJs('/resources/dvelum-module-cms/js/BlocksPanel.js', 3);
        $this->resource->addJs('/resources/dvelum-module-cms/js/Page.js', 3);
        $this->resource->addJs('/resources/dvelum-module-cms/js/crud/page.js', 4);

        /**
         * @var Medialib $mediaModel
         */
        $mediaModel = Model::factory('Medialib');
        $mediaModel->includeScripts($this->resource);

        $moduleManager = new Frontend();

        $funcList = [];

        foreach ($moduleManager->getList() as $config) {
            $funcList[] = array('id' => $config['code'], 'title' => $config['title']);
        }

        $this->resource->addInlineJs('
        	var aFuncCodes = ' . json_encode($funcList) . ';
        ');
    }

    /**
     * Get list of pages as a data tree config
     */
    public function treeListAction()
    {
        /**
         * @var \Model_Page $pagesModel
         */
        $pagesModel = Model::factory('Page');
        $this->response->json($pagesModel->getTreeList(['id', 'parent_id', 'published', 'code']));
    }

    /**
     * Get pages list as array
     * @param Event $event
     * @return void
     */
    public function prepareList(Event $event): void
    {
        $data = &$event->getData()->data;

        if (empty($data)) {
            return;
        }

        $ids = Utils::fetchCol('id', $data);
        /**
         * @var \Model_Vc $vcModel
         */
        $vcModel = Model::factory('Vc');

        $maxRevisions = $vcModel->getLastVersion('page', $ids);

        foreach ($data as $k => &$v) {
            if (isset($maxRevisions[$v['id']])) {
                $v['last_version'] = $maxRevisions[$v['id']];
            } else {
                $v['last_version'] = 0;
            }
        }
        unset($v);
    }

    /**
     * Get blocks
     */
    public function blockListAction()
    {
        $blocksModel = Model::factory('Blocks');
        $data = $blocksModel->query()->fields(['id', 'title', 'is_system', 'published'])->fetchAll();
        foreach ($data as $k => &$v) {
            $v['deleted'] = false;
        }
        $this->response->success($data);
    }


    /**
     * Check if page code is unique
     */
    public function checkCodeAction()
    {
        $id = $this->request->post('id', 'int', 0);
        $code = $this->request->post('code', 'string', false);

        $code = Filter::filterValue('pagecode', $code);

        $model = Model::factory('Page');

        if ($model->checkUnique($id, 'code', $code)) {
            $this->response->success(['code' => $code]);
        } else {
            $this->response->error($this->lang->get('SB_UNIQUE'));
        }
    }

    /**
     * Change page sorting order
     */
    public function sortPagesAction()
    {
        if (!$this->checkCanEdit()) {
            return;
        }

        $id = $this->request->post('id', 'integer', false);
        $newParent = $this->request->post('newparent', 'integer', false);
        $order = $this->request->post('order', 'array', array());

        if (!$id || !strlen((string)$newParent) || empty($order)) {
            $this->response->error($this->lang->get('WRONG_REQUEST'));
        }

        try {
            $pObject = Orm\Record::factory('page', $id);
            $pObject->set('parent_id', $newParent);
            $pObject->save();
            /**
             * @var \Model_Page $pageModel
             */
            $pageModel = Model::factory('Page');
            $pageModel->updateSortOrder($order);
            $this->response->success();
        } catch (\Exception $e) {
            $this->response->error($this->lang->get('CANT_EXEC'));
        }
    }

    /**
     * Find staging URL
     * @param RecordInterface $obj
     * @return string
     */
    public function getStagingUrl(RecordInterface $obj): string
    {
        return $this->request->url([$obj->get('code')]);
    }

    /**
     * @param Event $event
     * @return void
     */
    public function prepareData(Event $event): void
    {
        $data = &$event->getData()->data;

        $templateStorage = View::storage();
        $templatePath = $templateStorage->get($this->appConfig->get('themes') . $data['theme'] . '/layout_cfg.php');

        if (empty($templatePath)) {
            $templatePath = $templateStorage->get($this->appConfig->get('themes') . 'default/layout_cfg.php');
        }

        if (empty($templatePath)) {
            $this->response->error($this->lang->get('WRONG_REQUEST'));
            return;
        }

        try {
            $config = new Config\File\AsArray($templatePath);
        } catch (\Exception $e) {
            $this->response->error($this->lang->get('WRONG_REQUEST'));
            return;
        }

        $conf = $config->__toArray();

        $items = [];

        if (isset($conf['items']) && !empty($conf['items'])) {
            foreach ($conf['items'] as $k => $v) {
                $v['code'] = $k;
                $items[] = $v;
            }
            $conf['items'] = $items;
        }

        /*
         * Collect blocks info
         */
        if (!empty($data['blocks'])) {
            $blocksCfg = unserialize($data['blocks']);
            if (!empty($blocksCfg)) {
                foreach ($blocksCfg as $key => $value) {
                    $blocksCfg[$key] = array_values($this->collectBlockLinksData($value));
                }
            }
            $data['blocks'] = ['config' => $conf, 'blocks' => $blocksCfg];
        } else {
            $data['blocks'] = ['config' => $conf, 'blocks' => []];
        }
    }

    /**
     *  Get blocks map
     */
    public function blockConfigAction()
    {
        $theme = $this->request->post('theme', 'string', 'default');

        try {
            $templateStorage = View::storage();
            $templatePath = $templateStorage->get($this->appConfig->get('themes') . $theme . '/layout_cfg.php');

            if (empty($templatePath)) {
                throw new \Exception('Undefined theme');
            }

            $config = new Config\File\AsArray($templatePath);
        } catch (\Exception $e) {
            $this->response->error($this->lang->get('WRONG_REQUEST'));
            return;
        }

        $conf = $config->__toArray();
        $items = [];

        if (isset($conf['items']) && !empty($conf['items'])) {
            foreach ($conf['items'] as $k => $v) {
                $v['code'] = $k;
                $items[] = $v;
            }
            $conf['items'] = $items;
        }

        $this->response->success($conf);
    }

    /**
     * Prepare data for linked field component
     * @param array $data
     * @return array
     */
    protected function collectBlockLinksData(array $data): array
    {
        $ids = Utils::fetchCol('id', $data);
        $data = Utils::rekey('id', $data);

        $obj = Orm\Record::factory('Blocks');
        $model = Model::factory('Blocks');

        $fields = ['id', 'title' => $obj->getConfig()->getLinkTitle(), 'is_system'];
        $usedRC = $obj->getConfig()->isRevControl();

        if ($usedRC) {
            $fields[] = 'published';
        }

        $oData = $model->getItems($ids, $fields);

        if (!empty($data)) {
            $oData = Utils::rekey('id', $oData);
        }

        /*
         * Find out deleted records
         */
        $deleted = array_diff($ids, array_keys($oData));

        $result = [];

        foreach ($ids as $id) {
            if (in_array($id, $deleted)) {
                $title = '';
                if (isset($data[$id]['title'])) {
                    $title = $data[$id]['title'];
                }
                $item = array('id' => $id, 'deleted' => 1, 'title' => $title, 'published' => 1, 'is_system' => 0);
                if ($usedRC) {
                    $item['published'] = 0;
                }
            } else {
                $item = array(
                    'id' => $id,
                    'deleted' => 0,
                    'title' => $oData[$id]['title'],
                    'published' => 1,
                    'is_system' => $oData[$id]['is_system']
                );
                if ($usedRC) {
                    $item['published'] = $oData[$id]['published'];
                }
            }
            $result[] = $item;
        }
        return $result;
    }

    /**
     * Publish page
     */
    public function publishAction()
    {
        $id = $this->request->post('id', 'integer', false);
        $vers = $this->request->post('vers', 'integer', false);

        if (!$id || !$vers) {
            $this->response->error($this->lang->get('WRONG_REQUEST'));
            return;
        }

        if (!$this->checkCanPublish()) {
            return;
        }

        try {
            /**
             * @var Orm\RecordInterface $object
             */
            $object = Orm\Record::factory($this->getObjectName(), $id);
        } catch (\Exception $e) {
            $this->response->error($this->lang->get('CANT_EXEC'));
            return;
        }

        if (!$this->checkOwner($object)) {
            $this->response->error($this->lang->get('CANT_ACCESS'));
            return;
        }

        /**
         * @var Vc $vc
         */
        $vc = Model::factory('Vc');

        $data = $vc->getData($this->getObjectName(), $id, $vers);

        /*
         * Do not publish some data
         * parent_id and order_no can be changed outside of version control
         */
        $publishException = ['id', 'order_no', 'parent_id'];
        foreach ($publishException as $field) {
            if (array_key_exists($field, $data)) {
                unset($data[$field]);
            }
        }

        if (empty($data)) {
            $this->response->error($this->lang->get('CANT_EXEC'));
            return;
        }

        $objectConfig = $object->getConfig();

        foreach ($data as $k => $v) {
            if ($object->fieldExists($k)) {
                if ($objectConfig->getField($k)->isMultiLink() && !empty($v)) {
                    $v = array_keys($v);
                }
                try {
                    $object->set($k, $v);
                } catch (\Exception $e) {
                    $this->response->error($this->lang->get('VERSION_INCOPATIBLE'));
                    return;
                }
            }
        }

        if (isset($data['blocks']) && strlen($data['blocks'])) {
            $blocks = unserialize($data['blocks']);
            if (empty($blocks)) {
                $blocks = [];
            }

            if (!Model::factory('Blocks')->setMapping($id, $blocks)) {
                $this->response->error($this->lang->get('CANT_EXEC') . ' code 2');
                return;
            }
        }

        $object->set('published_version', $vers);
        $object->set('published', true);

        if ($vers == 1 || empty($object->get('date_published'))) {
            $object->set('date_published', date('Y-m-d H:i:s'));
        }

        if (!$object->save(false)) {
            $this->response->error($this->lang->get('CANT_EXEC'));
            return;
        }

        if ($objectConfig->hasHistory()) {
            /**
             * @var Historylog $hl
             */
            $hl = Model::factory('Historylog');
            $hl->log(
                $this->user->getId(),
                $object->getId(),
                Historylog::Publish,
                Model::factory($object->getName())->table()
            );
        }
        $this->response->success();
    }

    /**
     * Delete object
     * Sends JSON reply in the result and
     * closes the application
     */
    public function deleteAction()
    {
        $id = $this->request->post('id', 'integer', false);

        if (!$id) {
            $this->response->error($this->lang->get('WRONG_REQUEST'));
            return;
        }

        if (!$this->checkCanDelete()) {
            return;
        }

        $childIds = Model::factory('Page')->query()->filters(['parent_id' => $id])->fields(['id'])->fetchAll();

        if (!empty($childIds)) {
            $this->response->error($this->lang->get('REMOVE_CHILDREN'));
            return;
        }

        parent::deleteAction();
    }

    /**
     * Get themes list
     */
    public function themesListAction()
    {
        $templateStorage = View::storage();
        $paths = $templateStorage->getPaths();

        $themes = [];
        $themesDir = $this->appConfig->get('themes');
        foreach ($paths as $path) {
            if (is_dir($path . $themesDir)) {
                $themesList = File::scanFiles($path . $themesDir, false, false, File::DIRS_ONLY);
                if (!empty($themesList)) {
                    foreach ($themesList as $themePath) {
                        $themeName = basename($themePath);
                        $themes[$themeName] = true;
                    }
                }
            }
        }

        if (!empty($themes)) {
            $themes = array_keys($themes);
        }

        $result = [];

        if (!empty($themes)) {
            foreach ($themes as $name) {
                $code = basename($name);
                if ($code[0] != '.') {
                    $result[] = array('id' => $code, 'title' => $code);
                }
            }
        }

        $this->response->success($result);
    }

    /**
     * Get list of default blocks
     */
    public function defaultBlocksAction()
    {
        $blocks = Model::factory('Blockmapping');
        $list = $blocks->query()
            ->filters(['page_id' => null])
            ->fields(['id' => 'block_id', 'place'])
            ->fetchAll();

        $templateStorage = View::storage();
        $filePath = $templateStorage->get($this->appConfig->get('themes') . 'default' . '/layout_cfg.php');

        if (empty($filePath)) {
            $this->response->error($this->lang->get('WRONG_REQUEST'));
            return;
        }

        try {
            $config = new Config\File\AsArray($filePath);
        } catch (\Exception $e) {
            $this->response->error($this->lang->get('WRONG_REQUEST'));
            return;
        }

        if (!empty($list)) {
            $list = Utils::groupByKey('place', $list);

            foreach ($list as $key => &$value) {
                $value = array_values($this->collectBlockLinksData($value));
            }
            unset($value);
        } else {
            $list = [];
        }

        $this->response->success([
            'config' => $config->__toArray(),
            'blocks' => $list
        ]);
    }

    /**
     * Save default blocks map
     */
    public function defaultBlocksSaveAction()
    {
        $this->checkCanEdit();

        $data = $this->request->post('blocks', 'raw', '');
        if (strlen($data)) {
            $data = json_decode($data, true);
        } else {
            $data = [];
        }

        /**
         * @var \Model_Blockmapping $blockMapping
         */
        $blockMapping = Model::factory('Blockmapping');
        $blockMapping->clearMap(0);

        if (!empty($data)) {
            foreach ($data as $place => $items) {
                $blockMapping->addBlocks(0, $place, Utils::fetchCol('id', $items));
            }
        }

        $blockManager = new \Dvelum\App\BlockManager();
        $blockManager->invalidateDefaultMap();
        $this->response->success();
    }

    /**
     * Get desktop module info
     */
    public function desktopModuleInfo()
    {
        // $modulesConfig = Config::storage()->get($this->appConfig->get('backend_modules'));
        // $moduleCfg = $modulesConfig->get($this->getModule());

        $projectData = [];
        $projectData['includes']['js'][] = '/resources/dvelum-module-cms/js/BlocksPanel.js';
        $projectData['includes']['js'][] = '/resources/dvelum-module-cms/js/Page.js';

        /*
         * Get module codes
         */
        $moduleManager = new Frontend();
        // $fModules = Config::storage()->get($this->appConfig->get('frontend_modules'));
        $funcList = [];
        foreach ($moduleManager->getList() as $config) {
            $funcList[] = ['id' => $config['code'], 'title' => $config['title']];
        }
        $projectData['includes']['js'][] = $this->resource->cacheJs('var aFuncCodes = ' . json_encode($funcList) . ';');

        /*
         * Module bootstrap
         */
        if (file_exists($this->appConfig->get('jsPath') . 'app/system/desktop/' . strtolower($this->getModule()) . '.js')) {
            $projectData['includes']['js'][] = '/js/app/system/desktop/' . strtolower($this->getModule()) . '.js';
        }

        return $projectData;
    }
}