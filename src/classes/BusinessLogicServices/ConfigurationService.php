<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Logeecom\Infrastructure\Logger\Logger;
use Packlink\BusinessLogic\Configuration;

class ConfigurationService extends Configuration
{
    /**
     * @inheritdoc
     */
    const SCHEDULER_TIME_THRESHOLD = 1800;
    /**
     * @inheritdoc
     */
    const MIN_LOG_LEVEL = Logger::ERROR;
    /**
     * @var string
     */
    private $moduleVersion;

    /**
     * Returns current system identifier.
     *
     * @return string Current system identifier.
     */
    public function getCurrentSystemId()
    {
        return \Configuration::get('PS_SHOP_DEFAULT');
    }

    /**
     * Returns web-hook callback URL for current system.
     *
     * @return string Web-hook callback URL.
     */
    public function getWebHookUrl()
    {
        return \Context::getContext()->link->getModuleLink(
            'packlink',
            'webhooks',
            array(),
            null,
            null,
            \Configuration::get('PS_SHOP_DEFAULT')
        );
    }

    /**
     * Returns async process starter url, always in http.
     *
     * @param string $guid Process identifier.
     *
     * @return string Formatted URL of async process starter endpoint.
     */
    public function getAsyncProcessUrl($guid)
    {
        $params = array('guid' => $guid);

        return \Context::getContext()->link->getModuleLink(
            'packlink',
            'asyncprocess',
            $params,
            null,
            null,
            \Configuration::get('PS_SHOP_DEFAULT')
        );
    }

    /**
     * Returns backup carrier ID.
     *
     * @return int|null Backup carrier ID if found; otherwise, NULL.
     */
    public function getBackupCarrierId()
    {
        return $this->getConfigValue('backupCarrierId') ?: null;
    }

    /**
     * Sets backup carrier ID.
     *
     * @param int $carrierId ID of the backup carrier.
     */
    public function setBackupCarrierId($carrierId)
    {
        $this->saveConfigValue('backupCarrierId', $carrierId);
    }

    /**
     * Retrieves integration name.
     *
     * @return string Integration name.
     */
    public function getIntegrationName()
    {
        return 'PrestaShop';
    }

    /**
     * Returns order draft source.
     *
     * @return string
     */
    public function getDraftSource()
    {
        return 'module_prestashop';
    }

    /**
     * Gets the current version of the module/integration.
     *
     * @return string The version number.
     */
    public function getModuleVersion()
    {
        if (!$this->moduleVersion) {
            $this->moduleVersion = \Module::getInstanceByName('packlink')->version;
        }

        return $this->moduleVersion;
    }

    /**
     * Gets the name of the integrated e-commerce system.
     * This name is related to Packlink API which can be different from the official system name.
     *
     * @return string The e-commerce name.
     */
    public function getECommerceName()
    {
        return 'prestashop_2';
    }

    /**
     * Gets the current version of the integrated e-commerce system.
     *
     * @return string The version number.
     */
    public function getECommerceVersion()
    {
        return _PS_VERSION_;
    }
}
