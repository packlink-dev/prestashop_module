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

{* Generate HTML code for adding shipping content *}
<p id="pl-label-printed" hidden>{l s='Printed' mod='packlink'}</p>
<p id="pl-label-ready" hidden>{l s='Ready' mod='packlink'}</p>
<div class="tab-pane" id="packlink-shipping">
  {if $shipping neq null}
    {if !empty($labels)}
      <h4>{l s='Shipment labels' mod='packlink'}</h4>
      <div class="table-responsive">
        <table class="table">
          <thead>
          <tr>
            <th>
              <span class="title_box ">{l s='Date' mod='packlink'}</span>
            </th>
            <th>
              <span class="title_box ">{l s='Number' mod='packlink'}</span>
            </th>
            <th>
              <span class="title_box ">{l s='Status' mod='packlink'}</span>
            </th>
            <th></th>
          </tr>
          </thead>
          <tbody>
          {foreach from=$labels item=label}
            <tr>
              <td>{$label->date|escape:'html':'UTF-8'}</td>
              <td>
                <a href="{html_entity_decode($label->link|escape:'html':'UTF-8')}" target="_blank">
                  {$label->number|escape:'html':'UTF-8'}
                </a>
              </td>
              <td>{$label->status|escape:'html':'UTF-8'}</td>
              <td class="text-right">
                <a class="btn btn-default"
                   href="{html_entity_decode($label->link|escape:'html':'UTF-8')}"
                   title="{l s='Print' mod='packlink'}"
                   data-order="{$orderId|escape:'html':'UTF-8'}"
                   data-link="{html_entity_decode($label->link|escape:'html':'UTF-8')}"
                   data-print-label-url="{html_entity_decode($printLabelUrl|escape:'html':'UTF-8')}"
                   data-label-printed="{$label->printed|escape:'html':'UTF-8'}"
                   onclick="plPrintLabelOnOrderDetailsPage(this)"
                   target="_blank">
                  <i class="icon-print"></i>
                  {l s='Print label' mod='packlink'}
                </a>
              </td>
            </tr>
          {/foreach}
          </tbody>
        </table>
      </div>
    {/if}
    <h4 style="margin-top: 15px;">{l s='Shipment details' mod='packlink'}</h4>
    <table class="table" id="shipping_table">
      <thead>
      <tr>
        <th>
          <span class="title_box">{l s='Carrier logo' mod='packlink'}</span>
        </th>
        <th>
          <span class="title_box">{l s='Carrier' mod='packlink'}</span>
        </th>
        <th>
          <span class="title_box">{l s='Carrier tracking numbers' mod='packlink'}</span>
        </th>
        <th>
        </th>
      </tr>
      </thead>
      <tbody>
      <tr>
        <td>
          {if $shipping->icon neq ''}
            <img
                    src="{html_entity_decode($shipping->icon|escape:'html':'UTF-8')}"
                    alt="{$shipping->name|escape:'html':'UTF-8'}"
                    style="width: 85px;"
            />
          {/if}
        </td>
        <td>
          {$shipping->name|escape:'html':'UTF-8'}
        </td>
        <td>
          {foreach from=$shipping->carrier_tracking_numbers key=index item=tracking_number}
            {$tracking_number|escape:'html':'UTF-8'}
            {if $index !== count($shipping->carrier_tracking_numbers) - 1}, {/if}
          {/foreach}
        </td>
        <td style="text-align: right;">
          {if !empty($shipping->carrier_tracking_numbers)}
            <a
                    class="btn btn-default"
                    href="{html_entity_decode($shipping->carrier_tracking_url|escape:'html':'UTF-8')}"
                    title="{l s='Track it!' mod='packlink'}"
                    target="_blank"
            >
              {l s='Track it!' mod='packlink'}
            </a>
          {/if}
        </td>
      </tr>
      </tbody>
    </table>
    <dl style="margin-top: 15px;">
      {if $shipping->status}
        <dt>{l s='Status' mod='packlink'}</dt>
        <dd style="margin-bottom: 10px">
                <span style="color: grey">
                    <i class="icon-calendar"></i> {$shipping->time|escape:'html':'UTF-8'}
                </span> - <b>{$shipping->status|escape:'html':'UTF-8'}</b>
        </dd>
      {/if}
      {if $shipping->reference}
        <dt>{l s='Packlink reference number' mod='packlink'}</dt>
        <dd style="margin-bottom: 10px">{$shipping->reference|escape:'html':'UTF-8'}</dd>
      {/if}
      {if $shipping->packlink_shipping_price}
        <dt>{l s='Packlink shipping price' mod='packlink'}</dt>
        <dd style="margin-bottom: 10px">{$shipping->packlink_shipping_price|escape:'html':'UTF-8'}</dd>
      {/if}
    </dl>
    {if $shipping->link}
      <a
              class="btn btn-default"
              href="{html_entity_decode($shipping->link|escape:'html':'UTF-8')}"
              title="{l s='View on Packlink PRO' mod='packlink'}"
              target="_blank"
      >
        <i class="icon-eye"></i>
        {l s='View on Packlink PRO' mod='packlink'}
      </a>
    {/if}
  {else}
    <div class="table-responsive">
      <table class="table">
        <tbody>
        <tr>
          <td style="border:none; width: 180px;">
            <img alt="{l s='Packlink PRO Shipping' mod='packlink'}"
                 src="{html_entity_decode($pluginBasePath|escape:'html':'UTF-8')}views/img/logo-pl.svg"
                 width="150px;"
            >
          </td>
          <td style="border:none;">
              <span style="font-weight: normal;">
                  {$message|escape:'html':'UTF-8'}
              </span>
          </td>
          {if $displayDraftButton}
            <td style="border:none;text-align:right;">
              <button
                      type="button"
                      class="btn btn-default"
                      data-order="{$orderId|escape:'html':'UTF-8'}"
                      data-create-draft-url="{html_entity_decode($createDraftUrl|escape:'html':'UTF-8')}"
                      onclick="plCreateOrderDraft(this)"
              >
                <i class="icon-plus-sign"></i> {l s='Create Draft' mod='packlink'}
              </button>
            </td>
          {/if}
        </tr>
        </tbody>
      </table>
    </div>
  {/if}
</div>