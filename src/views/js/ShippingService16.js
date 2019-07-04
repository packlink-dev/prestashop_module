var Packlink = window.Packlink || {};

(function () {
  function ShippingService16Constructor() {
    let dropoffElement = null;

    this.getDropOffShippingMethods = getDropOffShippingMethods;
    this.hideDropOff = hideDropOff;
    this.showDropOff = showDropOff;
    this.setMessage = setMessage;
    this.enableSubmit = enableSubmit;
    this.disableSubmit = disableSubmit;
    this.changeBtnText = changeBtnText;

    /**
     * Returns radio buttons of drop off shipping methods.
     *
     * @param referenceIds
     * @return {Array}
     */
    function getDropOffShippingMethods(referenceIds) {
      let result = [];

      let inputElements = document.getElementsByTagName('input');
      if (!referenceIds.length || !inputElements.length) {
        return result;
      }

      for (let element of inputElements) {
        if (element.type === 'radio') {
          let id = trimString(element.value);

          if (referenceIds.indexOf(id) !== -1) {
            element.setAttribute('data-pl-dropoff', 'true');
            element.setAttribute('data-pl-id', id);
          }

          result.push(element);
        }
      }

      return result;
    }

    /**
     * Shows drop off.
     *
     * @param {function} clickedCallback
     * @param {element} dropoff
     * @param {string} btnMsg
     */
    function showDropOff(clickedCallback, dropoff, btnMsg) {
      if (dropoffElement) {
        dropoffElement.remove();
      }

      dropoffElement = document.getElementById('pl-dropoff').cloneNode(true);

      let point = dropoff.parentElement;
      while (!point.classList || !point.classList.contains('delivery_option')) {
        point = point.parentElement;
      }
      point.after(dropoffElement);

      let button = dropoffElement.querySelector('#pl-dropoff-button');
      button.addEventListener('click', clickedCallback);
      button.innerHTML = btnMsg;
    }

    /**
     * Hides drop off.
     */
    function hideDropOff() {
      if (dropoffElement) {
        dropoffElement.remove();
        dropoffElement = null;
      }
    }

    /**
     * Enables submit button.
     */
    function enableSubmit() {
      document.getElementsByName('processCarrier')[0].classList.remove('disabled');
    }

    /**
     * Disables submit button.
     */
    function disableSubmit() {
      document.getElementsByName('processCarrier')[0].classList.add('disabled');
    }

    /**
     * Sets dropoff message.
     *
     * @param {string} message
     */
    function setMessage(message) {
      if (dropoffElement) {
        dropoffElement.querySelector('#pl-message').innerHTML = message;
      }
    }

    /**
     * Sets button text.
     *
     * @param {string} btnMsg
     */
    function changeBtnText(btnMsg) {
      let button = dropoffElement.querySelector('#pl-dropoff-button');
      button.innerHTML = btnMsg;
    }

    // Private utility methods.

    /**
     * Trims string by removing trailing comma.
     *
     * @param {string} data
     *
     * @return {string}
     */
    function trimString(data) {
      if (typeof data !== 'string') {
        return '';
      }

      if (data.charAt(data.length - 1) === ',') {
        data = data.slice(0, data.length - 1);
      }

      return data;
    }
  }

  Packlink.shippingService = new ShippingService16Constructor();
})();
