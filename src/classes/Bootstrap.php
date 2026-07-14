<?php

namespace Packlink\PrestaShop\Classes;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

use Logeecom\Infrastructure\Configuration\ConfigEntity;
use Logeecom\Infrastructure\Http\CurlHttpClient;
use Logeecom\Infrastructure\Http\HttpClient;
use Logeecom\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use Logeecom\Infrastructure\Logger\LogData;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\Serializer\Concrete\NativeSerializer;
use Logeecom\Infrastructure\Serializer\Serializer;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\DefaultTaskMetadataProvider;
use Logeecom\Infrastructure\TaskExecution\Interfaces\AsyncProcessUrlProviderInterface;
use Logeecom\Infrastructure\TaskExecution\Interfaces\TaskRunnerConfigInterface;
use Logeecom\Infrastructure\TaskExecution\Process;
use Logeecom\Infrastructure\TaskExecution\QueueItem;
use Logeecom\Infrastructure\TaskExecution\Scheduler\Models\Schedule;
use Logeecom\Infrastructure\TaskExecution\TaskExecutionBootstrap;
use Packlink\Brands\Packlink\PacklinkConfigurationService;
use Packlink\BusinessLogic\BootstrapComponent;
use Packlink\BusinessLogic\Brand\BrandConfigurationService;
use Packlink\BusinessLogic\CashOnDelivery\Model\CashOnDelivery;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\Country\Interfaces\CountryServiceInterface;
use Packlink\BusinessLogic\Country\WarehouseCountryService;
use Packlink\BusinessLogic\FileResolver\FileResolverService;
use Packlink\BusinessLogic\IntegrationRegistration\Interfaces\IntegrationRegistrationDataProviderInterface;
use Packlink\BusinessLogic\IntegrationRegistration\Interfaces\ModuleResetServiceInterface;
use Packlink\BusinessLogic\Order\Interfaces\ShopOrderService as ShopOrderServiceInterface;
use Packlink\BusinessLogic\OrderShipmentDetails\Models\OrderShipmentDetails;
use Packlink\BusinessLogic\ShipmentDraft\Models\OrderSendDraftTaskMap;
use Packlink\BusinessLogic\Tasks\Interfaces\TaskMetadataProviderInterface;
use Packlink\BusinessLogic\UpdateShippingServices\Models\UpdateShippingServiceTaskStatus;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService;
use Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService;
use Packlink\PrestaShop\Classes\BusinessLogicServices\IntegrationRegistrationDataProvider;
use Packlink\PrestaShop\Classes\BusinessLogicServices\ModuleResetService;
use Packlink\PrestaShop\Classes\BusinessLogicServices\RegistrationInfoService;
use Packlink\PrestaShop\Classes\BusinessLogicServices\ShopOrderService;
use Packlink\PrestaShop\Classes\BusinessLogicServices\SystemInfoService;
use Packlink\PrestaShop\Classes\Entities\CarrierServiceMapping;
use Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping;
use Packlink\PrestaShop\Classes\InfrastructureServices\LoggerService;
use Packlink\PrestaShop\Classes\Repositories\BaseRepository;
use Packlink\PrestaShop\Classes\Repositories\QueueItemRepository;
use Packlink\BusinessLogic\Registration\RegistrationInfoService as RegistrationInfoServiceInterface;
use Packlink\BusinessLogic\SystemInformation\SystemInfoService as SystemInfoInterface;

/**
 * Class Bootstrap
 *
 * @package Packlink\PrestaShop\Classes
 */
class Bootstrap extends BootstrapComponent
{
    /**
     * Initializes infrastructure components.
     *
     * Under core V2 the task-execution stack (queue, task runner, scheduler) is no longer
     * registered by the business-logic bootstrap; PrestaShop runs in Standalone / TaskRunner mode,
     * so the platform must register that stack explicitly.
     */
    public static function init()
    {
        parent::init();

        TaskExecutionBootstrap::init();
    }

