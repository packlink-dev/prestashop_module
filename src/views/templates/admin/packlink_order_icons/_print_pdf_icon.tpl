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

{* Generate HTML code for extending existing template for printing Invoice Icon with shipment labels icons *}
<p id="pl-label-printed" hidden>{l s='Printed' mod='packlink'}</p>
<p id="pl-label-ready" hidden>{l s='Ready' mod='packlink'}</p>
<p id="pl-disable-popup" hidden>
  {l s='Please disable pop-up blocker on this page in order to bulk open shipment labels' mod='packlink'}
</p>
<p id="pl-print-labels-url" hidden>
  {html_entity_decode($printLabelsUrl|escape:'html':'UTF-8')}
</p>
<span class="btn-group-action">
    <span class="btn-group">
    {if Configuration::get('PS_INVOICE') && $order->invoice_number}
      <a class="btn btn-default _blank"
         href="{html_entity_decode($link->getAdminLink('AdminPdf')|escape:'html':'UTF-8')}&amp;submitAction=generateInvoicePDF&amp;id_order={$order->id}"
      >
                 <i class="icon-file-text"></i>
            </a>
    {/if}
      {foreach from=$labels item=label}
        <a class="btn btn-default _blank shipment-label" href="{html_entity_decode($label->link|escape:'html':'UTF-8')}"
           data-link="{html_entity_decode($label->link|escape:'html':'UTF-8')}"
           data-order="{$orderId|escape:'html':'UTF-8'}"
           data-print-label-url="{html_entity_decode($printLabelUrl|escape:'html':'UTF-8')}"
           onclick="plPrintLabelOnOrdersPage(this)"
                {if $label->printed}
                  title="{l s='Printed' mod='packlink'}" style="color: #c3c3c3"
                {else}
                  title="{l s='Ready' mod='packlink'}"
                {/if}
              >
                <i class="icon-tag"
                  {if $label->printed}
                    style="color: #c3c3c3"
                  {/if}
                ></i>
      </a>
      {/foreach}
      {* Generate HTML code for printing Delivery Icon with link *}
      {if $order->delivery_number}
        <a class="btn btn-default _blank"
           href="{html_entity_decode($link->getAdminLink('AdminPdf')|escape:'html':'UTF-8')}&amp;submitAction=generateDeliverySlipPDF&amp;id_order={$order->id}">
            <i class="icon-truck"></i>
        </a>
      {/if}
    </span>
</span>
