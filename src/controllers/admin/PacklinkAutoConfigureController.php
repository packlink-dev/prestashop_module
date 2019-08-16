<?php

use Packlink\BusinessLogic\Controllers\AutoConfigurationController;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/**
 * Class PacklinkAutoConfigureController.
 */
class PacklinkAutoConfigureController extends PacklinkBaseController
{
    /**
     * Starts the auto-configuration.
     */
    public function initContent()
    {
        $controller = new AutoConfigurationController();

        PacklinkPrestaShopUtility::dieJson(array('success' => $controller->start(true)));
    }
}
