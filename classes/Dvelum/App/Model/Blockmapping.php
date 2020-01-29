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
use Dvelum\Orm;
use Dvelum\Orm\Model;

class Blockmapping extends Model
{
 	/**
     * Clear block map for page by page ID
     * @param int $pageId
     * @return void
     */
    public function clearMap($pageId) : void
    {
        if(!$pageId)
          $this->db->delete($this->table(),' `page_id` IS NULL');
        else 
          $this->db->delete($this->table(),' `page_id` = ' . intval($pageId));
    }

    /**
     * Add block map for page
     * @param int $pageId
     * @param string $code
     * @param array $blockIds
     */
    public function addBlocks(int $pageId , string $code , array $blockIds)
    {
        if(empty($blockIds))
            return true;
            
        $order = 0;
        foreach ($blockIds as $id)
        {
            $blockmapItem = Orm\Record::factory('blockmapping');
            $blockmapItem->set('block_id' , $id);
            if($pageId)
              $blockmapItem->set('page_id' , $pageId);
            
            $blockmapItem->set('place' , $code);
            $blockmapItem->set('order_no' , $order);
            $blockmapItem->save(false);
            $order++;
        }    
        return true;    
    }
}