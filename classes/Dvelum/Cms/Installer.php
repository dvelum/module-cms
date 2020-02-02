<?php
namespace Dvelum\Cms;

use Dvelum\App\Model\Blockmapping;
use Dvelum\App\Model\Blocks;
use Dvelum\App\Model\Permissions;
use Dvelum\Config\ConfigInterface;
use Dvelum\App\Session\User;
use Dvelum\Orm\Model;
use Dvelum\Orm\Record;
use Dvelum\Utils;

class Installer extends \Dvelum\Externals\Installer
{
    /**
     * Install
     * @param ConfigInterface $applicationConfig
     * @param ConfigInterface $moduleConfig
     * @return bool
     * @throws \Exception
     */
    public function install(ConfigInterface $applicationConfig, ConfigInterface $moduleConfig) : bool
    {
        $moduleList = ['Blocks' ,'Medialib' ,'Mediacategory' ,'Mediaconfig' ,'Menu' ,'Page'];
        // Add permissions
        $userInfo = User::factory()->getInfo();
        /**
         * @var Permissions $permissionsModel
         */
        $permissionsModel = Model::factory('Permissions');
        foreach ($moduleList as $moduleName){
            if (!$permissionsModel->setGroupPermissions($userInfo['group_id'], $moduleName, 1, 1, 1, 1)) {
                return false;
            }
        }

        $this->addRecords();

        return true;
    }

    protected function addRecords()
    {
        $indexPage = Model::factory('Page')->getItemByField('code','index');
        if(empty($indexPage)){
            $indexPage = Record::factory('Page');
            $indexPage->setValues([
                'code'=>'index',
                'is_fixed'=>1,
                'html_title'=>'Index',
                'menu_title'=>'Index',
                'page_title'=>'Index',
                'meta_keywords'=>'',
                'meta_description'=>'',
                'parent_id'=>null,
                'text' =>'[Index page content]',
                'func_code'=>'',
                'order_no' => 1,
                'show_blocks'=>true,
                'published'=>true,
                'published_version'=>0,
                'date_created'=>date('Y-m-d H:i:s'),
                'date_updated'=>date('Y-m-d H:i:s'),
                'blocks'=>'',
                'theme'=>'default',
                'date_published'=>date('Y-m-d H:i:s'),
                'in_site_map'=>false,
                'default_blocks'=>true
            ]);
            $indexPage->saveVersion();
            $indexPage->publish();
        }else{
            $indexPage = Record::factory('Page', $indexPage['id']);
        }

        // add menu
        $topMenu = Record::factory('Menu');
        $topMenu->setValues([
            'code' =>'headerMenu',
            'title' => 'Header Menu'
        ]);

        if(!$topMenu->save())
            return false;

        $topMenuItem = Record::factory('Menu_Item');
        $topMenuItem->setValues([
            'page_id'=>$indexPage->getId(),
            'title'=>'Index',
            'menu_id'=>$topMenu->getId(),
            'order'=>0,
            'parent_id'=>null,
            'tree_id'=>1,
            'link_type'=>'page',
            'published'=>1
        ]);

        if(!$topMenuItem->save())
            return false;


        $bottomMenu = Record::factory('Menu');
        $bottomMenu->setValues([
            'code' =>'footerMenu',
            'title' => 'Footer Menu'
        ]);
        if(!$bottomMenu->save())
            return false;

        $bottomMenuItem = Record::factory('Menu_Item');
        $bottomMenuItem->setValues([
            'page_id'=>$indexPage->getId(),
            'title'=>'Index',
            'menu_id'=>$bottomMenu->getId(),
            'order'=>0,
            'parent_id'=>null,
            'tree_id'=>1,
            'link_type'=>'page',
            'published'=>1
        ]);
        if(!$bottomMenuItem->save())
            return false;


        $leftMenu = Record::factory('Menu');
        $leftMenu->setValues([
            'code' =>'menu',
            'title' => 'Menu'
        ]);
        if(!$leftMenu->save())
            return false;

        $leftMenuItem = Record::factory('Menu_Item');
        $leftMenuItem->setValues([
            'page_id'=>$indexPage->getId(),
            'title'=>'Index',
            'menu_id'=>$leftMenu->getId(),
            'order'=>0,
            'parent_id'=>null,
            'tree_id'=>1,
            'link_type'=>'page',
            'published'=>1
        ]);
        if(!$leftMenuItem->save())
            return false;


        // add blocks
        $topMenuBlock = Record::factory('Blocks');
        $topMenuBlock->setValues([
            'title' => 'Header Menu',
            'published' =>1,
            'show_title'=>false,
            'published_version'=>0,
            'is_system'=>1,
            'sys_name'=>'Block_Menu_Top',
            'is_menu' =>1,
            'menu_id'=>$topMenu->getId(),
            'last_version'=>0
        ]);
        if(!$topMenuBlock->saveVersion() || !$topMenuBlock->publish())
            return false;


        $bottomMenuBlock = Record::factory('Blocks');
        $bottomMenuBlock->setValues([
            'title' => 'Footer Menu',
            'published' =>1,
            'show_title'=>false,
            'published_version'=>0,
            'is_system'=>1,
            'sys_name'=>'Block_Menu_Footer',
            'is_menu' =>1,
            'menu_id'=>$bottomMenu->getId(),
            'last_version'=>0
        ]);

        if(!$bottomMenuBlock->saveVersion() || !$bottomMenuBlock->publish()){
            return false;
        }

        $menuBlock = Record::factory('Blocks');
        $menuBlock->setValues([
            'title' => 'Menu',
            'published' =>1,
            'show_title'=>true,
            'published_version'=>0,
            'is_system'=>1,
            'sys_name'=>'Block_Menu',
            'is_menu' =>1,
            'menu_id'=>$leftMenu->getId(),
            'last_version'=>0
        ]);
        if(!$menuBlock->saveVersion() || !$menuBlock->publish())
            return false;

        $testBlock = Record::factory('Blocks');
        $testBlock->setValues([
            'title' => 'Test Block',
            'published' =>1,
            'show_title'=>true,
            'published_version'=>0,
            'text'=>'
                    <ul>
                        <li>Articles</li>
                        <li>Pages</li>
                        <li>Blocks</li>
                        <li>Users</li>
                    </ul>
                ',
            'is_system'=>false,
            'is_menu' =>false,
            'last_version'=>0
        ]);
        if(!$testBlock->saveVersion() || !$testBlock->publish())
            return false;

        /**
         * @var Blockmapping $blockMapping
         */
        $blockMapping = Model::factory('Blockmapping');
        $blockMapping->clearMap(0);
        $blockMap = [
            'top-blocks' => [
                ['id'=>$topMenuBlock->getId()]
            ],
            'bottom-blocks' => [
                ['id'=>$bottomMenuBlock->getId()]
            ],
            'left-blocks' => [
                ['id'=>$menuBlock->getId()]
            ],
            'right-blocks' => [
                ['id'=>$testBlock->getId()]
            ]
        ];

        foreach ($blockMap as $place=>$items){
            $blockMapping->addBlocks(0 , $place , Utils::fetchCol('id', $items));
        }
    }

    /**
     * Uninstall
     * @param ConfigInterface $applicationConfig
     * @param ConfigInterface $moduleConfig
     * @return bool
     */
    public function uninstall(ConfigInterface $applicationConfig, ConfigInterface $moduleConfig)
    {

    }
}