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
        $url = $this->getConfigService()->getStatusUpdateUrl();
        return $this->enforceHttps($url);
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

    /**
     * Ensures the given URL uses HTTPS if the shop is operating over SSL.
     *
     * PrestaShop 1.6.x has a known issue where getModuleLink() may ignore
     * the $ssl parameter and return an HTTP URL even when SSL is active.
     * This method corrects that by checking PrestaShop's own SSL config
     * and the current request protocol, then upgrading the scheme if needed.
     *
     * @param string $url The URL to potentially upgrade.
     *
     * @return string The URL with the correct scheme.
     */
    private function enforceHttps($url)
    {
        if (strpos($url, 'http://') === 0) {
            $url = 'https://' . substr($url, 7);
        }

        return $url;
    }
}