    /**
     * Initializes infrastructure services and utilities.
     */
    protected static function initServices()
    {
        ServiceRegister::registerService(
            Configuration::CLASS_NAME,
            function () {
                return ConfigurationService::getInstance();
            }
        );

        ServiceRegister::registerService(
            IntegrationRegistrationDataProviderInterface::CLASS_NAME,
            function () {
                return new IntegrationRegistrationDataProvider(
                    ServiceRegister::getService(\Logeecom\Infrastructure\Configuration\Configuration::CLASS_NAME)
                );
            }
        );

        ServiceRegister::registerService(
            ModuleResetServiceInterface::CLASS_NAME,
            function () {
                return new ModuleResetService(
                    ServiceRegister::getService(IntegrationRegistrationDataProviderInterface::CLASS_NAME)
                );
            }
        );

        parent::initServices();

        ServiceRegister::registerService(
            Serializer::CLASS_NAME,
            function () {
                return new NativeSerializer();
            }
        );

        ServiceRegister::registerService(
            ShopLoggerAdapter::CLASS_NAME,
            function () {
                return LoggerService::getInstance();
            }
        );

        ServiceRegister::registerService(
            BrandConfigurationService::CLASS_NAME,
            function () {
                return new PacklinkConfigurationService();
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
            ShopOrderServiceInterface::CLASS_NAME,
            function () {
                return new ShopOrderService();
            }
        );

        ServiceRegister::registerService(
            RegistrationInfoServiceInterface::CLASS_NAME,
            function () {
                return new RegistrationInfoService();
            }
        );

        ServiceRegister::registerService(
            SystemInfoInterface::CLASS_NAME,
            function () {
                return new SystemInfoService();
            }
        );

        ServiceRegister::registerService(
            FileResolverService::CLASS_NAME,
            function () {
                return new FileResolverService(array(
                    dirname(__FILE__) . '/../views/brand/countries',
                    dirname(__FILE__) . '/../views/countries',
                ));
            }
        );

        ServiceRegister::registerService(
            WarehouseCountryService::CLASS_NAME,
            function () {
                return BusinessLogicServices\WarehouseCountryService::getInstance();
            }
        );

        // Core V2's WarehouseController type-hints CountryServiceInterface. The core registers
        // WarehouseServiceInterface but not this one, so the platform country service (which is a
        // CountryServiceInterface) is wired here so the controller's dependency resolves.
        ServiceRegister::registerService(
            CountryServiceInterface::class,
            function () {
                return BusinessLogicServices\WarehouseCountryService::getInstance();
            }
        );

        // Core V2 resolves the async-process endpoint through this contract (Standalone mode).
        ServiceRegister::registerService(
            AsyncProcessUrlProviderInterface::CLASS_NAME,
            function () {
                return new BusinessLogicServices\AsyncProcessUrlProvider();
            }
        );

        // Core V2 resolves each task's queue name / priority / context through this provider.
        ServiceRegister::registerService(
            TaskMetadataProviderInterface::CLASS_NAME,
            function () {
                /** @var Configuration $config */
                $config = ServiceRegister::getService(Configuration::CLASS_NAME);
                /** @var TaskRunnerConfigInterface $taskRunnerConfig */
                $taskRunnerConfig = ServiceRegister::getService(TaskRunnerConfigInterface::CLASS_NAME);

                return new DefaultTaskMetadataProvider($config, $taskRunnerConfig);
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
        RepositoryRegistry::registerRepository(CashOnDelivery::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(CarrierServiceMapping::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(OrderShipmentDetails::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(Schedule::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(
            CartCarrierDropOffMapping::getClassName(),
            BaseRepository::getClassName()
        );
        RepositoryRegistry::registerRepository(LogData::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(OrderSendDraftTaskMap::CLASS_NAME, BaseRepository::getClassName());
        RepositoryRegistry::registerRepository(
            UpdateShippingServiceTaskStatus::CLASS_NAME,
            BaseRepository::getClassName()
        );
    }
}
