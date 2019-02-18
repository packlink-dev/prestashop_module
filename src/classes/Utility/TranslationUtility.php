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
