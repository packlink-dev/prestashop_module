<?php
/**
 * 2019 Packlink
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Packlink <support@packlink.com>
 * @copyright 2019 Packlink Shipping S.L
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\PrestaShop\Classes\WebhookHandlers\WebhookHandlerFactory;

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
        $input = \Tools::file_get_contents('php://input');

        Logger::logDebug(
            $this->l('Webhook from Packlink received.'),
            'Integration',
            array('payload' => $input)
        );

        $payload = json_decode($input);

        $this->validatePayload($payload);
        $this->checkAuthToken();

        $webhookHandler = WebhookHandlerFactory::create($payload->event);
        $webhookHandler->handle($payload->data);

        PacklinkPrestaShopUtility::dieJson(array('success' => true));
    }

    /**
     * Validates request payload and returns bad request response in case of invalid payload.
     *
     * @param \stdClass $payload Request data.
     */
    private function validatePayload($payload)
    {
        if (empty($payload)
            || !$payload->datetime
            || !$payload->data
            || !in_array($payload->event,
                array(
                    'shipment.carrier.success',
                    'shipment.carrier.fail',
                    'shipment.label.ready',
                    'shipment.label.fail',
                    'shipment.tracking.update',
                    'shipment.delivered',
                ),
                true)
        ) {
            PacklinkPrestaShopUtility::die400(array('message' => 'Invalid payload'));
        }
    }

    private function checkAuthToken()
    {
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
        $configService = ServiceRegister::getService(Logeecom\Infrastructure\Configuration\Configuration::CLASS_NAME);
        $authToken = $configService->getAuthorizationToken();

        if (empty($authToken)) {
            PacklinkPrestaShopUtility::die404(array('message' => 'Authorization token not found'));
        }
    }
}