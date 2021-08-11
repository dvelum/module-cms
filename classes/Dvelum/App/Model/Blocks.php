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

class Blocks extends Model
{

    /**
     * Set block mapping for page
     * @param Model $blockMapping
     * @param int $pageId
     * @param array $map
     *          like array(
     *          'code'=>array('blockid1','blockid2'),... or
     *          'code'=>array(array('id'=>'blockid1'),array('id':'blockid2')
     *          'code3'=>array('blockid4','blockid7)
     *          )
     */
    public function setMapping(Model $blockMapping, int $pageId, array $map)
    {
        $blockMapping->clearMap($pageId);

        if (empty($map)) {
            return true;
        }

        foreach ($map as $code => $items) {
            $ids = array();
            if (!empty($items)) {
                foreach ($items as $k => $v) {
                    if (is_array($v)) {
                        $ids[] = $v['id'];
                    } else {
                        $ids[] = $v;
                    }
                }
            }

            $blockMapping->addBlocks($pageId, $code, $ids);
        }

        return true;
    }

    /**
     * Get block list for page
     * @param Model $blockMapping
     * @param int $page
     * @param int|bool $version - optional
     * @return array - block list sorted by place code
     */
    public function getPageBlocks(Model $blockMapping, int $pageId, $version = false) : array
    {
        if ($version) {
            return $this->extractBlocks($pageId, $version);
        }


        $sql = $this->db->select()
            ->from(array(
                't' => $this->table()
            ))
            ->join(array(
                'map' => $blockMapping->table()
            ), 't.id = map.block_id', array(
                'place'
            ));

        if (!$pageId) {
            $sql->where('map.page_id  IS NULL');
        } else {
            $sql->where('map.page_id = ' . ((int)$pageId));
        }
        $sql->order('map.order_no ASC');

        $data = $this->db->fetchAll($sql);

        if (!empty($data)) {
            $data = \Dvelum\Utils::groupByKey('place', $data);
        }

        return $data;
    }

    /**
     * Get blocks map from object vesrion
     * @param Model $vcModel
     * @param int $pageId
     * @param int $version
     * @return array
     */
    public function extractBlocks(Model $vcModel, int $pageId, int $version) : array
    {

        $data = $vcModel->getData('page', $pageId, $version);

        if (!isset($data['blocks']) || empty($data['blocks'])) {
            return array();
        }

        $data = unserialize($data['blocks']);

        if (empty($data)) {
            return [];
        }

        $ids = [];
        $info = [];
        foreach ($data as $place => $items) {
            if (!empty($items)) {
                foreach ($items as $index => $config) {
                    $ids[] = $config['id'];
                }

                $sql = $this->db->select()
                    ->from($this->table())
                    ->where('`id` IN(' . \Dvelum\Utils::listIntegers($ids) . ')');

                $info = $this->db->fetchAll($sql);
            }
        }

        if (!empty($info)) {
            $info = \Dvelum\Utils::rekey('id', $info);
        }

        foreach ($data as $place => $items) {
            if (!empty($items)) {
                foreach ($items as $index => $config) {
                    if (isset($info[$config['id']])) {
                        $data[$place][$index] = $info[$config['id']];
                        $data[$place][$index]['place'] = $place;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Get default blocks map
     * @return array
     */
    public function getDefaultBlocks()
    {
        return $this->getPageBlocks(0);
    }
}