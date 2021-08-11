<?php
return [
    /*
   * the type of frontend router with two possible values:
   * 'Module' — using tree-like page structure  (‘Pages’ section of the back-office panel);
   * 'Path' — the router based on the file structure of client controllers.
   * 'Config' - using frontend modules configuration
   */
    'router' => \Dvelum\App\Router\Cms::class, // 'Cms','Path','Config'
    // Default Frontend Controller
    'default_controller' => '\\Dvelum\\App\\Frontend\\Cms\\Index\\Controller',
];