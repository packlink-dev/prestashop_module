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
        <div class="col-md-12 pl-sm-margin-bottom">
          <p style="font-size: 12px">
            {l s='This shipping service supports delivery to pre-defined drop-off locations. Please choose location that suits you the most by clicking on the "Select drop-off location" button.' mod='packlink' }
          </p>
        </div>
      </div>
      <div class="row pl-sm-margin-bottom">
        <div class="col-xs-5">
          <span id="pl-message" class="pl-message"></span>
        </div>
        <div class="col-xs-7">
          <div class="btn btn-primary pull-right" id="pl-dropoff-button">
          </div>
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
    <div class="pl-close-modal" id="pl-close-modal-btn">X</div>
    <iframe src="https://pro.packlink.fr/index.html?lang=en" height="100%" width="100%" id="pl-map-frame"></iframe>
  </div>
</div>

<script>
  Packlink.trans = {
    select: "{l s='Select drop-off location' mod='packlink'}",
    change: "{l s='Change drop-off location' mod='packlink'}",
    address: "{l s='Package will be delivered to:' mod='packlink'}",
    wrongAddress: "{l s='There are no delivery locations available for your delivery address. Please change your address.' mod='packlink'}"
  };

  Packlink.checkOut = new Packlink.CheckOutController(
      JSON.parse('{$configuration|escape:'html':'UTF-8'}'.replace(/&quot;/g, '"'))
  );

  function checkLoadStatus() {
    let elements = document.getElementsByClassName('delivery_option_radio');
    if (elements.length === 0) {
      setTimeout(checkLoadStatus, 100);
    } else {
      Packlink.checkOut.init();
    }
  }

  checkLoadStatus();
</script>