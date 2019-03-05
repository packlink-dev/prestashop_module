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

namespace Packlink\PrestaShop\Classes;

require_once _PS_MODULE_DIR_ . '/packlink/vendor/autoload.php';

use Logeecom\Infrastructure\Configuration\ConfigEntity;
use Logeecom\Infrastructure\Http\CurlHttpClient;
use Logeecom\Infrastructure\Http\HttpClient;
use Logeecom\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\Process;
use Logeecom\Infrastructure\TaskExecution\QueueItem;
use Packlink\BusinessLogic\BootstrapComponent;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\Order\Interfaces\OrderRepository as OrderRepositoryInterface;
use Packlink\BusinessLogic\Scheduler\Models\Schedule;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService;
use Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService;
use Packlink\PrestaShop\Classes\Entities\CarrierServiceMapping;
use Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping;
use Packlink\PrestaShop\Classes\Entities\ShopOrderDetails;
use Packlink\PrestaShop\Classes\InfrastructureServices\LoggerService;
use Packlink\PrestaShop\Classes\Repositories\BaseRepository;
use Packlink\PrestaShop\Classes\Repositories\OrderRepository;
use Packlink\PrestaShop\Classes\Repositories\QueueItemRepository;

/**
 * Class Bootstrap
 *
 * @package Packlink\PrestaShop\Classes
 */
class Bootstrap extends BootstrapComponent
{
    /**
     * Initializes infrastructure services and utilities.
     */
    protected static function initServices()
    {
        parent::initServices();

        ServiceRegister::registerService(
            ShopLoggerAdapter::CLASS_NAME,
            function () {
                return LoggerService::getInstance();
            }
        );

        ServiceRegister::registerService(
            Configuration::CLASS_NAME,
            function () {
                return ConfigurationService::getInstance();
            }
        );

        ServiceRegister::registerService(
            ShopShippingMethodService::CLASS_NAME,
            function () {
                return new CarrierService();
            }
        );

        ServiceRegister::registerService(
            HttpClient::CLASS_NAME,
            function () {
                return new CurlHttpClient();
            }
        );

        ServiceRegister::registerService(
            OrderRepositoryInterface::CLASS_NAME,
            function () {
                return new OrderRepository();
            }
        );
    }

    /**
     * Initializes repositories.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryClassException
     */
    protected static function initRepositories()
    {
        RepositoryRegistry::registerRepository(Process::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(ConfigEntity::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(QueueItem::CLASS_NAME, QueueItemRepository::getClassName());
        RepositoryRegistry::registerRepository(ShippingMethod::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(CarrierServiceMapping::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(ShopOrderDetails::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(Schedule::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(
            CartCarrierDropOffMapping::getClassName(),
            BaseRepository::getClassName()
        );
    }
}
