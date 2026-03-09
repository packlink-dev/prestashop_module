<?php

use Logeecom\Infrastructure\Configuration\Configuration;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/**
 * Class PacklinkIntegrationStatusModuleFrontController
 *
 * Returns the current integration activation status.
 * Called from StateController.js after the initial state check.
 */
class PacklinkIntegrationStatusModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    public function initContent()
    {
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);

        PacklinkPrestaShopUtility::dieJson(array(
            'status' => $configService->getIntegrationStatus() ?: 'ACTIVE',
        ));
    }
}
