<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\Logger\Logger;

/**
 * Class ProductFeatureSources
 *
 * Exposes the product features a merchant created in the shop (Catalog > Attributes & Features >
 * Features) as customs data-mapping sources, and reads a single product's value for one of them.
 *
 * A feature is the PrestaShop way to add an arbitrary named field to a product ("Country",
 * "Tariff code", ...), which is exactly what the customs mapping needs: the merchant picks their own
 * field on the customs settings page instead of being limited to the two fields this module adds to
 * the product page.
 *
 * Source keys are prefixed so they can never collide with the module's own source keys, and carry
 * the feature id rather than its name, so renaming a feature does not break an existing mapping.
 *
 * @package Packlink\PrestaShop\Classes\Utility
 */
class ProductFeatureSources
{
    /**
     * Prefix marking a mapping source that points at a product feature.
     */
    const SOURCE_PREFIX = 'feature_';

    /**
     * Language to read merchant-maintained product data in (feature names and values, country names).
     *
     * Features are language-scoped - names come from Feature::getFeatures($idLang) and values live in
     * feature_value_lang - so every read needs a language. The shop default is used whenever there is
     * no request language, which is the normal case for the order build: it also runs from the task
     * runner, webhooks and CLI, where Context has no hydrated language and reading ->id would fatal.
     *
     * @return int
     */
    public static function resolveLanguageId()
    {
        $context = \Context::getContext();

        if ($context !== null && !empty($context->language) && !empty($context->language->id)) {
            return (int)$context->language->id;
        }

        return (int)\Configuration::get('PS_LANG_DEFAULT');
    }

    /**
     * Returns one mapping option per product feature defined in the shop, sorted by name.
     *
     * @param int $languageId Language used for the feature names.
     *
     * @return array List of array('value' => 'feature_<id>', 'name' => string).
     */
    public static function getOptions($languageId)
    {
        $options = array();

        foreach (self::getFeatures($languageId) as $feature) {
            if (empty($feature['id_feature']) || !isset($feature['name']) || $feature['name'] === '') {
                continue;
            }

            $options[] = array(
                'value' => self::SOURCE_PREFIX . (int)$feature['id_feature'],
                'name' => $feature['name'],
            );
        }

        usort($options, array(__CLASS__, 'compareByName'));

        return $options;
    }

    /**
     * True when the given mapping source points at a product feature.
     *
     * @param string $source
     *
     * @return bool
     */
    public static function isFeatureSource($source)
    {
        return is_string($source) && strpos($source, self::SOURCE_PREFIX) === 0;
    }

    /**
     * Reads a product's value for the feature referenced by the mapping source.
     *
     * @param string $source Mapping source key, e.g. "feature_3".
     * @param int $productId
     * @param int $languageId
     *
     * @return string Feature value, or an empty string when the product has no value for it.
     */
    public static function getValue($source, $productId, $languageId)
    {
        if (!self::isFeatureSource($source)) {
            return '';
        }

        $featureId = (int)substr($source, strlen(self::SOURCE_PREFIX));
        if ($featureId <= 0 || (int)$productId <= 0) {
            return '';
        }

        try {
            $value = \Db::getInstance()->getValue(
                'SELECT fvl.`value`'
                . ' FROM `' . _DB_PREFIX_ . 'feature_product` fp'
                . ' INNER JOIN `' . _DB_PREFIX_ . 'feature_value_lang` fvl'
                . ' ON fvl.`id_feature_value` = fp.`id_feature_value`'
                . ' AND fvl.`id_lang` = ' . (int)$languageId
                . ' WHERE fp.`id_product` = ' . (int)$productId
                . ' AND fp.`id_feature` = ' . $featureId
            );
        } catch (\Exception $e) {
            Logger::logWarning(
                'Failed to read feature ' . $featureId . ' for product ' . (int)$productId . ': ' . $e->getMessage(),
                'Integration'
            );

            return '';
        }

        return $value === false || $value === null ? '' : trim((string)$value);
    }

    /**
     * Returns every feature defined in the shop.
     *
     * @param int $languageId
     *
     * @return array
     */
    private static function getFeatures($languageId)
    {
        try {
            $features = \Feature::getFeatures((int)$languageId);
        } catch (\Exception $e) {
            Logger::logWarning('Failed to load product features: ' . $e->getMessage(), 'Integration');

            return array();
        }

        return is_array($features) ? $features : array();
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
