<?php

use Packlink\BusinessLogic\Controllers\CashOnDeliveryController as CoreController;
use Packlink\BusinessLogic\Controllers\ShippingMethodController;
use Packlink\BusinessLogic\Http\DTO\CashOnDelivery;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\BusinessLogicServices\OfflinePaymentService;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

if (!defined('_PS_VERSION_')) {
    exit;
}

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

    /**
     * @var ShippingMethodController $shippingMethodController
     */
    protected $shippingMethodController;

    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        $this->controller = new CoreController();
        $this->offlinePaymentService = new OfflinePaymentService();
        $this->shippingMethodController = new ShippingMethodController();

    }

    /**
     * @return void
     */
    public function postProcess()
    {
        parent::postProcess();

        try {
            $input = PacklinkPrestaShopUtility::getPacklinkPostData();

            $acc = $this->getAccountConfiguration();

            $paymentsToHide = array();

            if(empty($input) && empty($input['selectedService'])) {
               $this->sendSuccessResponse($paymentsToHide);
            }

            $services = $this->shippingMethodController->getShippingServicesForMethod($input['selectedService']);

            $config = $services[0]->cashOnDeliveryConfig;

            if($config &&
                !$config->offered
                && $acc !== null
                && $acc->enabled
                && $acc->active
                && $acc->account)
            {
                $paymentsToHide[] = array('name' => $acc->account->getOfflinePaymentMethod());
            }

            PacklinkPrestaShopUtility::dieJson(array(
                'success' => true,
                'data' => $paymentsToHide,
            ));
        } catch (Exception $e) {
            PacklinkPrestaShopUtility::dieJson(array(
                'success' => false,
                'message' => $e->getMessage(),
            ));
        }
    }

    /**
     * @param $data
     *
     * @return void
     */
    public function sendSuccessResponse($data)
    {
        PacklinkPrestaShopUtility::dieJson(array(
            'success' => true,
            'data' => $data,
        ));
    }

    /**
     * Retrieves Packlink account configuration and checks if an account exists.
     *
     * @return CashOnDelivery|null
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    private function getAccountConfiguration()
    {
        return $this->controller->getCashOnDeliveryConfiguration();
    }
}
