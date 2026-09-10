<?php

namespace Packlink\PrestaShop\Classes\Utility;

/**
 * Class MoneyFormatter.
 *
 * Formats monetary amounts with PrestaShop's own currency formatting so the output follows the
 * shop locale (6.6 renders as "6,60 €" under a comma-decimal locale, not "6.60 EUR").
 *
 * @package Packlink\PrestaShop\Classes\Utility
 */
class MoneyFormatter
{
    /**
     * Formats an amount as a localized money string.
     *
     * Never throws: the result is a display string in checkout and the admin order tab, where a
     * formatting failure must not take the page down. Any error degrades to a plain "%.2f ISO"
     * string instead.
     *
     * @param float $amount Amount to format.
     * @param string $isoCode Currency ISO code. When empty, the context currency is used.
     *
     * @return string
     */
    public static function format($amount, $isoCode = '')
    {
        $amount = (float)$amount;
        $isoCode = $isoCode !== null ? strtoupper(trim((string)$isoCode)) : '';

        try {
            if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
                return self::formatLegacy($amount, $isoCode);
            }

            return self::formatWithLocale($amount, $isoCode);
        } catch (\Throwable $e) {
            // Unmatched on PHP 5, where \Throwable does not exist; the \Exception block below
            // covers that runtime instead.
        } catch (\Exception $e) {
            // Degrade to the plain fallback below.
        }

        return self::fallbackFormat($amount, $isoCode);
    }

    /**
     * Formats through Tools::displayPrice(). Deprecated in 1.7 and removed in 9, so this path is
     * only ever reached on PrestaShop 1.6.
     *
     * @param float $amount
     * @param string $isoCode
     *
     * @return string
     */
    private static function formatLegacy($amount, $isoCode)
    {
        $currency = self::resolveCurrency($isoCode);

        if ($currency === null) {
            return self::fallbackFormat($amount, $isoCode);
        }

        return \Tools::displayPrice($amount, $currency);
    }

    /**
     * Formats through the 1.7+/9.x Locale service. The service only became reachable from the
     * context around 1.7.6, so both accessors are probed and older 1.7 releases fall back to the
     * plain string.
     *
     * @param float $amount
     * @param string $isoCode
     *
     * @return string
     */
    private static function formatWithLocale($amount, $isoCode)
    {
        if ($isoCode === '') {
            $currency = self::resolveCurrency('');

            if ($currency === null) {
                return self::fallbackFormat($amount, '');
            }

            $isoCode = $currency->iso_code;
        }

        $locale = self::getLocale();

        if ($locale === null) {
            return self::fallbackFormat($amount, $isoCode);
        }

        return $locale->formatPrice($amount, $isoCode);
    }

    /**
     * Resolves the Currency object to format with.
     *
     * When an ISO code is requested but not installed in the shop, this returns null rather than
     * substituting the context currency: showing the shop's symbol next to an amount quoted in a
     * different currency would misstate the price.
     *
     * @param string $isoCode Currency ISO code, or an empty string for the context currency.
     *
     * @return \Currency|null
     */
    private static function resolveCurrency($isoCode)
    {
        if ($isoCode !== '') {
            $currencyId = (int)\Currency::getIdByIsoCode($isoCode);

            if ($currencyId) {
                $currency = new \Currency($currencyId);

                if (\Validate::isLoadedObject($currency)) {
                    return $currency;
                }
            }

            return null;
        }

        $context = \Context::getContext();

        if ($context !== null
            && isset($context->currency)
            && \Validate::isLoadedObject($context->currency)
        ) {
            return $context->currency;
        }

        return null;
    }

    /**
     * Returns the context locale, or null when the running PrestaShop version does not expose one.
     *
     * @return \PrestaShop\PrestaShop\Core\Localization\Locale|null
     */
    private static function getLocale()
    {
        $context = \Context::getContext();

        if ($context === null) {
            return null;
        }

        if (method_exists($context, 'getCurrentLocale')) {
            $locale = $context->getCurrentLocale();

            if ($locale !== null) {
                return $locale;
            }
        }

        if (method_exists('Tools', 'getContextLocale')) {
            return \Tools::getContextLocale($context);
        }

        return null;
    }

    /**
     * Locale-agnostic last resort: a fixed two-decimal amount with the ISO code appended when one
     * is known. Wrong locale beats a broken page.
     *
     * @param float $amount
     * @param string $isoCode
     *
     * @return string
     */
    private static function fallbackFormat($amount, $isoCode)
    {
        $formatted = sprintf('%.2f', $amount);

        return $isoCode !== '' ? $formatted . ' ' . $isoCode : $formatted;
    }
}
