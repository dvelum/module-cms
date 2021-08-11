<?php
use Psr\Container\ContainerInterface as c;

return [
    'config.frontend' => static function (c $c) : \Dvelum\Config\ConfigInterface{
       return $c->get(\Dvelum\Config\Storage\StorageInterface::class)->get('frontend.php');
    },
    \Dvelum\App\BlockManager::class => static function (c $c): \Dvelum\App\BlockManager{
        $cache = $c->has(\Dvelum\Cache\CacheInterface::class) ? $c->get(
            \Dvelum\Cache\CacheInterface::class
        ) : null;
        $blockManager = new \Dvelum\App\BlockManager($c->get(Dvelum\Orm\Orm::class), $c->get(\Dvelum\Template\Service::class), $cache);
        if($c->get('config.main')->get('blockmanager_hard_cache')){
            $blockManager->useHardCacheTime(true);
        }
        return $blockManager;
    }
];