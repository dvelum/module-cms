<?php
/**
 *  DVelum project http://code.google.com/p/dvelum/ , https://github.com/k-samuel/dvelum , http://dvelum.net
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
 *
 */
declare(strict_types=1);

namespace Dvelum\App\Trigger;

use Dvelum\App\Trigger;
use Dvelum\Orm;
use Dvelum\Orm\Model;
use Dvelum\Service;

class Page extends Trigger
{
	public function onAfterAdd(Orm\RecordInterface $object)
	{
		parent::onAfterAdd($object);	
		$this->clearBlockCache($object->getId());
	}
	
	public function onAfterUpdate(Orm\RecordInterface $object)
	{
		parent::onAfterUpdate($object);
			
		$this->clearBlockCache($object->getId());
		$this->clearItemCache($object->code ,$object->getId());	
	}
	
	public function onAfterDelete(Orm\RecordInterface $object)
	{
		parent::onAfterDelete($object);
			
		$this->clearBlockCache($object->getId());
		$this->clearItemCache($object->code ,$object->getId());
        Model::factory('Blockmapping')->clearMap($object->getId());
	}
	
	public function clearItemCache($code , $id)
	{
		if(!$this->cache)
			return;

		$model = Model::factory('Page');
		$this->cache->remove($model->getCacheKey(array('item', 'code', $code)));
		$this->cache->remove(\Model_Page::getCodeHash($id));
		$this->cache->remove(\Model_Page::getCodeHash($code));
		$bm =  Service::get('blockManager');
		$bm->invalidatePageMap($id);
		$this->cache->remove(\Router_Module::CACHE_KEY_ROUTES);
	}

    public function clearBlockCache($pageId)
	{
		if($this->cache){
			$bm = Service::get('blockManager');
			$this->cache->remove($bm->hashPage($pageId));
			$this->cache->remove(Model::factory('Page')->getCacheKey(array('codes')));
		}
	}
}