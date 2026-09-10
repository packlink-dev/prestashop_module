<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Configuration as ConfigurationInterface;
use Packlink\BusinessLogic\Http\DTO\Package;
use Packlink\BusinessLogic\Http\DTO\ParcelInfo;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\PrestaShop\Classes\Entities\CarrierServiceMapping;

/**
 * Class CachingUtility
 *
 * @package Packlink\PrestaShop\Classes\Utility
 */
class CachingUtility
{
    /**
     * @var array
     */
    protected static $costCache;
    /**
     * Composed duty amounts for this request, keyed by shipping method id. Null until fetched.
     *
     * @var array|null
     */
    protected static $ddpCostCache;
    /**
     * Transport portion of each duties-paid carrier's price for this request, keyed by method id.
     * Filled while pricing, read by the checkout presentation so it never has to price again.
     *
     * @var array
     */
    protected static $ddpTransportCache = array();
    /**
     * @var float
     */
    protected static $cartTotal;
    /**
     * @var \Packlink\BusinessLogic\Warehouse\Warehouse
     */
    protected static $wareHouse;
    /**
     * @var array
     */
    protected static $carriers = array();
    /**
     * @var \Packlink\BusinessLogic\Configuration
     */
    protected static $config;
    /**
     * @var array
     */
    protected static $addressCache = array();
    /**
     * @var array
     */
    protected static $countryCache = array();
    /**
     * @var array
     */
    protected static $parcelCache = array();
    /**
     * @var ParcelInfo
     */
    protected static $parcel;
    /**
     * @var \Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService
     */
    protected static $carrierService;
    /**
     * All carrier-service mapping rows for this request. Null until loaded; checkout pricing reads a
     * mapping ~3 times per carrier row, so mappings are fetched with one select instead of one each.
     *
     * @var CarrierServiceMapping[]|null
     */
    protected static $carrierMappingCache;

    /**
     * Caches Carrier.
     *
     * @param int $id
     *
     * @return \Carrier
     */
    public static function getCarrier($id)
    {
        if (empty(self::$carriers[$id])) {
            self::$carriers[$id] = new \Carrier($id);
        }

        return self::$carriers[$id];
    }

    /**
     * Retrieves cost cache.
     *
     * @return array | float
     */
    public static function getCosts()
    {
        if (self::$costCache === null) {
            return false;
        }

        return self::$costCache;
    }

    /**
     * Sets cost cache.
     *
     * @param array $calculatedCosts Array of calculated shipping costs.
     */
    public static function setCosts($calculatedCosts)
    {
        self::$costCache = $calculatedCosts;
    }

    /**
     * Retrieves the composed duty amounts for this request, keyed by shipping method id.
     *
     * Distinguishes "not fetched yet" (FALSE) from "fetched, nothing available" (empty array), so a
     * failed or inapplicable duty lookup is not retried once per carrier during the same render.
     *
     * @return array|bool Array of amounts keyed by method id, or FALSE when not yet fetched.
     */
    public static function getDdpCosts()
    {
        if (self::$ddpCostCache === null) {
            return false;
        }

        return self::$ddpCostCache;
    }

    /**
     * Sets the composed duty amounts for this request.
     *
     * @param array $ddpCosts Composed duty amounts keyed by shipping method id.
     */
    public static function setDdpCosts($ddpCosts)
    {
        self::$ddpCostCache = $ddpCosts;
    }

    /**
     * Records the transport portion of a duties-paid carrier's price, as computed while pricing it.
     *
     * @param int $methodId Packlink shipping method id.
     * @param float $transport Transport cost with shop cost settings already applied.
     */
    public static function setDdpTransport($methodId, $transport)
    {
        self::$ddpTransportCache[(int)$methodId] = (float)$transport;
    }

    /**
     * Transport portions recorded while pricing, keyed by method id.
     *
     * @return array
     */
    public static function getDdpTransport()
    {
        return self::$ddpTransportCache;
    }

    /**
     * Retrieves total cart value.
     *
     * @return array | float
     */
    public static function getCartTotal()
    {
        if (self::$cartTotal === null) {
            return false;
        }

        return self::$cartTotal;
    }

    /**
     * Sets total cart value.
     *
     * @param float $cartTotal Cart total value.
     */
    public static function setCartTotal($cartTotal)
    {
        self::$cartTotal = $cartTotal;
    }

    /**
     * Retrieves default warehouse.
     *
     * @return \Packlink\BusinessLogic\Warehouse\Warehouse
     */
    public static function getDefaultWarehouse()
    {
        if (self::$wareHouse === null) {
            self::$wareHouse = self::getConfig()->getDefaultWarehouse();
        }

        return self::$wareHouse;
    }

    /**
     * Retrieves address from cache.
     *
     * @param int $id
     *
     * @return \Address
     */
    public static function getAddress($id)
    {
        if (empty(self::$addressCache[$id])) {
            self::$addressCache[$id] = new \Address($id);
        }

        return self::$addressCache[$id];
    }

    /**
     * Retrieves country cache.
     *
     * @param $id
     *
     * @return \Country
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public static function getCountry($id)
    {
        if (empty(self::$countryCache[$id])) {
            self::$countryCache[$id] = new \Country($id);
        }

        return self::$countryCache[$id];
    }

    /**
     * Retrieves package cache for products.
     *
     * @param array $products Shop products.
     *
     * @return \Packlink\BusinessLogic\Http\DTO\Package[]
     */
    public static function getPackages($products)
    {
        if (empty(self::$parcelCache)) {
            $defaultParcel = self::getDefaultParcel();

            foreach ($products as $product) {
                $package = new Package(
                    (float)$product['weight_attribute'] ?: (float)$product['weight'] ?: (float)$defaultParcel->weight,
                    ceil((float)$product['width']) ?: (int)$defaultParcel->width,
                    ceil((float)$product['height']) ?: (int)$defaultParcel->height,
                    ceil((float)$product['depth']) ?: (int)$defaultParcel->length
                );

                for ($i = 0; $i < $product['quantity']; $i++) {
                    self::$parcelCache[] = $package;
                }
            }
        }

        return self::$parcelCache;
    }

    /**
     * @return \Packlink\BusinessLogic\Http\DTO\ParcelInfo
     */
    public static function getDefaultParcel()
    {
        if (self::$parcel === null) {
            self::$parcel = self::getConfig()->getDefaultParcel();
        }

        return self::$parcel;
    }

    /**
     * Returns every carrier-service mapping row, loading all of them with a single select on first use.
     *
     * @return CarrierServiceMapping[]
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public static function getCarrierServiceMappings()
    {
        if (self::$carrierMappingCache === null) {
            $repository = RepositoryRegistry::getRepository(CarrierServiceMapping::getClassName());
            self::$carrierMappingCache = $repository->select();
        }

        return self::$carrierMappingCache;
    }

    /**
     * Drops the mapping cache. Must be called after every mapping write so that reads later in the
     * same request (e.g. syncDdpCarrier right after createCarrier) see the write.
     */
    public static function resetCarrierServiceMappings()
    {
        self::$carrierMappingCache = null;
    }

    /**
     * @return \Packlink\BusinessLogic\Configuration
     */
    protected static function getConfig()
    {
        if (self::$config === null) {
            self::$config = ServiceRegister::getService(ConfigurationInterface::CLASS_NAME);
        }

        return self::$config;
    }

    /**
     * @return \Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService
     */
    protected static function getCarrierService()
    {
        if (self::$carrierService === null) {
            self::$carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);
        }

        return self::$carrierService;
    }
}
