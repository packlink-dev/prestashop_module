<?php

namespace Packlink\PrestaShop\Classes\ShippingServices;

use Address;
use Carrier;
use Cart;
use Context;
use Customer;
use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\DDP\DdpBehavior;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod;
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
     * Effective DDP behaviour per shipping method for this request. Pricing runs once per carrier row
     * and twice per method (base + DDP row), so the behaviour lookup is memoized per request.
     *
     * @var array
     */
    private static $ddpBehaviorCache = array();

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
     * @throws \Exception
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
                return self::applyShopCostCalculationSettings(min(array_values($allCosts)), $cart);
            }
        }

        if ($calculatedCosts !== false) {
            return isset($calculatedCosts[$methodId])
                ? self::resolveCost($cart, $calculatedCosts[$methodId], $methodId, $carrierReferenceId, $shippingProducts)
                : false;
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
            self::getCartTotal($cart),
            (string)\Context::getContext()->shop->id
        );

        CachingUtility::setCosts($calculatedCosts);

        return isset($calculatedCosts[$methodId])
            ? self::resolveCost($cart, $calculatedCosts[$methodId], $methodId, $carrierReferenceId, $shippingProducts)
            : false;
    }

    /**
     * Resolves the price of one carrier row, which for a duties-charging method is two rows: the base
     * (transport-only) carrier and its DDP carrier.
     *
     * The duty amount rides inside the carrier price (DP6) so it is taxed by that carrier's tax rules
     * group and refunded natively. Shop cost-calculation settings are applied to the transport portion
     * only (DP9): a met free-shipping threshold zeroes transport while duty stays owed.
     *
     * @param Cart $cart PrestaShop cart object.
     * @param float $transportCost Raw calculated transport cost for the method.
     * @param int $methodId Packlink shipping method id.
     * @param int $carrierReferenceId PrestaShop carrier reference id of the row being priced.
     * @param array $shippingProducts Non-virtual cart product rows.
     *
     * @return float|bool Price for this carrier row, or FALSE when the row must not be offered.
     *
     * @throws \Exception Only from the pre-existing transport pricing; the DDP surface never throws.
     */
    private static function resolveCost(
        Cart $cart,
        $transportCost,
        $methodId,
        $carrierReferenceId,
        array $shippingProducts
    ) {
        // Transport pricing stays outside the guard below: its failures behaved the same before DDP
        // support and must keep doing so.
        $transport = self::applyShopCostCalculationSettings($transportCost, $cart);

        // PrestaShop calls the shipping-cost hook unwrapped, so nothing from the DDP surface may
        // escape it: any failure degrades this row to the transport-only price (fail-soft, DP5).
        try {
            return self::resolveDdpAwareCost($cart, $transport, $methodId, $carrierReferenceId, $shippingProducts);
        } catch (\Exception $e) {
            Logger::logWarning(
                'Failed to resolve DDP pricing for carrier reference ' . (int)$carrierReferenceId
                . ': ' . $e->getMessage(),
                'Integration'
            );

            return $transport;
        }
    }

    /**
     * Applies the DDP pricing rules to one carrier row.
     *
     * @param Cart $cart PrestaShop cart object.
     * @param float $transport Transport cost with shop cost settings already applied.
     * @param int $methodId Packlink shipping method id.
     * @param int $carrierReferenceId PrestaShop carrier reference id of the row being priced.
     * @param array $shippingProducts Non-virtual cart product rows.
     *
     * @return float|bool Price for this carrier row, or FALSE when the row must not be offered.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private static function resolveDdpAwareCost(
        Cart $cart,
        $transport,
        $methodId,
        $carrierReferenceId,
        array $shippingProducts
    ) {
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService $carrierService */
        $carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);
        $isDdpCarrier = $carrierService->isDdpCarrier($carrierReferenceId);

        // Domestic (or otherwise inapplicable) shipment: no duty is owed, so the DDP row is not offered
        // and the base row prices normally regardless of the merchant's behaviour setting.
        if (!CheckoutDdpService::isApplicable($cart)) {
            return $isDdpCarrier ? false : $transport;
        }

        $behavior = self::getEffectiveDdpBehavior($methodId);
        if ($behavior === DdpBehavior::NONE) {
            return $isDdpCarrier ? false : $transport;
        }

        $ddp = CheckoutDdpService::getAdjustedAmount($cart, $methodId, $shippingProducts);
        $dutyAvailable = $ddp !== null;

        if ($isDdpCarrier) {
            if (!$dutyAvailable) {
                return false;
            }

            // Record the split here, where both halves are already known. The checkout presentation
            // reads this instead of pricing the carrier again while rendering.
            CachingUtility::setDdpTransport($methodId, $transport);

            return $transport + $ddp;
        }

        // Duty is only offerable through an existing DDP twin carrier: if that row is missing
        // (creation failed, merchant deleted it), hiding the base row would drop the whole service
        // from checkout, so the base row stays visible instead (fail-soft, DP5).
        $twinExists = $carrierService->getDdpCarrierReferenceId($methodId) !== null;

        return CheckoutDdpService::shouldHideBaseCarrier($behavior, $dutyAvailable && $twinExists)
            ? false : $transport;
    }

    /**
     * Returns the effective DDP behaviour of a shipping method, defaulting to NONE when the method
     * cannot be loaded.
     *
     * @param int $methodId Packlink shipping method id.
     *
     * @return string One of DdpBehavior::NONE, OPTIONAL, ENFORCED, MANDATORY.
     */
    private static function getEffectiveDdpBehavior($methodId)
    {
        $methodId = (int)$methodId;
        if (array_key_exists($methodId, self::$ddpBehaviorCache)) {
            return self::$ddpBehaviorCache[$methodId];
        }

        return self::$ddpBehaviorCache[$methodId] = self::fetchEffectiveDdpBehavior($methodId);
    }

    /**
     * Loads the effective DDP behaviour of a shipping method from storage.
     *
     * @param int $methodId Packlink shipping method id.
     *
     * @return string One of DdpBehavior::NONE, OPTIONAL, ENFORCED, MANDATORY.
     */
    private static function fetchEffectiveDdpBehavior($methodId)
    {
        try {
            $repository = RepositoryRegistry::getRepository(ShippingMethod::getClassName());

            $filter = new QueryFilter();
            $filter->where('id', Operators::EQUALS, (int)$methodId);

            /** @var ShippingMethod|null $method */
            $method = $repository->selectOne($filter);

            return $method !== null ? $method->getEffectiveDdpBehavior() : DdpBehavior::NONE;
        } catch (\Exception $e) {
            Logger::logWarning(
                'Failed to resolve DDP behaviour for method ' . (int)$methodId . ': ' . $e->getMessage(),
                'Integration'
            );

            return DdpBehavior::NONE;
        }
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
     * @throws \Exception
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
            self::getCartTotal($cart),
            (string)\Context::getContext()->shop->id
        );
    }

    /**
     * Returns destination country code.
     *
     * @param \Cart $cart PrestaShop cart object.
     * @param \Packlink\BusinessLogic\Warehouse\Warehouse $warehouse
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
     * @param \Packlink\BusinessLogic\Warehouse\Warehouse $warehouse
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
     * @throws \Exception
     */
    private static function getCartTotal(Cart $cart)
    {
        if (CachingUtility::getCartTotal() === false) {
            CachingUtility::setCartTotal($cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING));
        }

        return CachingUtility::getCartTotal();
    }

    /**
     * Checks shipping cost settings and handling costs and applies settings to the given cost.
     *
     * @param float $cost
     * @param \Cart $cart
     *
     * @return float Calculated cost.
     *
     * @throws \PrestaShopException
     * @throws \Exception
     */
    private static function applyShopCostCalculationSettings($cost, Cart $cart)
    {
        // if shipping service is available
        $configuration = \Configuration::getMultiple(array(
            'PS_SHIPPING_FREE_PRICE',
            'PS_SHIPPING_FREE_WEIGHT',
        ));

        if ((float)$configuration['PS_SHIPPING_FREE_PRICE'] > 0
            && self::getCartTotal($cart) >= (float)$configuration['PS_SHIPPING_FREE_PRICE']
        ) {
            return 0;
        }

        if ((float)$configuration['PS_SHIPPING_FREE_WEIGHT'] > 0
            && $cart->getTotalWeight() >= (float)$configuration['PS_SHIPPING_FREE_WEIGHT']
        ) {
            return 0;
        }

        return $cost;
    }
}
