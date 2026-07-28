<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\Exceptions\TaskRunnerStatusStorageUnavailableException;
use Packlink\BusinessLogic\IntegrationRegistration\Interfaces\IntegrationRegistrationServiceInterface;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService;
use Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CustomsMappingService;
use Packlink\PrestaShop\Classes\Repositories\BaseRepository;
use Tools;

/**
 * Class PacklinkInstaller.
 *
 * @package Packlink\PrestaShop\Classes\Utility
 */
class PacklinkInstaller
{
    /**
     * Packlink module instance.
     *
     * @var \Packlink
     */
    private $module;
    private static $hooks = array(
        'displayAfterCarrier',
        'displayAdminOrderTabShip',
        'displayAdminOrderContentShip',
        'displayBeforeCarrier',
        'displayOrderConfirmation',
        'actionValidateOrder',
        'actionOrderStatusUpdate',
        'displayOrderDetail',
        'displayAdminProductsExtra',
        // Places the customs fields in the product Shipping tab on the legacy product form; never
        // called on the new product page (8.1+), where displayAdminProductsExtra is used instead.
        'displayAdminProductsShippingStepBottom',
        'actionProductUpdate',
        'actionCustomerFormBuilderModifier',
        'actionAfterCreateCustomerFormHandler',
        'actionAfterUpdateCustomerFormHandler',
    );
    private static $controllers = array(
        'Debug',
        'Dashboard',
        'DefaultParcel',
        'DefaultWarehouse',
        'ShippingMethods',
        'OrderStateMapping',
        'ShipmentLabels',
        'BulkShipmentLabels',
        'OrderDraft',
        'PacklinkAutoTest',
        'PacklinkAutoConfigure',
        'Configuration',
        'Login',
        'ModuleState',
        'Onboarding',
        'Registration',
        'RegistrationRegions',
        'ShippingZones',
        'SystemInfo',
        'ManualRefreshService',
        'CashOnDelivery',
        'Subscription',
        'ShipmentDocuments',
        'Customs',
    );

    /**
     * PacklinkInstaller constructor.
     *
     * @param \Packlink $module
     */
    public function __construct(\Packlink $module)
    {
        $this->module = $module;
    }

    /**
     * Initializes plugin.
     *
     * @return bool
     */
    public function initializePlugin()
    {
        Bootstrap::init();
        if (!$this->createBaseTable()) {
            return false;
        }

        return $this->addShopConfiguration();
    }

    /**
     * Detects whether other overrides of the order code exist.
     *
     * @return bool TRUE if overrides can be safely applied; otherwise, FALSE.
     */
    public function shouldInstallOverrides()
    {
        return $this->canPacklinkAddOverride(_PS_ROOT_DIR_ . '/override/controllers/admin/AdminOrdersController.php')
            && $this->canPacklinkAddOverride(_PS_ROOT_DIR_ . '/override/classes/order/Order.php');
    }

    /**
     * Removes overrides from the previous module versions so we don't leave unused code.
     */
    public function removeOldOverrides()
    {
        $path = $this->module->getLocalPath() . 'override/controllers/admin/AdminOrdersController.php';
        $oldFile = Tools::file_get_contents($path);
        $startPos = Tools::strpos($oldFile, '/** OLD PART START */');
        $endPos = Tools::strpos($oldFile, '/** OLD PART END */');
        if ($startPos !== false && $endPos !== false) {
            $newFile = Tools::substr($oldFile, 0, $startPos - 4) . Tools::substr($oldFile, $endPos + 20);
            file_put_contents($path, $newFile);
        }
    }

    /**
     * Performs actions when module is being uninstalled.
     *
     * @return bool Result of method execution.
     *
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function uninstall()
    {
        Bootstrap::init();

        /** @var IntegrationRegistrationServiceInterface $integrationService */
        $integrationService = ServiceRegister::getService(IntegrationRegistrationServiceInterface::CLASS_NAME);
        $integrationService->disconnectIntegration();

