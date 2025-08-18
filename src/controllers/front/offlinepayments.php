<?php

use Packlink\BusinessLogic\Controllers\CashOnDeliveryController as CoreController;
use Packlink\BusinessLogic\Http\DTO\CashOnDelivery;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService;
use Packlink\PrestaShop\Classes\BusinessLogicServices\OfflinePaymentService;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

class PacklinkOfflinePaymentsModuleFrontController extends ModuleFrontController
{
    /**
     * @var OfflinePaymentService
     */
    protected $offlinePaymentService;
    /**
     * @var CoreController $controller
     */
    protected $controller;

    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        $this->offlinePaymentService = new OfflinePaymentService();
        $this->controller = new CoreController();
    }

    /**
     * @return void
     */
    public function initContent(): void
    {
        parent::initContent();

        try {
            $payments = $this->offlinePaymentService->getOfflinePayments();
            $acc = $this->getAccountConfiguration();

            PacklinkPrestaShopUtility::dieJson(array(
                'success' => true,
                'data' => $payments,
            ));
        } catch (Exception $e) {
            PacklinkPrestaShopUtility::dieJson(array(
                'success' => false,
                'message' => $e->getMessage(),
            ));
        }
    }

    /**
     * Retrieves Packlink account configuration and checks if an account exists.
     *
     * @return CashOnDelivery
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Packlink\BusinessLogic\Http\CashOnDelivery\Exeption\CashOnDeliveryNotFoundException
     */
    private function getAccountConfiguration()
    {
        return $this->controller->getCashOnDeliveryConfiguration(ConfigurationService::getInstance()
            ->getCurrentSystemId());
    }
}
