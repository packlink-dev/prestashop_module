<?php

namespace Packlink\PrestaShop\Classes\ShippingServices;

use Cart;
use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\Order\Objects\Address;
use Packlink\BusinessLogic\Order\Objects\Item;
use Packlink\BusinessLogic\Order\Objects\Order;
use Packlink\BusinessLogic\Warehouse\Warehouse;
use Packlink\PrestaShop\Classes\Utility\CachingUtility;
use Packlink\PrestaShop\Classes\Utility\CustomsDataProvider;

/**
 * Class CheckoutOrderFactory.
 *
 * Builds a core Order from a cart, which is what the duty-cost call needs at checkout: the core
 * DdpCostService creates a customs invoice from an Order, and until an order is placed there is none.
 * ShopOrderService::getOrderAndShippingData() does the equivalent job for a placed order; the
 * customs-relevant values here are resolved through the same CustomsDataProvider so the checkout
 * estimate and the shipment's customs invoice cannot disagree.
 *
 * Nothing in this class may throw: a duty estimate is optional at checkout, while an exception here
 * would take the whole carrier list down with it. A missing phone, tax id or HS code is normal.
 *
 * @package Packlink\PrestaShop\Classes\ShippingServices
 */
class CheckoutOrderFactory
{
    /**
     * Builds a core Order from the given cart.
     *
     * @param Cart $cart PrestaShop cart.
     * @param Warehouse $warehouse Default warehouse, used as the fallback origin/destination.
     *
     * @return Order
     */
    public static function fromCart(Cart $cart, Warehouse $warehouse, $shippingCost = null)
    {
        $order = new Order();

        try {
            $products = $cart->getProducts();
        } catch (\Exception $e) {
            Logger::logWarning('Failed to read cart products: ' . $e->getMessage(), 'Integration');
            $products = array();
        }

        $provider = new CustomsDataProvider(self::loadCustomsMapping());
        $provider->preloadProducts(self::productIds($products));

        $order->setCustomerId((int)$cart->id_customer);
        $order->setCurrency(self::getCurrencyCode($cart));

        // Declared value: read through the same cached total the costing call uses, so the value
        // declared to Packlink matches the cart the shopper is being priced on.
        $order->setTotalPrice((float)self::getCartTotal($cart));
        $order->setBasePrice((float)self::getCartTotalWithoutTax($cart));

        $order->setShippingAddress(self::getAddress($cart, $warehouse));

        $taxId = $provider->resolveReceiverTaxId((int)$cart->id_customer, self::getDeliveryVatNumber($cart));
        if ($taxId !== '') {
            $order->setTaxId($taxId);
        }

        // Every cart line is sent: the documented per-invoice limits are not enforced by the API, so
        // capping or consolidating lines here would only make the estimate disagree with the invoice.
        $order->setItems(self::getItems($products, $provider, self::getLanguageId($cart)));
        $order->setTotalWeight(self::getTotalWeight($products));

        // Freight for the customs invoice's shipment cost (C8): customs value = goods + freight, so
        // this must be the transport price, never the goods value. method_exists guards against a
        // core pinned before the field existed.
        if ($shippingCost !== null && method_exists($order, 'setShippingCost')) {
            $order->setShippingCost((float)$shippingCost);
        }

        return $order;
    }

