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
use Dvelum\Config;
use Dvelum\Tree\Tree;

/**
 * Pages Model
 * @author Kirill Egorov 2011
 */
class Page extends Model
{
    /**
     * Get Pages tree
     * @param array $fields
     * @return array
     */
    public function getTreeList(array $fields): array
    {
        /*
         * Add the required fields to the list
         */
        $fields = array_unique(array_merge(['id', 'parent_id', 'order_no', 'code', 'menu_title', 'is_fixed'], $fields));

        $data = $this->query()->params([
                'sort' => 'order_no',
                'dir' => 'ASC'
            ])->fields($fields)->fetchAll();

        if (empty($data)) {
            return [];
        }

        $ids = \Dvelum\Utils::fetchCol('id', $data);
        /**
         * @var Model_Vc $vc
         */
        $vc = Model::factory('Vc');
        $maxRevisions = $vc->getLastVersion('page', $ids);

        foreach ($data as &$v) {
            if (isset($maxRevisions[$v['id']])) {
                $v['last_version'] = $maxRevisions[$v['id']];
            } else {
                $v['last_version'] = 0;
            }
        }
        unset($v);

        if (empty($data)) {
            return [];
        }

        $tree = new Tree();

        foreach ($data as $value) {
            if (!$value['parent_id']) {
                $value['parent_id'] = 0;
            }

            $tree->addItem($value['id'], $value['parent_id'], $value, $value['order_no']);
        }

        return $this->fillChildren($tree, 0);
    }

    /**
     * Fill childs data array for tree panel
     * @param Tree $tree
     * @param mixed $root
     * @return array
     */
    protected function fillChildren(Tree $tree, $root = 0)
    {
        $result = array();
        $children = $tree->getChildren($root);

        if (empty($children)) {
            return [];
        }

        $appConfig = Config::storage()->get('main.php');

        foreach ($children as $v) {
            $row = $v['data'];
            $obj = new \stdClass();

            $obj->id = $row['id'];
            $obj->text = $row['menu_title'] . ' <i>(' . $row['code'] . $appConfig['urlExtension'] . ')</i>';
            $obj->expanded = true;
            $obj->leaf = false;

            if ($row['published']) {
                $obj->qtip = $row['menu_title'] . ' <i>(' . $row['code'] . $appConfig['urlExtension'] . ')</i> published';
                $obj->iconCls = 'pagePublic';
            } else {
                $obj->qtip = $row['menu_title'] . ' <i>(' . $row['code'] . $appConfig['urlExtension'] . ')</i> not published';
                $obj->iconCls = 'pageHidden';
            }

            if ($row['is_fixed']) {
                $obj->allowDrag = false;
            }

            $cld = [];
            if ($tree->hasChildren($row['id'])) {
                $cld = $this->fillChildren($tree, $row['id']);
            }

            $obj->children = $cld;
            $result[] = $obj;
        }
        return $result;
    }

    /**
     * Update pages order_no
     * @param array $sortedIds
     */
    public function updateSortOrder(array $sortedIds)
    {
        $i = 0;
        foreach ($sortedIds as $v) {
            $obj = Orm\Record::factory($this->name, intval($v));
            $obj->set('order_no', $i);
            $obj->save();

            $i++;
        }
    }

    /**
     * Check if page code exists
     * @param string $code
     * @return bool
     */
    public function codeExists(string $code) : bool
    {
        return (bool) $this->dbSlave->fetchOne(
            $this->dbSlave->select()
                ->from($this->table(), ['count' => 'COUNT(*)'])
                ->where('code = ?', $code)
            );
    }

    /**
     * Get topic item ID by its code
     * @param string $code
     * @return integer;
     */
    public function getIdByCode($code): int
    {
        $recId = $this->dbSlave->fetchOne($this->dbSlave->select()->from($this->table(), array('id'))->where('code =?',
                $code));
        return intval($recId);
    }


    /**
     * Get hash for pagecode
     * @param string $code
     * @return string
     */
    static public function getCodeHash($code): string
    {
        return md5('page_' . $code);
    }

    /**
     * Reset childs elements set parent 0
     * @param page $id
     */
    public function resetChilds($id)
    {
        $obj = Orm\Record::factory($this->name, intval($id));
        $obj->set('parent_id', 0);
        $obj->save();
    }

    /**
     * Find page code by attached module
     * @param string $name
     * @return mixed (string / false)
     */
    public function getCodeByModule($name)
    {
        $data = $this->dbSlave->fetchOne(
            $this->dbSlave->select()
                ->from($this->table(), ['code'])
                ->where('`func_code` = ?', $name)
                ->order('published DESC')
        );

        if ($data) {
            return $data;
        } else {
            return false;
        }
    }

    /**
     * Get page codes
     * @return array
     */
    public function getCachedCodes()
    {
        $codes = false;
        $cacheKey = '';

        if ($this->cache) {
            $cacheKey = $this->getCacheKey(array('codes'));
            $codes = $this->cache->load($cacheKey);
        }

        if ($codes) {
            return $codes;
        }

        $codes = $this->query()->fields(['id', 'code'])->fetchAll();

        if (!empty($codes)) {
            $codes = \Dvelum\Utils::collectData('id', 'code', $codes);
        } else {
            $codes = [];
        }

        if ($this->cache) {
            $this->cache->save($codes, $cacheKey);
        }

        return $codes;
    }

    /**
     * Get id's of page with default blocks map
     * @return array
     */
    public function getPagesWithDefaultMap(): array
    {
        $ids = $this->query()->fields(['id'])->filters(['default_blocks' => 1])->fetchAll();

        if (!empty($ids)) {
            return \Dvelum\Utils::fetchCol('id', $ids);
        } else {
            return [];
        }
    }

    /**
     * Get pages Tree
     * @return Tree
     */
    public function getTree()
    {
        static $tree = false;
        $cacheKey = '';

        if ($tree instanceof Tree) {
            return $tree;
        }

        if ($this->cache) {
            $cacheKey = $this->getCacheKey(['pages_tree_data']);
            $tree = $this->cache->load($cacheKey);
        }

        if ($tree instanceof Tree) {
            return $tree;
        }

        $fields = ['id', 'parent_id', 'order_no', 'code', 'menu_title', 'is_fixed', 'published', 'in_site_map'];
        $data = $this->query()
            ->params([
                'sort' => 'order_no',
                'dir' => 'ASC'
            ])
            ->fields($fields)
            ->fetchAll();

        $tree = new Tree();

        if (!empty($data)) {
            foreach ($data as $v) {
                $tree->addItem($v['id'], $v['parent_id'], $v);
            }
        }

        if ($this->cache) {
            $this->cache->save($tree, $cacheKey);
        }

        return $tree;
    }
}