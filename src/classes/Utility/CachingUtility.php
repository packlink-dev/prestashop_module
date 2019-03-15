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

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Configuration as ConfigurationInterface;
use Packlink\BusinessLogic\Http\DTO\ParcelInfo;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;

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
     * @var float
     */
    protected static $cartTotal;
    /**
     * @var \Packlink\BusinessLogic\Http\DTO\Warehouse
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
     * @var array
     */
    protected static $carrierServiceCache = array();

    /**
     * Caches Carrier.
     *
     * @param int $id
     *
     * @return \Carrier
     *
     * @throws \PrestaShopException
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
     * @return \Packlink\BusinessLogic\Http\DTO\Warehouse
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
     */
    public static function getCountry($id)
    {
        if (empty(self::$countryCache[$id])) {
            self::$countryCache[$id] = new \Country($id);
        }

        return self::$countryCache[$id];
    }

    /**
     * Retrieves parcel cache for products.
     *
     * @param array $products
     *
     * @return array
     */
    public static function getParcels($products)
    {
        if (empty(self::$parcelCache)) {
            $defaultParcel = self::getDefaultParcel();

            foreach ($products as $product) {
                $parcel = new ParcelInfo();

                $parcel->height = ceil((float)$product['height']) ?: (int)$defaultParcel->height;
                $parcel->width = ceil((float)$product['width']) ?: (int)$defaultParcel->width;
                $parcel->length = ceil((float)$product['depth']) ?: (int)$defaultParcel->height;
                $parcel->weight = (float)$product['weight'] ?: (float)$defaultParcel->weight;

                for ($i = 0; $i < $product['quantity']; $i++) {
                    self::$parcelCache[] = $parcel;
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