    /**
     * Builds the shipping address from the cart's delivery address, falling back to the warehouse when
     * the shopper has not chosen one yet.
     *
     * @param Cart $cart
     * @param Warehouse $warehouse
     *
     * @return Address
     */
    private static function getAddress(Cart $cart, Warehouse $warehouse)
    {
        $shippingAddress = new Address();

        try {
            if (empty($cart->id_address_delivery)) {
                $shippingAddress->setCountry($warehouse->country);
                $shippingAddress->setZipCode($warehouse->postalCode);

                return $shippingAddress;
            }

            $deliveryAddress = CachingUtility::getAddress((int)$cart->id_address_delivery);
            $country = CachingUtility::getCountry((int)$deliveryAddress->id_country);

            if ($country !== null && !empty($country->iso_code)) {
                $shippingAddress->setCountry($country->iso_code);
            }

            $shippingAddress->setZipCode($deliveryAddress->postcode);
            $shippingAddress->setCity($deliveryAddress->city);
            $shippingAddress->setCompany($deliveryAddress->company);
            $shippingAddress->setPhone($deliveryAddress->phone ?: $deliveryAddress->phone_mobile);
            $shippingAddress->setStreet1($deliveryAddress->address1);
            $shippingAddress->setStreet2($deliveryAddress->address2);
            $shippingAddress->setName($deliveryAddress->firstname);
            $shippingAddress->setSurname($deliveryAddress->lastname);

            $customer = new \Customer((int)$cart->id_customer);
            if (\Validate::isLoadedObject($customer)) {
                $shippingAddress->setName($deliveryAddress->firstname ?: $customer->firstname);
                $shippingAddress->setSurname($deliveryAddress->lastname ?: $customer->lastname);
            }
        } catch (\Exception $e) {
            Logger::logWarning('Failed to build checkout shipping address: ' . $e->getMessage(), 'Integration');
        }

        return $shippingAddress;
    }

    /**
     * Builds one item per non-virtual cart line.
     *
     * @param array $products Cart product rows.
     * @param CustomsDataProvider $provider
     * @param int $languageId
     *
     * @return Item[]
     */
    private static function getItems(array $products, CustomsDataProvider $provider, $languageId)
    {
        $items = array();

        foreach ($products as $product) {
            try {
                if (!empty($product['is_virtual'])) {
                    continue;
                }

                $items[] = self::getItem($product, $provider, $languageId);
            } catch (\Exception $e) {
                Logger::logWarning(
                    'Skipping cart line for product ' . (isset($product['id_product']) ? (int)$product['id_product'] : 0)
                    . ' while building the duty estimate: ' . $e->getMessage(),
                    'Integration'
                );
            }
        }

        return $items;
    }

    /**
     * Builds a single item from a cart product row.
     *
     * @param array $product Cart product row.
     * @param CustomsDataProvider $provider
     * @param int $languageId
     *
     * @return Item
     */
    private static function getItem(array $product, CustomsDataProvider $provider, $languageId)
    {
        $defaultParcel = CachingUtility::getDefaultParcel();
        $productId = isset($product['id_product']) ? (int)$product['id_product'] : 0;

        $item = new Item();
        $item->setId($productId);
        $item->setQuantity(isset($product['quantity']) ? (int)$product['quantity'] : 1);

        if (!empty($product['reference'])) {
            $item->setSku($product['reference']);
        }

        if (!empty($product['name'])) {
            $item->setTitle($product['name']);
        }

        if (!empty($product['category'])) {
            $item->setCategoryName($product['category']);
        }

        // Unit prices, matching the order path: setPrice excludes tax, setTotalPrice includes it.
        $item->setPrice(isset($product['price']) ? (float)$product['price'] : 0.0);
        $item->setTotalPrice(isset($product['price_wt']) ? (float)$product['price_wt'] : 0.0);

        // Same dimension fallbacks as the packages sent for pricing (CachingUtility::getPackages).
        $weight = self::value($product, 'weight_attribute') ?: self::value($product, 'weight')
            ?: (float)$defaultParcel->weight;
        $item->setWeight(round((float)$weight, 2));
        $item->setWidth(ceil(self::value($product, 'width')) ?: (int)$defaultParcel->width);
        $item->setHeight(ceil(self::value($product, 'height')) ?: (int)$defaultParcel->height);
        $item->setLength(ceil(self::value($product, 'depth')) ?: (int)$defaultParcel->length);

        // Customs attributes; empty values fall back to the configured customs defaults in core.
        $tariffNumber = $provider->resolveTariffNumber($productId);
        if ($tariffNumber !== '') {
            $item->setTariffNumber($tariffNumber);
        }

        $countryOfOrigin = $provider->resolveCountryOfOrigin($productId);
        if ($countryOfOrigin !== '') {
            $item->setCountryOfOrigin($countryOfOrigin);
        }

        return $item;
    }

