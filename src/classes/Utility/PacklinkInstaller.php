<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\Exceptions\TaskRunnerStatusStorageUnavailableException;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\BusinessLogic\ShippingMethod\Utility\ShipmentStatus;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService;
use Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService;
use Packlink\PrestaShop\Classes\Repositories\BaseRepository;
use Packlink\PrestaShop\Classes\Repositories\OrderRepository;
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
        'updateCarrier',
        'displayAfterCarrier',
        'actionValidateStepComplete',
        'displayBackOfficeOrderActions',
        'displayAdminOrderTabShip',
        'displayAdminOrderContentShip',
        'displayBeforeCarrier',
        'displayOrderConfirmation',
        'actionValidateOrder',
        'actionOrderStatusUpdate',
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
        if (!$this->createBaseTable() || !$this->extendOrdersTable()) {
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

        $this->removeOrdersColumn();

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
     * Adds Packlink menu item to shipping tab group.
     *
     * @return bool Returns TRUE if tab has been successfully added, otherwise returns FALSE.
     *
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function addMenuItem()
    {
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
        $result = true;
        foreach (self::$hooks as $hook) {
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
     * Adds packlink column to orders table.
     *
     * @return bool
     */
    private function extendOrdersTable()
    {
        $columnName = pSQL(OrderRepository::PACKLINK_ORDER_DRAFT_FIELD);
        $tableName = _DB_PREFIX_ . 'orders';
        $checkColumnSqlStatement = 'SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = \'' . _DB_NAME_
            . '\' AND TABLE_NAME = \'' . pSQL($tableName)
            . '\' AND COLUMN_NAME = \'' . pSQL($columnName) . '\'';
        try {
            $result = \Db::getInstance()->executeS($checkColumnSqlStatement);
        } catch (\PrestaShopException $e) {
            Logger::logError('Error getting schema information. Error: ' . $e->getMessage(), 'Integration');

            return false;
        }

        if (is_array($result) && count($result) === 0) {
            $alterTableSqlStatement = 'ALTER TABLE ' . bqSQL($tableName)
                . ' ADD ' . bqSQL($columnName) . ' VARCHAR(100) DEFAULT NULL';
            try {
                return (bool)\Db::getInstance()->execute($alterTableSqlStatement);
            } catch (\PrestaShopException $e) {
                Logger::logError('Error extending orders table. Error: ' . $e->getMessage(), 'Integration');

                return false;
            }
        }

        return true;
    }

    /**
     * Removes Orders table extended column.
     *
     * @return bool
     */
    private function removeOrdersColumn()
    {
        try {
            $sql = 'ALTER TABLE ' . bqSQL(_DB_PREFIX_ . 'orders')
                . ' DROP COLUMN ' . bqSQL(OrderRepository::PACKLINK_ORDER_DRAFT_FIELD);

            \Db::getInstance()->execute($sql);
        } catch (\PrestaShopException $e) {
            $this->tryLogError('Error removing orders table column. Error: ' . $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Initialize default configuration values that plugin needs.
     *
     * @return bool
     */
    private function addDefaultPluginConfiguration()
    {
        try {
            /** @var ConfigurationService $configService */
            $configService = ServiceRegister::getService(\Packlink\BusinessLogic\Configuration::CLASS_NAME);
            $configService->setTaskRunnerStatus('', null);
        } catch (TaskRunnerStatusStorageUnavailableException $e) {
            Logger::logError(
                $this->module->l('Error creating default task runner status configuration.'),
                'Integration'
            );

            return false;
        }

        return true;
    }

    /**
     * Registers module hooks.
     *
     * @return bool
     */
    private function addHooks()
    {
        $result = true;
        foreach (self::$hooks as $hook) {
            $result = $result && $this->module->registerHook($hook);
        }

        return $result;
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

    /**
     * Registers controller.
     *
     * @param string $name Controller name.
     * @param int $parentId Id of parent controller.
     *
     * @return bool
     *
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    private function addController($name, $parentId = -1)
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
     * Removes controller.
     *
     * @param string $name Name of the controller.
     *
     * @return bool
     *
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    private function removeController($name)
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
