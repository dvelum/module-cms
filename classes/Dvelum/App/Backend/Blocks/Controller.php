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

namespace Dvelum\App\Backend\Blocks;

use Dvelum\App\Backend;
use Dvelum\App\Model\Medialib;
use Dvelum\Config;
use Dvelum\File;
use Dvelum\Utils;
use Dvelum\Orm\Model;

class Controller extends Backend\Ui\Controller
{
    public function indexAction()
    {
        $this->resource->addJs('/resources/dvelum-module-cms/js/Blocks.js' , 10 , 1);
        $this->resource->addJs('/resources/dvelum-module-cms/js/crud/blocks.js' , 10 , 1);
        /**
         * @var Medialib $mediaModel
         */
        $mediaModel = Model::factory('Medialib');
        $mediaModel->includeScripts();

        parent::indexAction();
    }

    public function getModule(): string
    {
        return 'Blocks';
    }

    public function getObjectName(): string
    {
        return 'Blocks';
    }

    /**
     * List defined Blocks
     */
    public function classListAction()
    {
        $blocks = Config::storage()->get('blocks.php')->__toArray();
        $data = [];
        foreach ($blocks as $id => $class){
            $data[] = ['id'=>$class, 'title'=>$class];
        }
        $this->response->success($data);
    }

    /**
     * Get list of accepted menu
     */
    public function menulistAction()
    {
        $menuModel = Model::factory('menu');
        $fields = ['id', 'title'];
        $list = $menuModel->query()->fields($fields)->fetchAll();

        if(!empty($list))
            $list = array_values($list);

        $this->response->success($list);
    }

    /**
     * Get desktop module info
     */
    public function desktopModuleInfo()
    {
        $moduleName = $this->getModule();
        $projectData = [];
        $projectData['includes']['js'][] =  '/resources/dvelum-module-cms/js/Blocks.js';
        /*
         * Module bootstrap
         */
        if(file_exists($this->appConfig->get('jsPath').'app/system/desktop/' . strtolower($moduleName) . '.js'))
            $projectData['includes']['js'][] = '/js/app/system/desktop/' . strtolower($moduleName) .'.js';

        return $projectData;
    }
}