    /**
     * Total shipment weight, taken from the same packages the costing call sends.
     *
     * @param array $products Cart product rows.
     *
     * @return float
     */
    private static function getTotalWeight(array $products)
    {
        $total = 0.0;

        try {
            foreach (CachingUtility::getPackages($products) as $package) {
                $total += (float)$package->weight;
            }
        } catch (\Exception $e) {
            Logger::logWarning('Failed to total cart weight: ' . $e->getMessage(), 'Integration');
        }

        return round($total, 2);
    }

    /**
     * Cart total including tax, without shipping, read through the shared per-request cache so the
     * declared value is identical to the one the costing call used.
     *
     * @param Cart $cart
     *
     * @return float
     */
    private static function getCartTotal(Cart $cart)
    {
        try {
            if (CachingUtility::getCartTotal() === false) {
                CachingUtility::setCartTotal($cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING));
            }

            return (float)CachingUtility::getCartTotal();
        } catch (\Exception $e) {
            Logger::logWarning('Failed to read cart total: ' . $e->getMessage(), 'Integration');

            return 0.0;
        }
    }

    /**
     * Cart total excluding tax, without shipping.
     *
     * @param Cart $cart
     *
     * @return float
     */
    private static function getCartTotalWithoutTax(Cart $cart)
    {
        try {
            return (float)$cart->getOrderTotal(false, Cart::BOTH_WITHOUT_SHIPPING);
        } catch (\Exception $e) {
            Logger::logWarning('Failed to read cart total without tax: ' . $e->getMessage(), 'Integration');

            return 0.0;
        }
    }

    /**
     * Returns the VAT number of the cart's delivery address, or an empty string.
     *
     * @param Cart $cart
     *
     * @return string
     */
    private static function getDeliveryVatNumber(Cart $cart)
    {
        try {
            if (empty($cart->id_address_delivery)) {
                return '';
            }

            $deliveryAddress = CachingUtility::getAddress((int)$cart->id_address_delivery);

            return !empty($deliveryAddress->vat_number) ? $deliveryAddress->vat_number : '';
        } catch (\Exception $e) {
            Logger::logWarning('Failed to read cart delivery VAT number: ' . $e->getMessage(), 'Integration');

            return '';
        }
    }

    /**
     * Returns the ISO code of the cart currency.
     *
     * @param Cart $cart
     *
     * @return string
     */
    private static function getCurrencyCode(Cart $cart)
    {
        try {
            $currency = new \Currency((int)$cart->id_currency);

            return \Validate::isLoadedObject($currency) ? $currency->iso_code : '';
        } catch (\Exception $e) {
            Logger::logWarning('Failed to read cart currency: ' . $e->getMessage(), 'Integration');

            return '';
        }
    }

    /**
     * Language id of the cart, falling back to the current context.
     *
     * @param Cart $cart
     *
     * @return int
     */
    private static function getLanguageId(Cart $cart)
    {
        if (!empty($cart->id_lang)) {
            return (int)$cart->id_lang;
        }

        $context = \Context::getContext();

        return ($context !== null && $context->language !== null) ? (int)$context->language->id : 0;
    }

    /**
     * Product ids of every cart line.
     *
     * @param array $products Cart product rows.
     *
     * @return int[]
     */
    private static function productIds(array $products)
    {
        $productIds = array();
        foreach ($products as $product) {
            if (isset($product['id_product'])) {
                $productIds[] = (int)$product['id_product'];
            }
        }

        return $productIds;
    }

    /**
     * Reads a numeric value from a cart product row.
     *
     * @param array $product
     * @param string $key
     *
     * @return float
     */
    private static function value(array $product, $key)
    {
        return isset($product[$key]) ? (float)$product[$key] : 0.0;
    }

    /**
     * Loads the stored customs data-source mapping, or null when none is configured.
     *
     * @return \Packlink\BusinessLogic\Customs\Models\CustomsMapping|null
     */
    private static function loadCustomsMapping()
    {
        try {
            /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
            $configService = ServiceRegister::getService(Configuration::CLASS_NAME);

            return $configService->getCustomsMappings();
        } catch (\Exception $e) {
            Logger::logWarning('Failed to load customs mapping: ' . $e->getMessage(), 'Integration');

            return null;
        }
    }
}
