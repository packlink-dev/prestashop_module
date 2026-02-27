<?php

use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\EmployeeUtility;

class PacklinkRegistrationwebhooksModuleFrontController extends ModuleFrontController
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

        //TODO: SET UP WEBHOOKHANDLER IN CORE

        // $webhookHandler = WebHookEventHandler::getInstance();
        //
        // if (!$webhookHandler->handle($input)) {
        //     PacklinkPrestaShopUtility::die400(array('message' => 'Invalid payload'));
        // }
        //
        // PacklinkPrestaShopUtility::dieJson(array('success' => true));
    }
}