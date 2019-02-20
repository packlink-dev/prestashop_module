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

var Packlink = window.Packlink || {};

(function () {

  function CheckOutControllerConstructor(configuration) {
    let shippingService = Packlink.shippingService;

    this.init = init;

    let currentMethod = null;
    let dropOffIds = [];

    let mapController = null;

    function init() {
      dropOffIds = Object.keys(configuration.dropoffIds);

      let dropOffs = shippingService.getDropOffShippingMethods(dropOffIds);

      let selectedLocation = configuration.selectedLocation;
      let selectedCarrier = configuration.selectedCarrier;

      for (let dropOff of dropOffs) {
        if (dropOff.checked && dropOff.getAttribute('data-pl-dropoff') === 'true') {
          currentMethod = dropOff;

          if (currentMethod.getAttribute('data-pl-id') === selectedCarrier) {
            showDropoff(Packlink.trans.change);
            shippingService.setMessage(Packlink.trans.address + '<br /> <i>' + getAddressString(selectedLocation) + '</i>');
          } else {
            showDropoff(Packlink.trans.select, true);
          }
        }

        dropOff.addEventListener('change', dropoffChangedHandler);
      }
    }

    /**
     * Handles changed event
     *
     * @param event
     */
    function dropoffChangedHandler(event) {
      currentMethod = event.target;

      if (currentMethod.getAttribute('data-pl-dropoff') === 'true') {
        showDropoff(Packlink.trans.select, true);
      } else {
        hideDropoff();
      }
    }

    /**
     * Handles click event on select dropoff button.
     */
    function dropOffButtonClickedHandler() {
      let id = currentMethod.getAttribute('data-pl-id');

      mapController = new Packlink.MapModalController(
          {
            getUrl: configuration.getLocationsUrl,
            methodId: configuration.dropoffIds[id],
            carrierId: id,
            onComplete: modalCompleteCallback
          }
      );

      mapController.display();
    }

    /**
     * Handles callback from modal.
     *
     * @param payload
     */
    function modalCompleteCallback(payload) {
      mapController.close();
      mapController = null;

      if (payload.type === 'no-locations') {
        shippingService.setMessage(Packlink.trans.wrongAddress);
      }

      if (payload.type === 'success') {
        shippingService.setMessage(Packlink.trans.address + '<br/> <i>' + payload.address + '</i>');
        shippingService.changeBtnText(Packlink.trans.change);
        shippingService.enableSubmit();
      }
    }

    /**
     * Shows select dropoff section.
     *
     * @param {string} btnMsg
     * @param {boolean} [disable]
     */
    function showDropoff(btnMsg, disable) {
      shippingService.showDropOff(dropOffButtonClickedHandler, currentMethod, btnMsg);
      shippingService.setMessage('');

      if (disable) {
        shippingService.disableSubmit();
      }
    }

    /**
     * Hides dropoff select section.
     */
    function hideDropoff() {
      shippingService.hideDropOff();
      shippingService.enableSubmit();
    }

    /**
     * Returns formatted address.
     *
     * @param {object} location
     * @return {string}
     */
    function getAddressString(location) {
      return location['name'] + ', ' + location['address'] + ', ' + location['zip'] + ', ' + location['city'];
    }
  }

  Packlink.CheckOutController = CheckOutControllerConstructor;
})();