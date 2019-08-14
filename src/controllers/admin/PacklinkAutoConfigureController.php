<?php

use Logeecom\Infrastructure\Http\AutoConfiguration;
use Logeecom\Infrastructure\Http\HttpClient;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\QueueService;
use Packlink\BusinessLogic\Configuration as ConfigurationInterface;
use Packlink\BusinessLogic\Tasks\UpdateShippingServicesTask;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class PacklinkAutoConfigureController.
 */
class PacklinkAutoConfigureController extends ModuleAdminController
{
    /**
     * PacklinkAutoConfigureController constructor.
     *
     * @throws \PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        $this->bootstrap = true;
    }

    /**
     * Starts the auto-configuration.
     */
    public function initContent()
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(ConfigurationInterface::CLASS_NAME);
        /** @var \Logeecom\Infrastructure\Http\HttpClient $httpService */
        $httpService = ServiceRegister::getService(HttpClient::CLASS_NAME);
        $service = new AutoConfiguration($configService, $httpService);

        try {
            $success = $service->start();
            if ($success) {
                // enqueue the task for updating shipping services
                /** @var QueueService $queueService */
                $queueService = ServiceRegister::getService(QueueService::CLASS_NAME);
                $queueService->enqueue($configService->getDefaultQueueName(), new UpdateShippingServicesTask());
            }

            PacklinkPrestaShopUtility::dieJson(array('success' => $success));
        } catch (\Logeecom\Infrastructure\Exceptions\BaseException $e) {
            PacklinkPrestaShopUtility::dieJson(
                array(
                    'success' => false,
                    'error' => TranslationUtility::__('Auto-configuration could not be completed successfully.'),
                )
            );
        }
    }
}
