<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class DashboardController
 */
class DashboardController extends PacklinkBaseController
{
    /**
     * Retrieves current setup status.
     */
    public function displayAjaxGetStatus()
    {
        $controller = new \Packlink\BusinessLogic\Controllers\DashboardController();

        PacklinkPrestaShopUtility::dieJson($controller->getStatus()->toArray());
    }
}
