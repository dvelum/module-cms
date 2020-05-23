<?php
/**
 *  DVelum project https://github.com/dvelum/dvelum , https://github.com/k-samuel/dvelum , http://dvelum.net
 *  Copyright (C) 2011-2020  Kirill Yegorov
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

use Dvelum\Orm\Model;

class Menu extends Model
{
    public function resetCachedMenuLinks($menuId)
    {
        if ($this->cache) {
            $this->cache->remove($this->getCacheKey(['links', $menuId]));
        }
    }

    public function getCachedMenuLinks($menuId) : array
    {
        $menuRecord = $this->getCachedItem($menuId);
        $cacheKey = '';

        if (!$menuRecord) {
            return [];
        }

        $list = false;

        if ($this->cache) {
            $cacheKey = $this->getCacheKey(['links', $menuId]);
            $list = $this->cache->load($cacheKey);
        }

        if ($list !== false) {
            return $list;
        }

        $itemModel = Model::factory('Menu_Item');

        $list = $itemModel->query()
            ->params([
                'sort' => 'order',
                'dir' => 'ASC'
            ])
            ->filters([
                'menu_id' => $menuRecord['id']
            ])
            ->fields([
                'order',
                'page_id',
                'published',
                'title',
                'parent_id',
                'tree_id',
                'url',
                'resource_id',
                'link_type'
            ])
            ->fetchAll();


        if (empty($list)) {
            return [];
        }

        $list = $this->addUrls($list);

        if ($this->cache) {
            $this->cache->save($list, $cacheKey);
        }

        return $list;
    }

    protected function addUrls(array $menuItems) : array
    {
        $request = \Dvelum\Request::factory();
        $codes = Model::factory('Page')->getCachedCodes();
        /**
         * @var Medialib $mediaModel
         */
        $mediaModel = Model::factory('Medialib');
        $resourceIds = array();
        $resourcesData = array();

        foreach ($menuItems as $k => &$v) {
            if (isset($codes[$v['page_id']])) {
                $v['page_code'] = $codes[$v['page_id']];
            } else {
                $v['page_code'] = '';
            }

            if ($v['link_type'] === 'resource') {
                $resourceIds[] = $v['resource_id'];
            }
        }
        unset($v);

        if (!empty($resourceIds)) {
            $resourceIds = array_unique($resourceIds);
            $data = $mediaModel->getItems($resourceIds, array('id', 'path'));

            if (!empty($data)) {
                $resourcesData = \Dvelum\Utils::rekey('id', $data);
            }
        }

        foreach ($menuItems as $k => &$v) {
            $v['link_url'] = '';
            switch ($v['link_type']) {
                case 'page' :
                    if ($v['page_code'] == 'index') {
                        $v['link_url'] = $request->url(['']);
                    } else {
                        $v['link_url'] = $request->url([$v['page_code']]);
                    }
                    break;
                case 'url' :
                    $v['link_url'] = $v['url'];
                    break;
                case 'resource' :
                    if (isset($resourcesData[$v['resource_id']])) {
                        $v['link_url'] = $mediaModel->addWebRoot($resourcesData[$v['resource_id']]['path']);
                    }
                    break;
                case 'nolink' :
                    $v['link_url'] = false;
                    break;
            }
        }
        unset($v);

        return $menuItems;
    }
}