var Packlink = window.Packlink || {};

(function () {
    function ShippingService17Constructor() {
        let dropoffElement = null;
        let isDisabled = false;

        this.getDropOffShippingMethods = getDropOffShippingMethods;
        this.hideDropOff = hideDropOff;
        this.showDropOff = showDropOff;
        this.setMessage = setMessage;
        this.enableSubmit = enableSubmit;
        this.disableSubmit = disableSubmit;
        this.changeBtnText = changeBtnText;
        this.showCODMessage = showCODMessage;
        this.hideCODMessage = hideCODMessage;
        this.markCashOnDeliveryMethods = markCashOnDeliveryMethods;

        /**
         * Returns radio buttons of drop off shipping methods.
         *
         * @param referenceIds
         * @param cashOnDeliveryReferences
         * @return {Array}
         */
        function getDropOffShippingMethods(referenceIds, cashOnDeliveryReferences) {
            let result = [];

            let inputElements = document.getElementsByTagName('input');
            if (!referenceIds.length || !inputElements.length) {
                return result;
            }

            let codKeys = [];
            let codPrices = {};
            if (cashOnDeliveryReferences) {
                codKeys = Object.keys(cashOnDeliveryReferences).filter(k => k !== "");
                codKeys.forEach(k => codPrices[k] = cashOnDeliveryReferences[k]);
            }

            for (let element of inputElements) {
                if (element.type === 'radio' && element.getAttribute('name').includes('delivery_option')) {
                    let id = trimString(element.value);

                    if (referenceIds.indexOf(id) !== -1) {
                        element.setAttribute('data-pl-dropoff', 'true');
                        element.setAttribute('data-pl-id', id);
                    }

                    if (codKeys.indexOf(id) !== -1) {
                        element.setAttribute('data-pl-cod', 'true');
                        element.setAttribute('data-pl-cod-price', codPrices[id]);
                    }

                    result.push(element);
                }
            }

            return result;
        }

        function markCashOnDeliveryMethods(cashOnDeliveryReferences) {
            let result = [];

            if (!cashOnDeliveryReferences || !Object.keys(cashOnDeliveryReferences).length) {
                return result;
            }

            let inputElements = document.getElementsByTagName('input');
            if (!inputElements.length) {
                return result;
            }

            let codKeys = Object.keys(cashOnDeliveryReferences).filter(k => k !== "");
            let codPrices = {};
            codKeys.forEach(k => codPrices[k] = cashOnDeliveryReferences[k]);

            for (let element of inputElements) {
                if (element.type === 'radio' && element.getAttribute('name').includes('delivery_option')) {
                    let id = trimString(element.value);

                    if (codKeys.indexOf(id) !== -1) {
                        element.setAttribute('data-pl-cod', 'true');
                        element.setAttribute('data-pl-cod-price', codPrices[id]);
                        result.push(element);
                    }
                }
            }

            return result;
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
         * Shows drop off.
         *
         * @param {function} clickedCallback
         * @param {Element} dropoff
         * @param {string} btnMsg
         */
        function showDropOff(clickedCallback, dropoff, btnMsg) {
            let insertedElements = document.querySelectorAll('.pl-drop-off-inserted');
            for (let insertedElement of insertedElements) {
                insertedElement.remove();
            }

            if (dropoffElement) {
                dropoffElement.remove();
            }

            dropoffElement = document.getElementById('pl-dropoff').cloneNode(true);
            dropoffElement.classList.add('pl-drop-off-inserted');

            let point = dropoff.parentElement;
            while (!point.classList
                || !(point.classList.contains('delivery-option') || point.classList.contains('checkout-delivery-line')
                    || point.classList.contains('delivery-options__item'))
                ) {
                point = point.parentElement;
            }

            point.after(dropoffElement);

            let button = dropoffElement.querySelector('#pl-dropoff-button');
            button.addEventListener('click', clickedCallback);
            button.title = btnMsg;
            button.innerHTML = '<span>' + btnMsg + '</span>';
        }

        /**
         * Shows COD message.
         *
         * @param {Element} dropoff
         * @param {string} paymentMethod
         */
        function showCODMessage(dropoff, paymentMethod) {
            let existing = document.querySelectorAll('.pl-cod-inserted');
            existing.forEach(el => el.remove());

            let codElement = document.getElementById('pl-cod').cloneNode(true);
            codElement.classList.add('pl-cod-inserted');

            let codPrice = dropoff.getAttribute('data-pl-cod-price') || 0;

            if (codPrice === "0") {
                return;
            }

            codElement.querySelector('p').innerHTML =
                `This service supports ${paymentMethod}. If you choose the ${paymentMethod} payment method, additional fee of ${codPrice} will be applied.`;

            let point = dropoff.parentElement;
            while (!point.classList
                || !(point.classList.contains('delivery-option') || point.classList.contains('checkout-delivery-line'))
                ) {
                point = point.parentElement;
            }

            point.after(codElement);
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

        function hideCODMessage() {
            let existing = document.querySelectorAll('.pl-cod-inserted');
            existing.forEach(el => el.remove());
        }

        /**
         * Enables submit button.
         */
        function enableSubmit() {
            isDisabled = false;

            let submitBtn = document.getElementsByName('confirmDeliveryOption');
            if (submitBtn.length) {
                submitBtn[0].classList.remove('pl-checkout-disabled');
            }

            let element = document.getElementById('checkout-payment-step');
            if (element) {
                element.classList.remove('pl-disabled');
            }
        }

        /**
         * Disables submit button.
         */
        function disableSubmit() {
            isDisabled = true;

            var submitBtn = document.getElementsByName('confirmDeliveryOption');
            if (submitBtn.length) {
                submitBtn[0].classList.add('pl-checkout-disabled');
            }

            clickOnDeliveryStep();
            setTimeout(disablePaymentTab, 100)
        }

        /**
         * Sets button text.
         *
         * @param {string} btnMsg
         */
        function changeBtnText(btnMsg) {
            let button = dropoffElement.querySelector('#pl-dropoff-button');
            button.innerHTML = btnMsg;
            button.title = btnMsg;
        }

        // Private utility methods.

        function clickOnDeliveryStep() {
            let deliveryStep = document.getElementById('checkout-delivery-step');
            if (deliveryStep) {
                deliveryStep.click();
            }
        }

        /**
         * Disables payment tab.
         */
        function disablePaymentTab() {
            if (isDisabled) {
                clickOnDeliveryStep();
                let element = document.getElementById('checkout-payment-step');

                if (element) {
                    element.classList.add('pl-disabled')
                } else {
                    setTimeout(disablePaymentTab, 100)
                }
            }
        }

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

    Packlink.shippingService = new ShippingService17Constructor();
})();
