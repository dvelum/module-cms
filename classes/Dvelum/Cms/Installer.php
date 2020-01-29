<?php
namespace Dvelum\Cms;

use Dvelum\App\Model\Permissions;
use Dvelum\Config\ConfigInterface;
use Dvelum\App\Session\User;
use Dvelum\Orm\Model;
use Dvelum\Orm\Record;

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

        $indexPage = Model::factory('Page')->getItemByField('code','index');
        if(empty($indexPage)){
            $indexPage = Record::factory('Page');
            $indexPage->setValues([
                'code' => 'index',
                'html_title' => 'index',
                'menu_title' => 'index',
                'page_title' => 'index'
            ]);
            $indexPage->saveVersion();
        }
        return true;
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