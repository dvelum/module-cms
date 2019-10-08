<?php
/**
 *  DVelum project https://github.com/dvelum/dvelum , https://github.com/k-samuel/dvelum , http://dvelum.net
 *  Copyright (C) 2011-2019  Kirill Yegorov
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
 *
 */
declare(strict_types=1);

namespace Dvelum\App\Model;

use Dvelum\Orm;
use Dvelum\Orm\Model;
use Dvelum\Tree\Tree;

class Mediacategory extends Model
{
    /**
     * Get categories tree
     * @return array
     */
    public function getCategoriesTree() : array
    {
        $categoryModel = Model::factory('Mediacategory');
        $data = $categoryModel->query()->fetchAll();
        $tree = new Tree();

        if (!empty($data)) {
            foreach ($data as $k => $v) {
                if (is_null($v['parent_id']))
                    $v['parent_id'] = 0;

                $tree->addItem($v['id'], $v['parent_id'], $v);
            }
        }

        return $this->fillChildren($tree, 0);
    }

    /**
     * Fill children data array for tree panel
     * @param Tree $tree
     * @param mixed $root
     * @return array
     */
    protected function fillChildren(Tree $tree, $root = 0) : array
    {
        $result = [];
        $childs = $tree->getChildren($root);

        if (empty($childs))
            return [];

        foreach ($childs as $k => $v) {
            $row = $v['data'];
            $obj = new \stdClass();

            $obj->id = $row['id'];
            $obj->text = $row['title'];
            $obj->expanded = !intval($root);
            $obj->leaf = false;
            $obj->allowDrag = true;

            $cld = array();
            if ($tree->hasChildren($row['id']))
                $cld = $this->fillChildren($tree, $row['id']);

            $obj->children = $cld;
            $result[] = $obj;
        }
        return $result;
    }

    /**
     * Update pages order_no
     * @param array $sortedIds
     * @throws \Exception
     */
    public function updateSortOrder(array $sortedIds)
    {
        $i = 0;
        foreach ($sortedIds as $v) {
            $obj = Orm\Record::factory($this->getObjectName(), intval($v));
            $obj->set('order_no', $i);
            $obj->save();
            $i++;
        }
    }
}