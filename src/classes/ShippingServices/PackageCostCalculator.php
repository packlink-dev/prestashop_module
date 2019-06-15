<?php

namespace Packlink\PrestaShop\Classes\ShippingServices;

use Address;
use Carrier;
use Cart;
use Context;
use Customer;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\BusinessLogic\ShippingMethod\ShippingCostCalculator;
use Packlink\BusinessLogic\ShippingMethod\ShippingMethodService;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\CachingUtility;

/**
 * Class PackageCostCalculator.
 *
 * @package Packlink\PrestaShop\Classes\ShippingServices
 */
class PackageCostCalculator
{
    /**
     * Returns shipping cost for current cart and selected carrier.
     *
     * @param Cart $cart Shopping cart object.
     * @param array $products Array of shop products for which shipping cost is calculated.
     * @param int $carrierId Id of the current carrier to get costs for.
     *
     * @return float|bool Calculated shipping cost if carrier is available, otherwise FALSE.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public static function getPackageCost(Cart $cart, array $products, $carrierId)
    {
        Bootstrap::init();

        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService $carrierService */
        $carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);
        $carrier = CachingUtility::getCarrier($carrierId);
        $carrierReferenceId = (int)$carrier->id_reference;
        $methodId = $carrierService->getShippingMethodId($carrierReferenceId);

        if ($methodId === null) {
            return false;
        }

        $shippingProducts = array();
        foreach ($products as $product) {
            if (!$product['is_virtual']) {
                $shippingProducts[] = $product;
            }
        }

        $calculatedCosts = CachingUtility::getCosts();

        if (self::displayBackupCarrier($cart, $calculatedCosts, $carrierReferenceId)) {
            $allCosts = self::getCostsForAllShippingMethods($cart, $shippingProducts);
            if (!empty($allCosts)) {
                return min(array_values($allCosts));
            }
        }

        if ($calculatedCosts !== false) {
            return self::addHandlingCost($calculatedCosts, $methodId, $carrier);
        }

        $warehouse = CachingUtility::getDefaultWarehouse();
        if ($warehouse === null) {
            return false;
        }

        $toCountry = self::getDestinationCountryCode($cart, $warehouse);
        $toZip = self::getDestinationCountryZip($cart, $warehouse);
        $parcels = CachingUtility::getPackages($shippingProducts);

        /** @var \Packlink\BusinessLogic\ShippingMethod\ShippingMethodService $shippingMethodService */
        $shippingMethodService = ServiceRegister::getService(
            ShippingMethodService::CLASS_NAME
        );

        $calculatedCosts = $shippingMethodService->getShippingCosts(
            $warehouse->country,
            $warehouse->postalCode,
            $toCountry,
            $toZip,
            $parcels,
            self::getCartTotal($cart)
        );

        CachingUtility::setCosts($calculatedCosts);

        return self::addHandlingCost($calculatedCosts, $methodId, $carrier);
    }

    /**
     * Returns whether backup carrier should be displayed.
     *
     * @param \Cart $cart PrestaShop cart object.
     * @param array $calculatedCosts Array of calculated shipping costs.
     * @param int $carrierId ID of the carrier.
     *
     * @return bool Returns TRUE if backup carrier should be displayed, otherwise returns FALSE.
     */
    private static function displayBackupCarrier($cart, $calculatedCosts, $carrierId)
    {
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
        $configService = ServiceRegister::getService(\Packlink\BusinessLogic\Configuration::CLASS_NAME);

        if (is_array($calculatedCosts)
            && empty($calculatedCosts)
            && $carrierId === $configService->getBackupCarrierId()
        ) {
            $zoneId = Address::getZoneById($cart->id_address_delivery);
            $customer = new Customer($cart->id_customer);

            $internalCarriers = Carrier::getCarriers(
                Context::getContext()->language->id,
                true,
                false,
                $zoneId,
                $customer->getGroups(),
                Carrier::PS_CARRIERS_ONLY
            );

            return empty($internalCarriers);
        }

        return false;
    }

    /**
     * Returns shipping costs for all Packlink shipping methods, not just active ones.
     *
     * @param \Cart $cart PrestaShop cart object.
     * @param array $products Array of products.
     *
     * @return array Array of shipping costs for all Packlink shipping methods.
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    private static function getCostsForAllShippingMethods($cart, $products)
    {
        $warehouse = CachingUtility::getDefaultWarehouse();
        if ($warehouse === null) {
            return array();
        }

        /** @var \Packlink\BusinessLogic\ShippingMethod\ShippingMethodService $shippingMethodsService */
        $shippingMethodService = ServiceRegister::getService(ShippingMethodService::CLASS_NAME);

        return ShippingCostCalculator::getShippingCosts(
            $shippingMethodService->getAllMethods(),
            $warehouse->country,
            $warehouse->postalCode,
            self::getDestinationCountryCode($cart, $warehouse),
            self::getDestinationCountryZip($cart, $warehouse),
            CachingUtility::getPackages($products),
            self::getCartTotal($cart)
        );
    }

    /**
     * Returns destination country code.
     *
     * @param \Cart $cart PrestaShop cart object.
     * @param \Packlink\BusinessLogic\Http\DTO\Warehouse $warehouse
     *
     * @return string Destination country code.
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    private static function getDestinationCountryCode($cart, $warehouse)
    {
        $countryCode = $warehouse->country;

        if (!empty($cart->id_address_delivery)) {
            $deliveryAddress = CachingUtility::getAddress((int)$cart->id_address_delivery);
            $deliveryCountry = CachingUtility::getCountry((int)$deliveryAddress->id_country);

            $countryCode = $deliveryCountry->iso_code;
        }

        return $countryCode;
    }

    /**
     * Returns destination country ZIP code.
     *
     * @param \Cart $cart PrestaShop cart object.
     * @param \Packlink\BusinessLogic\Http\DTO\Warehouse $warehouse
     *
     * @return string Destination country zip code.
     */
    private static function getDestinationCountryZip($cart, $warehouse)
    {
        $destinationZip = $warehouse->postalCode;

        if (!empty($cart->id_address_delivery)) {
            $destinationZip = CachingUtility::getAddress((int)$cart->id_address_delivery)->postcode;
        }

        return $destinationZip;
    }

    /**
     * Gets total cart value.
     *
     * @param \Cart $cart
     *
     * @return array|float
     */
    private static function getCartTotal(Cart $cart)
    {
        if (CachingUtility::getCartTotal() === false) {
            CachingUtility::setCartTotal($cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING));
        }

        return CachingUtility::getCartTotal();
    }

    /**
     * Adds handling cost to the calculated cost.
     *
     * @param array $calculatedCosts
     * @param int $methodId
     * @param \Carrier $carrier
     *
     * @return bool|int|mixed
     */
    private static function addHandlingCost(array $calculatedCosts, $methodId, Carrier $carrier)
    {
        $cost = isset($calculatedCosts[$methodId]) ? $calculatedCosts[$methodId] : false;
        if ($cost !== false && $carrier->shipping_handling) {
            $cost += (float)\Configuration::get('PS_SHIPPING_HANDLING') ?: 0;
        }

        return $cost;
    }
}
