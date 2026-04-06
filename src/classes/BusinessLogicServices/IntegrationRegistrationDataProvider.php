<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Packlink\BusinessLogic\IntegrationRegistration\AbstractIntegrationDataProvider;

class IntegrationRegistrationDataProvider extends AbstractIntegrationDataProvider
{
    const INTEGRATION_TYPE = 'prestashop_module';

    public function __construct($configurationService)
    {
        parent::__construct($configurationService);
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
        return $this->getConfigService()->getStatusUpdateUrl();
    }

    /**
     * Reset AuthorizationCredentials.
     *
     * @return void
     */
    public function deleteToken()
    {
        $this->getConfigService()->resetAuthorizationCredentials();
    }
}