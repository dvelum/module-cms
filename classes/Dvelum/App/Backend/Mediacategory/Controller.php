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

namespace Dvelum\App\Backend\Mediacategory;

/**
 * Mediacategory module controller
 * Backoffice UI
 */

use Dvelum\App\Model\Mediacategory;
use Dvelum\Orm;
use Dvelum\Orm\Model;
use Dvelum\Config;
use Dvelum\App\Backend;
use \Exception;

class Controller extends Backend\Ui\Controller
{
    public function getModule(): string
    {
        return 'Medialib';
    }

    public function getObjectName(): string
    {
        return 'Mediacategory';
    }

    /**
     * Get Categories tree
     */
    public function treeListAction()
    {
        /**
         * @var Mediacategory $model
         */
        $model = Model::factory('Mediacategory');
        $this->response->json($model->getCategoriesTree());
    }

    /**
     * Sort media categories
     */
    public function sortCatalogAction()
    {
        $this->checkCanEdit();

        $id = $this->request->post('id','integer',false);
        $newParent = $this->request->post('newparent','integer',false);
        $order = $this->request->post('order', 'array' , []);

        if(!$id || !strlen($newParent) || empty($order)){
            $this->response->error((string) $this->lang->get('WRONG_REQUEST'));
            return;
        }

        /**
         * @var Mediacategory $model
         */
        $model = Model::factory('Mediacategory');

        try{
            $pObject = Orm\Record::factory('mediacategory' , $id);
            $pObject->set('parent_id', $newParent);
            $pObject->save();
            $model->updateSortOrder($order);
            $this->response->success();
        } catch (Exception $e){
           $this->response->error($this->lang->get('CANT_EXEC') . ' ' . $e->getMessage());
        }
    }

    /**
     * Delete object
     * Sends JSON reply in the result and
     * closes the application
     */
    public function deleteAction()
    {
        $this->checkCanDelete();
        $id = $this->request->post('id' , 'integer' , false);

        if(!$id){
            $this->response->error($this->lang->get('WRONG_REQUEST'));
            return;
        }

        try{
            $object = Orm\Record::factory($this->objectName , $id);
        }catch(Exception $e){
           $this->response->error($this->lang->get('WRONG_REQUEST'));
           return;
        }

        $childCount = Model::factory('Mediacategory')->query()->filters(['parent_id'=>$id])->getCount();

        if($childCount){
            $this->response->error($this->lang->get('REMOVE_CHILDREN'));
            return;
        }

        $ormConfig = Config::storage()->get('orm.php');

        if($ormConfig->get('vc_clear_on_delete')){
            Model::factory('Vc')->removeItemVc($this->objectName , $id);
        }

        /**
         * @var Model_Medialib $medialib
         */
        $medialib = Model::factory('Medialib');
        $medialib->categoryRemoved($id);

        if(!$object->delete()){
            $this->response->error($this->lang->get('CANT_EXEC'));
            return;
        }

       $this->response->success();
    }

    /**
     * Change Medialibrary items category
     */
    public function placeItemsAction()
    {
        $this->checkCanEdit();
        $items = $this->request->post('items', 'string', false);
        $category = $this->request->post('catalog', 'integer', false);

        if($items === false|| $category === false)
           $this->response->error($this->lang->get('WRONG_REQUEST'));

        $items = json_decode($items , true);
        
        if(!is_array($items) || empty($items))
           $this->response->error($this->lang->get('WRONG_REQUEST'));

        $medialibModel = Model::factory('Medialib');

        if($medialibModel->updateItemsCategory($items , $category))
           $this->response->success();
        else
           $this->response->error($this->lang->get('CANT_EXEC'));
    }
}