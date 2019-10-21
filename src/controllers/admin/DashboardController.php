<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

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
