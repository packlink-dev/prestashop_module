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

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Logeecom\Infrastructure\Logger\Logger;
use Packlink\BusinessLogic\Configuration;

class ConfigurationService extends Configuration
{
    /**
     * @inheritdoc
     */
    const SCHEDULER_TIME_THRESHOLD = 86400;
    /**
     * @inheritdoc
     */
    const MIN_LOG_LEVEL = Logger::ERROR;

    /**
     * Returns current system identifier.
     *
     * @return string Current system identifier.
     */
    public function getCurrentSystemId()
    {
        // support multi-shop feature so that everything is installed for all shops.
        return \Configuration::get('PS_SHOP_DEFAULT');
    }

    /**
     * Returns web-hook callback URL for current system.
     *
     * @return string Web-hook callback URL.
     */
    public function getWebHookUrl()
    {
        return \Context::getContext()->link->getModuleLink(
            'packlink',
            'webhooks',
            array(),
            null,
            null,
            \Configuration::get('PS_SHOP_DEFAULT')
        );
    }

    /**
     * Retrieves integration name.
     *
     * @return string Integration name.
     */
    public function getIntegrationName()
    {
        return 'PrestaShop';
    }

    /**
     * Returns order draft source.
     *
     * @return string
     */
    public function getDraftSource()
    {
        return 'module_prestashop';
    }

    /**
     * Returns async process starter url, always in http.
     *
     * @param string $guid Process identifier.
     *
     * @return string Formatted URL of async process starter endpoint.
     */
    public function getAsyncProcessUrl($guid)
    {
        $params = array('guid' => $guid);

        return \Context::getContext()->link->getModuleLink(
            'packlink',
            'asyncprocess',
            $params,
            null,
            null,
            \Configuration::get('PS_SHOP_DEFAULT')
        );
    }
}
