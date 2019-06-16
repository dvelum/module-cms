<?php
/**
 *  DVelum project https://github.com/dvelum/dvelum , https://github.com/k-samuel/dvelum , http://dvelum.net
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

namespace Dvelum\App\Router;

use Dvelum\App\Auth;
use Dvelum\App\Cache\Manager;
use Dvelum\App\Router;
use Dvelum\Config;
use Dvelum\Page\Page;
use Dvelum\Request;
use Dvelum\Response;
use Dvelum\Orm\Model;
use Dvelum\App\Session\User;

/**
 * Back office
 */
class Cms extends Router
{
    const CACHE_KEY_ROUTES = 'Frontend_Routes';

    protected $appConfig;
    protected $frontendConfig;
    protected $moduleRoutes;

    public function __construct()
    {
        $this->appConfig = Config::storage()->get('main.php');
        $this->frontendConfig = Config::storage()->get('frontend.php');
    }

    /**
     * Route request
     * @param Request $request
     * @param Response $response
     */
    public function route(Request $request, Response $response): void
    {
        $pageVersion = $request->get('vers', 'int', false);

        $showRevision = false;
        $pageCode = $request->getPart(0);

        if (!is_string($pageCode) || !strlen($pageCode)) {
            $pageCode = 'index';
        }

        $pageData = Model::factory('Page')->getCachedItemByField('code', $pageCode);

        if (empty($pageData)) {
            $response->redirect('/');
            return;
        }

       $auth = new Auth($request, $this->appConfig);
       $auth->auth();

        if ($pageVersion && empty($request->getPart(1))) {
            $user = User::factory();
            if ($user->isAuthorized() && $user->isAdmin()) {
                $pageData = array_merge(
                    $pageData,
                    Model::factory('Vc')->getData('page', $pageData['id'], $pageVersion)
                );
                $showRevision = true;
            }
        }

        if ($pageData['published'] == false && !$showRevision) {
            $response->redirect('/');
        }

        $page = Page::factory();
        $this->applyPageData($pageData, $page);

        /**
         * Check if controller attached
         */
        if (strlen($pageData['func_code'])) {
            $fModules = Config::factory(Config\Factory::File_Array, $this->appConfig->get('frontend_modules'));

            if ($fModules->offsetExists($pageData['func_code'])) {
                $controllerConfig = $fModules->get($pageData['func_code']);
                $this->runController($controllerConfig['class'], $request->getPart(1), $request, $response);
            }
        } else {
            $this->runController($this->frontendConfig->get('default_controller'), null, $request, $response);
        }
    }

    protected function applyPageData(array $data, Page $page) : void
    {
        /*
            Page Object Fields
            [is_fixed]
            [parent_id]
            [code]
            [page_title]
            [menu_title]
            [html_title]
            [meta_keywords]
            [meta_description]
            [text]
            [func_code]
            [show_blocks]
            [in_site_map]
            [order_no]
            [blocks]
            [theme]
            [default_blocks]
            [id]
            [date_created]
            [date_published]
            [date_updated]
            [author_id]
            [editor_id]
            [published]
            [published_version]
            [last_version]
         */
        $page = Page::factory();
        $page->setId($data['id']);
        $page->setCode($data['code']);
        $page->setText($data['text']);
        $page->setHtmlTitle($data['html_title']);
        $page->setTitle($data['page_title']);
        $page->setMetaDescription($data['meta_description']);
        $page->setMetaKeywords($data['meta_keywords']);
        $page->setTheme($data['theme']);
        $page->setProperties([
            'show_blocks' => $data['show_blocks'],
            'in_site_map' => $data['in_site_map'],
            'default_blocks' => $data['default_blocks'],
            'func_code' => $data['func_code']
        ]);
    }

    /**
     * Run controller
     * @param string $controller
     * @param null|string $action
     * @param Request $request
     * @param Response $response
     */
    public function runController(string $controller, ?string $action, Request $request, Response $response): void
    {
        parent::runController($controller, $action, $request, $response);
    }

    protected function getModulesRoutes()
    {
        if (isset($this->moduleRoutes)) {
            return $this->moduleRoutes;
        }

        $this->moduleRoutes = array();

        $cacheManager = new Manager();
        $cache = $cacheManager->get('data');

        if (!$cache || !$list = $cache->load(self::CACHE_KEY_ROUTES)) {
            $pageModel = Model::factory('Page');
            $db = $pageModel->getDbConnection();
            $sql = $db->select()->from($pageModel->table(), array(
                'code',
                'func_code'
            ))->where('`published` = 1')->where('`func_code` !="" ');
            $list = $db->fetchAll($sql);
            if ($cache) {
                $cache->save($list, self::CACHE_KEY_ROUTES);
            }
        }

        if (!empty($list)) {
            foreach ($list as $item) {
                $this->moduleRoutes[$item['func_code']] = $item['code'];
            }
        }
        return $this->moduleRoutes;
    }

    /**
     * Define url address to call the module
     * The method locates the url of the published page with the attached
     * functionality
     * specified in the passed argument.
     * Thus, there is no need to know the exact page URL.
     *
     * @param string $module - module name
     * @return string
     */
    public function findUrl(string $module): string
    {
        if (!isset($this->moduleRoutes)) {
            $this->getModulesRoutes();
        }

        if (!isset($this->moduleRoutes[$module])) {
            return '';
        }

        return $this->moduleRoutes[$module];
    }
}