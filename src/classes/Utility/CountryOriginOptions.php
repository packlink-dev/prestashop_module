<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Packlink\BusinessLogic\Country\CountryCodes;

/**
 * Class CountryOriginOptions
 *
 * Builds the country-of-origin dropdown options for the product customs panel: the ISO 3166-1
 * alpha-2 codes Packlink accepts, labelled with the shop's localised country names, so the merchant
 * picks a country name while the value stored in ProductCustomsData stays the ISO code.
 *
 * @package Packlink\PrestaShop\Classes\Utility
 */
class CountryOriginOptions
{
    /**
     * ISO 3166-1 English short names for codes Packlink accepts but the PrestaShop country table
     * does not carry, so a country-name dropdown never has to show a bare code. Untranslated on
     * purpose: these are territories, and shipping origins for them are rare enough that the
     * English name is more useful than a missing one.
     *
     * @var array
     */
    private static $missingCountryNames = array(
        'BQ' => 'Bonaire, Sint Eustatius and Saba',
        'BV' => 'Bouvet Island',
        'CW' => 'Curacao',
        'HM' => 'Heard Island and McDonald Islands',
        'SH' => 'Saint Helena, Ascension and Tristan da Cunha',
        'SS' => 'South Sudan',
        'SX' => 'Sint Maarten (Dutch part)',
        'UM' => 'United States Minor Outlying Islands',
    );

    /**
     * Returns the dropdown options, sorted by localised country name.
     *
     * @param int $languageId Language used for the country names.
     *
     * @return array List of array('iso' => string, 'name' => string).
     */
    public static function get($languageId)
    {
        $names = self::getCountryNamesByIso($languageId);

        $options = array();
        foreach (CountryCodes::$countryCodes as $iso) {
            if (isset($names[$iso])) {
                $name = $names[$iso];
            } elseif (isset(self::$missingCountryNames[$iso])) {
                $name = self::$missingCountryNames[$iso];
            } else {
                // Last resort: the code itself, so a code Packlink adds later stays selectable.
                $name = $iso;
            }

            $options[] = array('iso' => $iso, 'name' => $name);
        }

        usort($options, array(__CLASS__, 'compareByName'));

        return $options;
    }

    /**
     * Resolves a free-text country value to an ISO 3166-1 alpha-2 code Packlink accepts.
     *
     * Needed because a merchant-created product feature mapped to the country of origin normally
     * holds a name ("Germany", "germany") rather than a code. Accepts either form and returns an
     * empty string when the value matches no known country, so the caller can fall back to the
     * configured default instead of sending something the API will reject.
     *
     * @param string $value Country name or ISO alpha-2 code.
     * @param int $languageId Language the names are compared in.
     *
     * @return string ISO alpha-2 code, or an empty string.
     */
    public static function resolveIso($value, $languageId)
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        $upper = strtoupper($value);
        if (strlen($upper) === 2 && in_array($upper, CountryCodes::$countryCodes, true)) {
            return $upper;
        }

        foreach (self::getCountryNamesByIso($languageId) as $iso => $name) {
            if (strcasecmp($name, $value) === 0 && in_array($iso, CountryCodes::$countryCodes, true)) {
                return $iso;
            }
        }

        // Territories the shop has no row for are still mappable by their English name.
        foreach (self::$missingCountryNames as $iso => $name) {
            if (strcasecmp($name, $value) === 0) {
                return $iso;
            }
        }

        return '';
    }

    /**
     * Maps ISO alpha-2 code => localised country name for every country the shop knows. Inactive
     * countries are included on purpose: origin is a property of the product, not a destination the
     * shop delivers to.
     *
     * @param int $languageId
     *
     * @return array
     */
    private static function getCountryNamesByIso($languageId)
    {
        $names = array();

        try {
            $countries = \Country::getCountries((int)$languageId, false);
        } catch (\Exception $e) {
            return $names;
        }

        if (!is_array($countries)) {
            return $names;
        }

        foreach ($countries as $country) {
            if (empty($country['iso_code']) || empty($country['name']) || !is_string($country['name'])) {
                continue;
            }

            $names[strtoupper($country['iso_code'])] = $country['name'];
        }

        return $names;
    }

    /**
     * Compares two options by their label.
     *
     * @param array $first
     * @param array $second
     *
     * @return int
     */
    private static function compareByName($first, $second)
    {
        return strcasecmp($first['name'], $second['name']);
    }
}
