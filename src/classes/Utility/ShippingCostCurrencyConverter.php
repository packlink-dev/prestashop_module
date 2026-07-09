<?php

namespace Packlink\PrestaShop\Classes\Utility;

/**
 * Class ShippingCostCurrencyConverter
 *
 * @package Packlink\PrestaShop\Classes\Utility
 */
class ShippingCostCurrencyConverter
{
    /**
     * Converts shipping cost from service/API currency to cart currency.
     *
     * Uses PrestaShop conversion rates (same logic as Tools::convertPriceFull).
     *
     * @param float $amount Amount in service currency.
     * @param string $fromCurrencyIso ISO code of the service/API currency.
     * @param int $cartCurrencyId PrestaShop cart currency ID.
     *
     * @return float Converted amount in cart currency.
     */
    public static function convertToCartCurrency($amount, $fromCurrencyIso, $cartCurrencyId)
    {
        if (empty($fromCurrencyIso) || (int) $cartCurrencyId <= 0) {
            return $amount;
        }

        $cartCurrency = new \Currency((int) $cartCurrencyId);
        if (!\Validate::isLoadedObject($cartCurrency)) {
            return $amount;
        }

        if (strtoupper($fromCurrencyIso) === strtoupper($cartCurrency->iso_code)) {
            return $amount;
        }

        $fromCurrencyId = (int) \Currency::getIdByIsoCode($fromCurrencyIso);
        if ($fromCurrencyId <= 0) {
            return $amount;
        }

        $fromCurrency = new \Currency($fromCurrencyId);

        return self::convertPrice($amount, $fromCurrency, $cartCurrency);
    }

    /**
     * Converts price between two currencies using shop conversion rates.
     *
     * @param float $amount
     * @param \Currency $currencyFrom
     * @param \Currency $currencyTo
     *
     * @return float
     */
    private static function convertPrice($amount, \Currency $currencyFrom, \Currency $currencyTo)
    {
        if ((int) $currencyFrom->id === (int) $currencyTo->id) {
            return $amount;
        }

        $defaultCurrencyId = (int) \Configuration::get('PS_CURRENCY_DEFAULT');

        if ((int) $currencyFrom->id === $defaultCurrencyId) {
            $amount *= (float) $currencyTo->conversion_rate;
        } else {
            $conversionRate = (float) $currencyFrom->conversion_rate;
            if ($conversionRate <= 0) {
                $conversionRate = 1;
            }

            $amount = $amount / $conversionRate;
            $amount *= (float) $currencyTo->conversion_rate;
        }

        return (float) \Tools::ps_round($amount, _PS_PRICE_DISPLAY_PRECISION_);
    }
}
