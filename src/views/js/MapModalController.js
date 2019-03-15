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
  function MapModalControllerConstructor(configuration) {
    this.display = display;
    this.close = close;

    let modal = null;
    let closeButton = null;
    let ajaxService = Packlink.ajaxService;

    let locations = [];

    let selectedId;
    let lang;

    function display() {
      modal = document.getElementById('pl-map-modal');
      modal.classList.remove('hidden');

      document.getElementById('pl-modal-spinner').classList.remove('disabled');

      closeButton = document.getElementById('pl-close-modal-btn');
      closeButton.addEventListener('click', closeClickedListener);

      lang = configuration.lang ? configuration.lang : 'en';

      ajaxService.post(
          configuration.getUrl,
          {
            method: 'getLocations',
            methodId: configuration.methodId
          },
          getLocationsSuccessHandler
      )
    }

    /**
     * Closes modal.
     */
    function close() {
      modal.classList.add('hidden');
      closeButton.removeEventListener('click', closeClickedListener);
    }

    /**
     * Handles communication with location picker library.
     *
     * @param id
     */
    function onDropoffSelected(id) {
      selectedId = id;
      selectDropoff();
    }

    /**
     * Location retrieve success handler.
     *
     * @param response
     */
    function getLocationsSuccessHandler(response) {
      locations = response;

      if (!locations || locations.length === 0) {
        configuration.onComplete({type: 'no-locations'});
      }

      submitLocations();
    }

    /**
     * Submits location to location library.
     */
    function submitLocations() {
      Packlink.locationPicker.display(locations, onDropoffSelected, configuration.dropOffId, lang);
      document.getElementById('pl-modal-spinner').classList.add('disabled');
    }

    /**
     * Handles close button clicked event.
     */
    function closeClickedListener() {
      configuration.onComplete({type: 'close'});
    }

    /**
     * Selects dropoff location.
     */
    function selectDropoff() {
      document.getElementById('pl-modal-spinner').classList.remove('disabled');

      ajaxService.post(
          configuration.getUrl,
          {method: 'postSelectedDropoff', carrierId: configuration.carrierId, dropOff: getSelectedDropoff()},
          selectDropoffSuccessHandler
      );
    }

    /**
     * Select dropoff location success callback.
     */
    function selectDropoffSuccessHandler() {
      document.getElementById('pl-modal-spinner').classList.add('disabled');
      configuration.onComplete(
          {
            type: 'success',
            address: getAddressString(getSelectedDropoff()),
            dropOff: getSelectedDropoff()
          }
      )
    }

    /**
     * Retrieves selected dropoff.
     *
     * @return {object}
     */
    function getSelectedDropoff() {
      let dropOff = {};

      for (let loc of locations) {
        if (loc.id === selectedId) {
          dropOff = loc;
          break;
        }
      }

      return dropOff;
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

  Packlink.MapModalController = MapModalControllerConstructor;
})();
