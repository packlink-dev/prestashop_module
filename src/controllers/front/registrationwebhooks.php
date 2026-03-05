<?php

use Packlink\BusinessLogic\WebHook\IntegrationRegistrationWebhookEventHandler;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\EmployeeUtility;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/**
 * Front controller for integration registration.
 */
class PacklinkRegistrationwebhooksModuleFrontController extends ModuleFrontController
{
    /**
     * Hardcoded header name Packlink uses to send the webhook secret.
     */
    const WEBHOOK_SECRET_HEADER = 'X-Packlink-Webhook-Secret';

    /**
     * PacklinkRegistrationWebhooksModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    /**
     * Handles incoming Packlink integration lifecycle webhook events.
     */
    public function initContent()
    {
        if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            EmployeeUtility::impersonate();
        }

        $input = \Tools::file_get_contents('php://input');

        $webhookHandler = IntegrationRegistrationWebHookEventHandler::getInstance();

        if (!$webhookHandler->handle($input)) {
            PacklinkPrestaShopUtility::die400(array('message' => 'Invalid request'));
        }

        PacklinkPrestaShopUtility::dieJson(array('success' => true));
    }
}
