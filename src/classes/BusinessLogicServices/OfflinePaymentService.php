<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Address;
use Cart;
use Country;
use Logeecom\Infrastructure\ServiceRegister;
use Module;
use Packlink\BusinessLogic\CashOnDelivery\Services\OfflinePaymentsServices;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\Controllers\ShippingMethodController;
use Packlink\BusinessLogic\Http\DTO\CashOnDelivery;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingService;
use Packlink\PrestaShop\Classes\Bootstrap;
use PaymentModule;
use Validate;
use Packlink\BusinessLogic\Controllers\CashOnDeliveryController as CoreController;


/**
 * Class OfflinePaymentService
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class OfflinePaymentService extends OfflinePaymentsServices
{
    /**
     * @var CoreController $controller
     */
    protected $controller;

    /**
     * @var ShippingMethodController $shippingMethodController
     */
    protected $shippingMethodController;

    /**
     * @var ConfigurationService $configuration
     */
    protected $configuration;

    public function __construct()
    {
        Bootstrap::init();

        $this->controller = new CoreController();
        $this->shippingMethodController = new ShippingMethodController();
        $this->configuration = ServiceRegister::getService( Configuration::CLASS_NAME );
    }

    /**
     * Offline payment methods
     *
     * @var string[]
     */
    protected $knownOffline = array(
        'ps_wirepayment',
        'ps_checkpayment',
        'ps_cashondelivery',
    );

    /**
     * @param ShippingService[] $services
     * @param Cart $cart
     *
     * @return ShippingService|null
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getMatchingService(array $services, Cart $cart)
    {
        $warehouse = $this->configuration->getDefaultWarehouse();

        $fromCountry = $warehouse ? $warehouse->country : null;
        $toCountry   = $warehouse ? $warehouse->country : null;

        if ($cart && $cart->id_address_delivery) {
            $address = new Address((int) $cart->id_address_delivery);
            if (Validate::isLoadedObject($address) && $address->id_country) {
                $country = new Country((int) $address->id_country);
                $toCountry = $country->iso_code;
            }
        }

        return $this->getCheapestMatchingService($services, $fromCountry, $toCountry);
    }
    /**
     * @return array
     */
    public function getOfflinePayments()
    {
        $offlinePayments = array();

        foreach (PaymentModule::getInstalledPaymentModules() as $payment) {
            $module = Module::getInstanceByName($payment['name']);

            if (!Validate::isLoadedObject($module) || !$module->active) {
                continue;
            }

            if ($this->isOffline($module->name)) {
                $offlinePayments[] = [
                    'name' => $module->name,
                    'displayName' => $module->displayName,
                ];
            }
        }

        return $offlinePayments;
    }

    /**
     * @param string $orderId
     *
     * @return bool
     */
    public function shouldSurchargeApply($orderId)
    {
        try {
            $acc = $this->getAccountConfiguration();

            $order = $this->getShopOrderService()->getOrderAndShippingData($orderId);

            return $acc && $acc->account && $acc->enabled && $acc->active && $acc->account->getOfflinePaymentMethod() === $order->getPaymentId();

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param $orderId
     * @param $shippingMethodId
     * @return bool
     */
    public function isValidPaymentMethod($orderId, $shippingMethodId)
    {
        try {
            $acc = $this->getAccountConfiguration();

            $paymentId = $this->getShopOrderService()->getOrderAndShippingData($orderId)->getPaymentId();

            if(!$acc || !$acc->active || !$acc->enabled || !$acc->account || $acc->account->getOfflinePaymentMethod() !== $paymentId) {
                return true;
            }

            $services = $this->shippingMethodController->getShippingServicesForMethod($shippingMethodId);

            $config = $services[0]->cashOnDeliveryConfig;

            if ($config && !$config->offered) {
                return false;
            }

            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    public function calculateFee($orderId)
    {
        $controller = new CoreController();

        $order = $this->getShopOrderService()->getOrderAndShippingData($orderId);

        return $controller->calculateFee($order);
    }

    /**
     * Retrieves Packlink account configuration and checks if an account exists.
     *
     * @return CashOnDelivery|null
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function getAccountConfiguration()
    {
        return $this->controller->getCashOnDeliveryConfiguration();
    }

    /**
     * @param string $moduleName
     *
     * @return bool
     */
    protected function isOffline($moduleName)
    {
        return in_array($moduleName, $this->knownOffline, true);
    }

    /**
     * @return ShopOrderService
     */
    private function getShopOrderService()
    {
        /** @var ShopOrderService $shopOrderService */
        $shopOrderService = ServiceRegister::getService(ShopOrderService::CLASS_NAME);

        return $shopOrderService;
    }
}