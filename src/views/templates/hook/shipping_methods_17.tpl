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

<div class="pl-template">
  <div class="row pl-dropof" id="pl-dropoff">
    <div class="col-md-12">
      <div class="row">
        <div class="col-md-12">
          <p>
            {l s='This shipping service supports delivery to pre-defined drop-off locations. Please choose location that suits you the most by clicking on the "Select drop-off location" button.' mod='packlink'}
          </p>
        </div>
      </div>
      <div class="row pl-sm-margin-bottom">
        <div class="col-xs-6">
          <span id="pl-message" class="pl-message"></span>
        </div>
        <div class="col-xs-6">
          <button type="button" class="btn btn-primary float-xs-right pl-17-button" id="pl-dropoff-button">
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="pl-input-mask hidden" id="pl-map-modal">
  <div class="pl-map-modal" id="pl-modal-content">
    <div class="pl-modal-spinner-wrapper disabled" id="pl-modal-spinner">
      <div class="pl-modal-spinner"></div>
    </div>
    <div class="pl-close-modal" id="pl-close-modal-btn"><i class="material-icons">close</i></div>

    <location-picker>
      <div class="lp-content" data-lp-id="content">
        <div class="lp-locations">
          <div class="lp-input-wrapper">
            <div class="input">
              <input type="text" title="" required data-lp-id="search-box">
              <span class="label" data-lp-id="search-box-label"></span>
            </div>
          </div>

          <div data-lp-id="locations"></div>
        </div>
      </div>
    </location-picker>

  </div>
</div>


<location-picker-template>
  <div class="lp-template" id="template-container">
    <div data-lp-id="working-hours-template" class="lp-hour-wrapper">
      <div class="day" data-lp-id="day">
      </div>
      <div class="hours" data-lp-id="hours">
      </div>
    </div>

    <div class="lp-location-wrapper" data-lp-id="location-template">
      <div class="radio-button lp-collapse">
        <div class="lp-radio"></div>
      </div>
      <div class="composite lp-expand">
        <div class="street-name uppercase" data-lp-id="composite-address"></div>
        <div class="lp-working-hours-btn excluded" data-lp-composite data-lp-id="show-composite-working-hours-btn"></div>
        <div data-lp-id="composite-working-hours" class="lp-working-hours">

        </div>
      </div>
      <div class="name uppercase lp-collapse" data-lp-id="location-name"></div>
      <div class="street lp-collapse">
        <div class="street-name uppercase" data-lp-id="location-street"></div>
        <div class="lp-working-hours-btn excluded" data-lp-id="show-working-hours-btn"></div>
        <div data-lp-id="working-hours" class="lp-working-hours">

        </div>
      </div>
      <div class="city uppercase lp-collapse" data-lp-id="location-city">
      </div>
      <div class="lp-select-column">
        <div class="lp-select-button excluded" data-lp-id="select-btn"></div>
      </div>
      <a class="excluded" href="#" data-lp-id="show-on-map" target="_blank">
        <div class="lp-show-on-map-btn excluded"></div>
      </a>
    </div>
  </div>
</location-picker-template>

<script>
  Packlink.trans = {
    select: "{l s='Select drop-off location' mod='packlink'}",
    change: "{l s='Change drop-off location' mod='packlink'}",
    address: "{l s='Package will be delivered to:' mod='packlink'}",
    wrongAddress: "{l s='There are no delivery locations available for your delivery address. Please change your address.' mod='packlink'}"
  };

  Packlink.checkOut = new Packlink.CheckOutController(
      JSON.parse('{$configuration|escape:'UTF-8'}'.replace(/&quot;/g, '"').replace(/&amp;/g, '&'))
  );

  Packlink.checkOut.init();
</script>