<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Http\Proxy;
use Packlink\BusinessLogic\IntegrationRegistration\IntegrationRegistrationServiceInterface;
use PrestaShop\PrestaShop\Adapter\Configuration;

class IntegrationRegistrationService implements IntegrationRegistrationServiceInterface
{
    const INTEGRATION_TYPE = 'prestashop_module';
    /**
     * @var string|null
     */
    private $integrationId = null;
    /**
     * @var Proxy
     */
    private $proxy;

    /**
     * @var Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService
     */
    private $configurationService;

    public function __construct()
    {
        $this->proxy = ServiceRegister::getService(Proxy::CLASS_NAME);
        $this->configurationService = ServiceRegister::getService(
            \Logeecom\Infrastructure\Configuration\Configuration::CLASS_NAME);
    }

    /**
     * Registers the integration with Packlink and saves integration ID from the response into ConfigEntity.
     *
     * @return null|string Integration identifier or null if request fails
     *
     * @throws \Logeecom\Infrastructure\Http\Exceptions\HttpAuthenticationException
     * @throws \Logeecom\Infrastructure\Http\Exceptions\HttpCommunicationException
     * @throws \Logeecom\Infrastructure\Http\Exceptions\HttpRequestException
     * @throws \Packlink\BusinessLogic\IntegrationRegistration\Exceptions\IntegrationNotRegisteredException
     */
    public function registerIntegration()
    {
        $existingId = $this->getIntegrationId();
        if (!empty($existingId)) {
            return $existingId;
        }

        $payload = array(
            'integration_type' => $this->getIntegrationType(),
            'integration' => array(
                'guid' => $this->getIntegrationGuid(),
                'name' => $this->getIntegrationName(),
            ),
            'webhooks' => array(
                'http_header_name' => 'X-Packlink-Webhook-Secret', //TODO: Probably wrong -> will change when get to webhooks
                'http_header_value' => $this->getWebhookSecret(),
                'status_update_url' => $this->getIntegrationWebhookStatusUpdateUrl(),
            ),
        );

        $integrationId = $this->proxy->registerIntegration($payload);
        $this->configurationService->setIntegrationId($integrationId);
        $this->integrationId = $integrationId;

        return $integrationId;
    }

    /**
     * Disconnects the integration from Packlink.
     */
    public function disconnectIntegration()
    {
        // TODO: Implement disconnectIntegration() method.
    }

    /**
     * Returns the persisted integration GUID.
     *
     * @return string Integration GUID.
     */
    public function getIntegrationGuid()
    {
        $guid = $this->configurationService->getIntegrationGuid();
        if (!$guid) {
            $guid = \Logeecom\Infrastructure\Utility\GuidProvider::getInstance()->generateGuid();
            $this->configurationService->setIntegrationGuid($guid);
        }

        return $guid;
    }

    /**
     * Returns the webhook secret.
     *
     * @return string Webhook secret used for authentication.
     */
    public function getWebhookSecret()
    {
        $secret = $this->configurationService->getWebhookSecret();
        if (!$secret) {
            $bytes32 = openssl_random_pseudo_bytes(32);
            $secret = rtrim(strtr(base64_encode($bytes32), '+/', '-_'), '=');
            $this->configurationService->setWebhookSecret($secret);
        }

        return $secret;
    }

    /**
     * Returns the integration ID if present as class variable or in database, otherwise returns null
     *
     * @return string|null Integration ID.
     */
    public function getIntegrationId()
    {
        if ($this->integrationId) {
            return $this->integrationId;
        }

        $result = $this->configurationService->getIntegrationId();

        if ($result) {
            $this->integrationId = $result;

            return $this->integrationId;
        }

        return null;
    }

    /**
     * Returns the integration type (e.g. Prestashop, WooCommerce...).
     *
     * @return string Integration type.
     */
    public function getIntegrationType()
    {
        return self::INTEGRATION_TYPE;
    }

    /**
     * Returns the name of the integration.
     *
     * @return string Integration name.
     */
    public function getIntegrationName()
    {
        return \Configuration::get('PS_SHOP_NAME');
    }

    /**
     * Returns the WebhookStatusUpdateUrl.
     *
     * @return string Integration name.
     */
    public function getIntegrationWebhookStatusUpdateUrl()
    {
        return $this->configurationService->getStatusUpdateUrl('registrationwebhooks');
    }
}