<?php

use Packlink\BusinessLogic\Controllers\AutoConfigurationController;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

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