        try {
            /** @var CarrierService $carrierService */
            $carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);
            $carrierService->deletePacklinkCarriers();
        } catch (\Exception $exception) {
        }

        $this->removeControllers();

        // remove menu item
        $this->removeController('Packlink');

        $this->dropBaseTable();

        $this->deleteLogs();

        // Make sure that deleted configuration is reflected into cached values as well.
        \Configuration::loadConfiguration();

        return true;
    }

    /**
     * Adds controllers and hooks.
     *
     * @return bool Result of method execution.
     *
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function addControllersAndHooks()
    {
        return $this->addMenuItem() && $this->addHooks() && $this->addControllers();
    }

    /**
     * Registers additional hooks for versions 1.7.7 and above.
     *
     * @return bool
     */
    public function updateHooks()
    {
        $result = true;

        foreach ($this->getAdditionalHooks() as $hook) {
            $result = $result && $this->module->registerHook($hook);
        }

        return $result;
    }

    /**
     * Adds Packlink menu item to shipping tab group.
     *
     * @return bool Returns TRUE if tab has been successfully added, otherwise returns FALSE.
     *
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function addMenuItem()
    {
        /** @noinspection PhpDeprecationInspection */
        $id = \Tab::getIdFromClassName('Packlink');

        if ($id) {
            return true;
        }

        $tab = new \Tab();

        $languages = \Language::getLanguages(true, \Context::getContext()->shop->id);
        foreach ($languages as $language) {
            $tab->name[$language['id_lang']] = 'Packlink PRO';
        }

        $tab->class_name = 'Packlink';
        /** @noinspection PhpDeprecationInspection Exists in PS1.6 */
        $tab->id_parent = (int)\Tab::getIdFromClassName('AdminParentShipping');
        $tab->module = $this->module->name;

        return $tab->add();
    }

    /**
     * Unregisters module hooks.
     *
     * @return bool
     */
    public function removeHooks()
    {
        $hooks = self::$hooks;
        $hooks = array_merge($hooks, $this->getAdditionalHooks());

        $result = true;
        foreach ($hooks as $hook) {
            $result = $result && $this->module->unregisterHook($hook);
        }

        return $result;
    }

    /**
     * Unregisters module controllers.
     *
     * @return bool
     */
    public function removeControllers()
    {
        $result = true;
        try {
            $tabs = \Tab::getCollectionFromModule($this->module->name);
            if ($tabs && count($tabs)) {
                foreach ($tabs as $tab) {
                    $tab->delete();
                }
            }
        } catch (\PrestaShopException $e) {
            $this->tryLogError('Error removing controller! Error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Adds configuration for current shop.
     *
     * @return bool
     */
    public function addShopConfiguration()
    {
        $this->addDefaultStatusMapping();

        return $this->addDefaultPluginConfiguration();
    }

    /**
     * Registers a controller.
     *
     * @param string $name Controller name.
     * @param int $parentId Id of parent controller.
     *
     * @return bool
     *
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function addController($name, $parentId = -1)
    {
        try {
            $tab = new \Tab();
            $tab->active = 1;
            $tab->name[(int)\Configuration::get('PS_LANG_DEFAULT')] = $this->module->l('Packlink');
            $tab->class_name = $name;
            $tab->module = $this->module->name;
            $tab->id_parent = $parentId;
            $tab->add();

            return true;
        } catch (\PrestaShopException $e) {
            Logger::logWarning(
                'Failed to register controller "' . $name . '". Error: ' . $e->getMessage(),
                'Integration'
            );
        }

        return false;
    }

    /**
     * Removes a controller.
     *
     * @param string $name Name of the controller.
     *
     * @return bool
     *
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function removeController($name)
    {
        try {
            /** @noinspection PhpDeprecationInspection Because it exists in PS1.6 */
            $tab = new \Tab((int)\Tab::getIdFromClassName($name));
            if ($tab) {
                $tab->delete();
            }
        } catch (\PrestaShopException $e) {
            $this->tryLogError('Error removing controller "' . $name . '". Error: ' . $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Creates Packlink entity table.
     *
     * @return bool Result of create table query.
     */
    private function createBaseTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS '
            . bqSQL(_DB_PREFIX_ . BaseRepository::TABLE_NAME)
            . '(
                 `id` INT NOT NULL AUTO_INCREMENT,
                 `type` VARCHAR(128) NOT NULL,
                 `index_1` VARCHAR(255),
                 `index_2` VARCHAR(255),
                 `index_3` VARCHAR(255),
                 `index_4` VARCHAR(255),
                 `index_5` VARCHAR(255),
                 `index_6` VARCHAR(255),
                 `index_7` VARCHAR(255),
                 `index_8` VARCHAR(255),
                 `data` LONGTEXT NOT NULL,
                 PRIMARY KEY(`id`)
            )
            ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        try {
            return \Db::getInstance()->execute($sql);
        } catch (\PrestaShopException $e) {
            Logger::logError('Error creating base database table. Error: ' . $e->getMessage(), 'Integration');
        }

        return false;
    }

    /**
     * Adds additional index column to the Packlink entity table.
     *
     * @return bool Result of create table query.
     */
    public function addAdditionalIndex()
    {
        $sql = 'ALTER TABLE '
            . bqSQL(_DB_PREFIX_ . BaseRepository::TABLE_NAME)
            . ' ADD `index_8` VARCHAR(255)';

        try {
            return \Db::getInstance()->execute($sql);
        } catch (\PrestaShopException $e) {
            Logger::logError('Error adding additional index column. Error: ' . $e->getMessage(), 'Integration');
        }

        return false;
    }

    /**
     * Drops base database table.
     *
     * @return bool
     */
    private function dropBaseTable()
    {
        $script = 'DROP TABLE IF EXISTS ' . bqSQL(_DB_PREFIX_ . BaseRepository::TABLE_NAME);

        try {
            return (bool)\Db::getInstance()->execute($script);
        } catch (\PrestaShopException $e) {
            $this->tryLogError('Error dropping base database table. Error: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Deletes all packlink logs.
     *
     * @return bool
     */
    private function deleteLogs()
    {
        $script = 'DELETE FROM ' . _DB_PREFIX_ . 'log WHERE `message` LIKE \'' . pSQL('%PACKLINK LOG%') . '\'';

        try {
            return (bool)\Db::getInstance()->execute($script);
        } catch (\PrestaShopException $e) {
            $this->tryLogError('Error deleting packlink logs. Error: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Initialize default configuration values that plugin needs.
     *
     * @return bool
     */
    private function addDefaultPluginConfiguration()
    {
        try {
            // Task-runner status lives on core V2's TaskRunnerConfig, not the Configuration service.
            /** @var \Logeecom\Infrastructure\TaskExecution\Interfaces\TaskRunnerConfigInterface $taskRunnerConfig */
            $taskRunnerConfig = ServiceRegister::getService(
                \Logeecom\Infrastructure\TaskExecution\Interfaces\TaskRunnerConfigInterface::CLASS_NAME
            );
            $taskRunnerConfig->setTaskRunnerStatus('', null);
        } catch (TaskRunnerStatusStorageUnavailableException $e) {
            Logger::logError(
                $this->module->l('Error creating default task runner status configuration.'),
                'Integration'
            );

            return false;
        }

        $this->addDefaultCustomsMapping();

        return true;
    }

    /**
     * Seeds a default customs mapping so international shipments build a customs invoice out of the
     * box. Guarded: never overwrites an existing merchant-configured mapping. The
     * default tariff number is a valid 6-8 digit placeholder required by core validation.
     *
     * @return void
     */
    public function addDefaultCustomsMapping()
    {
        try {
            /** @var ConfigurationService $configService */
            $configService = ServiceRegister::getService(\Packlink\BusinessLogic\Configuration::CLASS_NAME);

            if ($configService->getCustomsMappings()) {
                return;
            }

            $mapping = new \Packlink\BusinessLogic\Customs\Models\CustomsMapping();
            $mapping->defaultReason = 'purchase_or_sale';
            $mapping->defaultSenderTaxId = '';
            $mapping->defaultReceiverUserType = \Packlink\BusinessLogic\Customs\CustomsService::PRIVATE_PERSON;
            $mapping->defaultReceiverTaxId = '';
            $mapping->defaultTariffNumber = '61091000';
            $mapping->defaultCountry = '';
            // Default data-mapping sources; match CustomsMappingService::SOURCE_* and preserve the
            // module's prior behaviour (receiver tax id from the customer Tax ID field, company VAT
            // from the address, tariff number from the product HS code). The two product fields
            // default to the fields this module adds to the product Shipping tab, so customs works
            // out of the box before the merchant maps anything of their own.
            $mapping->mappingReceiverTaxId = CustomsMappingService::SOURCE_CUSTOMER_TAX_ID;
            $mapping->mappingCompanyVat = CustomsMappingService::SOURCE_ADDRESS_VAT;
            $mapping->mappingTariffNumber = CustomsMappingService::SOURCE_PRODUCT_HS_CODE;
            $mapping->mappingCountryOfOrigin = CustomsMappingService::SOURCE_PRODUCT_COUNTRY_OF_ORIGIN;

            $configService->setCustomsMappings($mapping);
        } catch (\Exception $e) {
            Logger::logWarning(
                'Failed to seed default customs mapping: ' . $e->getMessage(),
                'Integration'
            );
        }
    }

    /**
     * Fills any data-mapping selection that is still empty with the module's own product/customer
     * fields.
     *
     * addDefaultCustomsMapping() only seeds a store that has no customs mapping at all, so a store
     * configured before a mapping row existed keeps that row empty - which the settings page shows as
     * the blank "not mapped" option, and which makes the order build fall back to the customs
     * defaults instead of the product's own data. Never overwrites a selection the merchant made.
     *
     * @return void
     */
    public function backfillCustomsMappingSources()
    {
        try {
            /** @var ConfigurationService $configService */
            $configService = ServiceRegister::getService(\Packlink\BusinessLogic\Configuration::CLASS_NAME);

            $mapping = $configService->getCustomsMappings();
            if (!$mapping) {
                // Nothing configured yet: the fresh-install seeding path already covers this.
                $this->addDefaultCustomsMapping();

                return;
            }

            $changed = false;

            // The country-of-origin row used to be stored by the module, before core's CustomsMapping
            // declared mapping_country_of_origin. Carry a merchant's existing selection over once, so
            // upgrading does not silently reset it to the default.
            $legacySource = $this->getLegacyCountryOfOriginSource($configService);
            if ($legacySource !== '' && empty($mapping->mappingCountryOfOrigin)) {
                $mapping->mappingCountryOfOrigin = $legacySource;
                $changed = true;
            }

            $defaults = array(
                'mappingTariffNumber' => CustomsMappingService::SOURCE_PRODUCT_HS_CODE,
                'mappingReceiverTaxId' => CustomsMappingService::SOURCE_CUSTOMER_TAX_ID,
                'mappingCompanyVat' => CustomsMappingService::SOURCE_ADDRESS_VAT,
                'mappingCountryOfOrigin' => CustomsMappingService::SOURCE_PRODUCT_COUNTRY_OF_ORIGIN,
            );

            foreach ($defaults as $property => $default) {
                if (property_exists($mapping, $property) && empty($mapping->{$property})) {
                    $mapping->{$property} = $default;
                    $changed = true;
                }
            }

            if ($changed) {
                $configService->setCustomsMappings($mapping);
            }
        } catch (\Exception $e) {
            Logger::logWarning(
                'Failed to seed default customs mapping: ' . $e->getMessage(),
                'Integration'
            );
        }
    }

    /**
     * Returns the country-of-origin source stored by older module versions, or an empty string.
     *
     * Kept only as the migration source for backfillCustomsMappingSources(); the mapping now lives in
     * core's CustomsMapping.
     *
     * @param ConfigurationService $configService
     *
     * @return string
     */
    private function getLegacyCountryOfOriginSource(ConfigurationService $configService)
    {
        try {
            $legacy = $configService->getCustomsMappingExtras();
        } catch (\Exception $e) {
            return '';
        }

        return isset($legacy['mapping_country_of_origin']) ? (string)$legacy['mapping_country_of_origin'] : '';
    }

    /**
     * Registers module hooks.
     *
     * @return bool
     */
    private function addHooks()
    {
        $hooks = self::$hooks;
        $hooks = array_merge($hooks, $this->getAdditionalHooks());

        $result = true;

        foreach ($hooks as $hook) {
            $result = $result && $this->module->registerHook($hook);
        }

        return $result;
    }

    /**
     * Returns the list of hooks used for PrestaShop versions 1.7.7 and above.
     *
     * @return array
     */
    private function getAdditionalHooks()
    {
        return array(
            'actionAdminControllerSetMedia',
            'actionOrderGridDefinitionModifier',
            'actionOrderGridPresenterModifier',
            'displayAdminOrderTabLink',
            'displayAdminOrderTabContent',
            'actionObjectShopUrlUpdateAfter',
        );
    }

    /**
     * Registers module controllers.
     *
     * @return bool
     *
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    private function addControllers()
    {
        $result = true;
        foreach (self::$controllers as $controller) {
            $result = $result && $this->addController($controller);
        }

        return $result;
    }

    private function addDefaultStatusMapping()
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(\Packlink\BusinessLogic\Configuration::CLASS_NAME);
        $mappings = $configService->getOrderStatusMappings();

        if (empty($mappings)) {
            $configService->setOrderStatusMappings(
                array(
                    ShipmentStatus::STATUS_PENDING => '',
                    ShipmentStatus::STATUS_ACCEPTED => 3,
                    ShipmentStatus::STATUS_READY => 3,
                    ShipmentStatus::STATUS_IN_TRANSIT => 4,
                    ShipmentStatus::STATUS_DELIVERED => 5,
                    ShipmentStatus::STATUS_CANCELLED => 6,
                    ShipmentStatus::OUT_FOR_DELIVERY => 4,
                )
            );
        }
    }

    /**
     * Tries to log the error.
     *
     * @param string $message
     */
    private function tryLogError($message)
    {
        try {
            Logger::logError($message, 'Integration');
        } catch (\Exception $exception) {
        }
    }

    /**
     * Checks if we can safely add our overrides.
     *
     * @param string $overriddenFilePath
     *
     * @return bool
     */
    private function canPacklinkAddOverride($overriddenFilePath)
    {
        $content = Tools::file_get_contents($overriddenFilePath);

        return $content === false || preg_match('/function __construct/', $content) === 0;
    }
}
