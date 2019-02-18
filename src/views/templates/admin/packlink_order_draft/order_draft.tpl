{**
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
 *}

{* Generate HTML code for printing link to order draft on Packlink *}
<span class="btn-group-action">
    <span class="btn-group">
        {* Generate HTML code for printing Delivery Icon with link *}
            <a href="{html_entity_decode($orderDraftLink|escape:'html':'UTF-8')}" target="_blank">
                <img
                        src="{html_entity_decode($imgSrc|escape:'html':'UTF-8')}"
                        alt="{l s='Packlink order draft' mod='packlink'}"
                        style="width: 32px;"
                >
            </a>
    </span>
</span>
