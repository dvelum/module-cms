<?php
return [
    'id' => 'dvelum-module-cms',
    'version' => '3.0.1',
    'author' => 'Kirill Yegorov',
    'name' => 'DVelum CMS',
    'configs' => './configs',
    'locales' => './locales',
    'resources' =>'./resources',
    'templates' => './templates',
    'vendor'=>'Dvelum',
    'autoloader'=> [
        './classes'
    ],
    'objects' =>[
        'blockmapping',
        'blocks',
        'mediacategory',
        'medialib',
        'menu',
        'menu_item',
        'page'
    ],
    'post-install'=>'\\Dvelum\\Cms\\Installer'
];