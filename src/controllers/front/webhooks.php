<?php

use Packlink\BusinessLogic\WebHook\WebHookEventHandler;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\EmployeeUtility;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/** @noinspection AutoloadingIssuesInspection */

/**
 * Class PacklinkWebhooksModuleFrontController
 */
class PacklinkWebhooksModuleFrontController extends ModuleFrontController
{
    /**
     * PacklinkAsyncProcessModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    /**
     * Handles incoming Packlink webhook events.
     */
    public function initContent()
    {
        if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            EmployeeUtility::impersonate();
        }

        $input = \Tools::file_get_contents('php://input');

        $webhookHandler = WebHookEventHandler::getInstance();

        if (!$webhookHandler->handle($input)) {
            PacklinkPrestaShopUtility::die400(array('message' => 'Invalid payload'));
        }

        PacklinkPrestaShopUtility::dieJson(array('success' => true));
    }

    /**
     * Displays maintenance page if shop is closed.
     */
    public function displayMaintenancePage()
    {
        // allow async process in maintenance mode
    }

    /**
     * Displays 'country restricted' page if user's country is not allowed.
     */
    protected function displayRestrictedCountryPage()
    {
        // allow async process
    }
}
