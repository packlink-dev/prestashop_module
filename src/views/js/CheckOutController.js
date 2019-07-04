var Packlink = window.Packlink || {};

(function () {

  function CheckOutControllerConstructor(configuration) {
    let shippingService = Packlink.shippingService;

    this.init = init;

    let currentMethod = null;
    let dropOffIds = [];

    let mapController = null;

    let selectedId = null;

    function init() {
      dropOffIds = Object.keys(configuration.dropoffIds);

      let dropOffs = shippingService.getDropOffShippingMethods(dropOffIds);

      let selectedLocation = configuration.selectedLocation;
      let selectedCarrier = configuration.selectedCarrier;

      if (selectedLocation) {
        selectedId = selectedLocation['id'];
      }

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
            onComplete: modalCompleteCallback,
            dropOffId: selectedId,
            lang: configuration.lang
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
        selectedId = payload.dropOff.id;
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