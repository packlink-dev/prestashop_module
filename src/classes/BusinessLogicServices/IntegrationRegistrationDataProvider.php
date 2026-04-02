<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Packlink\BusinessLogic\IntegrationRegistration\Interfaces\IntegrationRegistrationDataProviderInterface;

class IntegrationRegistrationDataProvider implements IntegrationRegistrationDataProviderInterface
{
    const INTEGRATION_TYPE = 'prestashop_module';
    /**
     * @var string|null
     */
    private $integrationId = null;
    /**
     * @var ConfigurationService
     */
    private $configurationService;

    public function __construct($configurationService)
    {
        $this->configurationService = $configurationService;
    }

    /**
     * @return array Payload.
     */
    public function getRegistrationPayload()
    {
        return array(
            'integration_type' => $this->getIntegrationType(),
            'integration' => array(
                'guid' => $this->getIntegrationGuid(),
                'name' => $this->getIntegrationName(),
            ),
            'webhooks' => array(
                'http_header_name' => 'X-Packlink-Webhook-Secret',
                'http_header_value' => $this->getWebhookSecret(),
                'status_update_url' => $this->getIntegrationWebhookStatusUpdateUrl(),
            ),
        );
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
     * Saves Integration Identifier to database
     *
     * @param string $integrationId
     *
     * @return void
     */
    public function setIntegrationId($integrationId)
    {
        $this->configurationService->setIntegrationId($integrationId);
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
            $cryptoStrong = false;
            $bytes32 = openssl_random_pseudo_bytes(32, $cryptoStrong);

            if ($bytes32 === false || $cryptoStrong === false) {
                throw new \RuntimeException('Unable to generate a secure webhook secret.');
            }

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
        return $this->configurationService->getStatusUpdateUrl();
    }

    /**
     * Removes integration registration data from the database.
     *
     * @return void
     */
    public function deleteIntegrationData()
    {
        $this->integrationId = null;
        $this->configurationService->deleteIntegrationData();
    }

    /**
     * Reset AuthorizationCredentials.
     *
     * @return void
     */
    public function deleteToken()
    {
        $this->configurationService->resetAuthorizationCredentials();
    }
}