<?php

namespace Packlink\PrestaShop\Classes\Utility;

/**
 * Class TranslationUtility
 *
 * @package Packlink\PrestaShop\Classes\Utility
 */
class TranslationUtility
{
    /**
     * Packlink module instance.
     *
     * @var \Module
     */
    private static $moduleInstance;

    /**
     * Method that wraps PrestaShop translation function.
     *
     * @param string $string String that needs to be translated.
     * @param array $args Translation arguments (one or more values to be injected into translated string).
     *
     * @return string Translated string.
     */
    public static function __($string, array $args = array())
    {
        $result = self::getModuleInstance()->l($string);

        if (!empty($args)) {
            $result = vsprintf($result, $args);
        }

        return $result;
    }

    /**
     * Returns module instance.
     *
     * @return \Module
     */
    private static function getModuleInstance()
    {
        if (self::$moduleInstance === null) {
            self::$moduleInstance = \Module::getInstanceByName('packlink');
        }

        return self::$moduleInstance;
    }
}
