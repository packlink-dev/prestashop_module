<?php

use Packlink\BusinessLogic\Controllers\AutoConfigurationController;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/**
 * Class PacklinkAutoConfigureController.
 */
class PacklinkAutoConfigureController extends ModuleAdminController
{
    /**
     * PacklinkAutoConfigureController constructor.
     *
     * @throws \PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        $this->bootstrap = true;
    }

    /**
     * Starts the auto-configuration.
     */
    public function initContent()
    {
        $controller = new AutoConfigurationController();

        PacklinkPrestaShopUtility::dieJson(array('success' => $controller->start(true)));
    }
}
