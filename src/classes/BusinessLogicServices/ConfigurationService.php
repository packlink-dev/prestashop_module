<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Logeecom\Infrastructure\Logger\Logger;
use Packlink\BusinessLogic\Configuration;

class ConfigurationService extends Configuration
{
    /**
     * Threshold between two runs of scheduler.
     */
    const SCHEDULER_TIME_THRESHOLD = 150;
    /**
     * @inheritdoc
     */
    const MIN_LOG_LEVEL = Logger::ERROR;
    /**
     * Max inactivity period for a task in seconds
     */
    const MAX_TASK_INACTIVITY_PERIOD = 60;
    /**
     * Default HTTP method to use for async call.
     */
    const ASYNC_CALL_METHOD = 'GET';
    /**
     * Singleton instance of this class.
     *
     * @var static
     */
    protected static $instance;
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
     * Gets max inactivity period for a task in seconds.
     * After inactivity period is passed, system will fail such task as expired.
     *
     * @return int Max task inactivity period in seconds if set; otherwise, self::MAX_TASK_INACTIVITY_PERIOD.
     */
    public function getMaxTaskInactivityPeriod()
    {
        return parent::getMaxTaskInactivityPeriod() ?: self::MAX_TASK_INACTIVITY_PERIOD;
    }

    /**
     * Returns web-hook callback URL for current system.
     *
     * @return string Web-hook callback URL.
     */
    public function getWebHookUrl()
    {
        return $this->getFrontendUrl('webhooks');
    }

    /**
     * Returns status update web-hook callback URL for current system.
     *
     * @return string Status Update Web-hook callback URL.
     */
    public function getStatusUpdateUrl()
    {
        return $this->getFrontendUrl('registrationwebhooks');
    }

    /**
     * Returns async process starter URL.
     *
     * @param string $guid Process identifier.
     *
     * @return string Formatted URL of async process starter endpoint.
     */
    public function getAsyncProcessUrl($guid)
    {
        $params = array('guid' => $guid);
        if ($this->isAutoTestMode()) {
            $params['auto-test'] = 1;
        }

        return $this->getFrontendUrl('asyncprocess', $params);
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
     * Sets Integration identifier.
     *
     * @param string $integrationId ID of the integration.
     *
     * @return \Logeecom\Infrastructure\Configuration\ConfigEntity
     */
    public function setIntegrationId($integrationId)
    {
        return parent::setIntegrationId($integrationId);
    }

    /**
     * Retrieves Integration identifier.
     *
     * @return string | null  IntegrationId configuration.
     */
    public function getIntegrationId()
    {
        return parent::getIntegrationId();
    }

    /**
     * Sets backup carrier ID.
     *
     * @param string $webhookSecret
     *
     * @return \Logeecom\Infrastructure\Configuration\ConfigEntity
     */
    public function setWebhookSecret($webhookSecret)
    {
        return parent::setWebhookSecret($webhookSecret);
    }

    /**
     * Retrieves Webhook secret.
     *
     * @return string | null  Webhook Secret configuration.
     */
    public function getWebhookSecret()
    {
        return parent::getWebhookSecret();
    }

    /**
     * Sets Integration Guid.
     *
     * @param string $webhookSecret
     *
     * @return \Logeecom\Infrastructure\Configuration\ConfigEntity
     */
    public function setIntegrationGuid($integrationGuid)
    {
        return parent::setIntegrationGuid($integrationGuid);
    }

    /**
     * Retrieves Integration Guid.
     *
     * @return string | null  Integration Guid configuration.
     */
    public function getIntegrationGuid()
    {
        return parent::getIntegrationGuid();
    }

    /**
     * Removes integration registration data from the database
     * by nulling out all integration-related configuration values.
     *
     * @return void
     */
    public function deleteIntegrationData() //TODO: test when packlink unblocks issue
    {
        $this->saveConfigValue('integrationId', null);
        $this->saveConfigValue('integrationGuid', null);
        $this->saveConfigValue('webhookSecret', null);
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

    /**
     * Retrieves integration status.
     *
     * @return string|null
     */
    public function getIntegrationStatus()
    {
        return parent::getIntegrationStatus();
    }

    /**
     * Sets integration status.
     *
     * @param string $status
     *
     * @return \Logeecom\Infrastructure\Configuration\ConfigEntity
     */
    public function setIntegrationStatus($status)
    {
        return parent::setIntegrationStatus($status);
    }

    /**
     * Returns whether the integration is currently active.
     * Integration is considered active unless explicitly set to DISABLED.
     *
     * @return bool
     */
    public function isIntegrationActive()
    {
        return parent::isIntegrationActive();
    }

    /**
     * Gets the URL of the frontend controller.
     *
     * @param string $controller Controller name.
     * @param array $params Route parameters.
     *
     * @return string
     */
    private function getFrontendUrl($controller, $params = array())
    {
        $shopId = \Configuration::get('PS_SHOP_DEFAULT');

        return \Context::getContext()->link->getModuleLink('packlink', $controller, $params, null, null, $shopId);
    }
}
