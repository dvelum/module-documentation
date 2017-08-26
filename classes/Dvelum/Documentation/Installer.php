<?php
namespace Dvelum\Documentation;

use Dvelum\Config\ConfigInterface;
use Dvelum\App\Session\User;
use Dvelum\Orm\Model;

class Installer extends \Externals_Installer
{
    /**
     * Install
     * @param ConfigInterface $applicationConfig
     * @param ConfigInterface $moduleConfig
     * @return boolean
     */
    public function install(ConfigInterface $applicationConfig, ConfigInterface $moduleConfig)
    {
        // Add permissions
        $userInfo = User::getInstance()->getInfo();
        /**
         * @var \Model_Permissions $permissionsModel
         */
        $permissionsModel = Model::factory('Permissions');
        if (!$permissionsModel->setGroupPermissions($userInfo['group_id'], 'Docs', 1, 1, 1, 1)) {
            return false;
        }
        return true;
    }

    /**
     * Uninstall
     * @param ConfigInterface $applicationConfig
     * @param ConfigInterface $moduleConfig
     * @return boolean
     */
    public function uninstall(ConfigInterface $applicationConfig, ConfigInterface $moduleConfig)
    {

    }
}