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

class Menu extends Trigger
{
    public function onAfterDelete(Orm\RecordInterface $object)
    {
        if(!$this->cache)
            return;

        parent::onAfterDelete($object);
        $this->clearBlockCache($object);
    }

    public function onAfterUpdate(Orm\RecordInterface $object)
    {
        if(!$this->cache)
            return;

        parent::onAfterUpdate($object);
        $this->clearBlockCache($object);
    }

    public function clearBlockCache(Orm\RecordInterface $object)
    {
        if(!$this->cache)
            return;

        /**
         * @var \Model_Menu $menuModel
         */
        $menuModel = Model::factory('Menu');
        $menuModel->resetCachedMenuLinks($object->getId());
        $blockManager = Service::get('blockManager');
        $blockManager->invalidateCacheBlockMenu($object->getId());
    }
}