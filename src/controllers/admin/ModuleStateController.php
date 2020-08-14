<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\BusinessLogic\Controllers\ModuleStateController as BaseModuleStateController;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class ModuleStateController
 */
class ModuleStateController extends PacklinkBaseController
{
    /**
     * Returns the current state of the module.
     */
    public function displayAjaxGetCurrentState()
    {
        $controller = new BaseModuleStateController();

        PacklinkPrestaShopUtility::dieJson($controller->getCurrentState()->toArray());
    }
}
