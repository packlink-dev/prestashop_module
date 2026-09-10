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
        this.markDdpMethods = markDdpMethods;
        this.showDdpMessage = showDdpMessage;
        this.hideDdpMessage = hideDdpMessage;
        this.isDdpDisplayCorrect = isDdpDisplayCorrect;

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
                || !(point.classList.contains('delivery-option') || point.classList.contains('delivery_option') || point.classList.contains('checkout-delivery-line'))
                ) {
                point = point.parentElement;
            }

            point.after(dropoffElement);

            let button = dropoffElement.querySelector('#pl-dropoff-button');
            button.addEventListener('click', clickedCallback);
            button.innerHTML = '<span>' + btnMsg + '</span>';
            button.title = btnMsg;
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
            let submitBtn = document.getElementsByName('processCarrier');
            if (submitBtn.length) {
                submitBtn[0].classList.remove('pl-checkout-disabled');
            }
        }

        /**
         * Disables submit button.
         */
        function disableSubmit() {
            let submitBtn = document.getElementsByName('processCarrier');
            if (submitBtn.length) {
                submitBtn[0].classList.add('pl-checkout-disabled');
            }
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
            button.innerHTML = '<span>' + btnMsg + '</span>';
            button.title = btnMsg;
        }

        /**
         * Tags the delivery-option radios that carry duties, with the duty portion of their price.
         *
         * @param {Object} ddpReferences Duty amount keyed by carrier reference id.
         * @param {Object} [transportAmounts] Transport portion keyed by carrier reference id.
         * @return {Array} All delivery-option radio elements, duties-paid ones tagged.
         */
        function markDdpMethods(ddpReferences, transportAmounts) {
            let result = [];

            if (!ddpReferences) {
                return result;
            }

            let ddpKeys = filterEmptyKeys(ddpReferences);
            if (!ddpKeys.length) {
                return result;
            }

            let inputElements = document.getElementsByTagName('input');
            for (let element of inputElements) {
                if (element.type === 'radio'
                    && (element.getAttribute('name') || '').indexOf('delivery_option') !== -1
                ) {
                    let id = trimString(element.value);

                    if (ddpKeys.indexOf(id) !== -1) {
                        element.setAttribute('data-pl-ddp', 'true');
                        element.setAttribute('data-pl-ddp-price', ddpReferences[id]);

                        if (transportAmounts && transportAmounts[id]) {
                            element.setAttribute('data-pl-transport-price', transportAmounts[id]);
                        }
                    }

                    result.push(element);
                }
            }

            return result;
        }

        /**
         * Shows the duties-paid note under the selected delivery option. PrestaShop 1.6 renders the
         * cart summary on another step (or rewrites it over AJAX on one-page checkout, which would
         * silently discard an injected row), so the note is anchored to the option itself — the same
         * insertion point the drop-off section uses. Presentation only: the duty amount is already
         * inside the carrier's price, so the note explains that price and is never added to any total.
         *
         * @param {Element} option The selected delivery-option radio.
         * @param {string} label Translated 'Delivery Duty Paid' label.
         */
        function showDdpMessage(option, label) {
            hideDdpMessage();

            let price = option.getAttribute('data-pl-ddp-price');
            if (!price || price === '0') {
                return;
            }

            let point = findDeliveryOptionContainer(option);
            if (!point) {
                return;
            }

            let transport = option.getAttribute('data-pl-transport-price');
            // With a transport figure the note breaks the carrier price out ("<transport> + <duty>
            // <label>"); without one it only names the duty portion of the price.
            let text = transport
                ? transport + ' + ' + price + ' ' + label
                : label + ': ' + price;

            point.after(createDdpMessageElement(text, price, trimString(option.value)));
        }

        /**
         * Removes every inserted duties-paid note.
         */
        function hideDdpMessage() {
            let insertedElements = document.querySelectorAll('.pl-ddp-inserted');
            for (let insertedElement of insertedElements) {
                insertedElement.remove();
            }
        }

        /**
         * Whether the duties-paid presentation on the page matches the given costs: every duties-paid
         * option is tagged, and the note is present exactly when the selected option carries a duty —
         * showing that option's amount. Lets the checkout controller skip re-applying labels when the
         * page is already right.
         *
         * @param {Object} ddpCosts Duty amount keyed by carrier reference id.
         * @return {boolean}
         */
        function isDdpDisplayCorrect(ddpCosts) {
            let keys = ddpCosts ? filterEmptyKeys(ddpCosts) : [];
            if (!keys.length) {
                return true;
            }

            let checkedDdpRadio = null;

            let inputElements = document.getElementsByTagName('input');
            for (let element of inputElements) {
                if (element.type === 'radio'
                    && (element.getAttribute('name') || '').indexOf('delivery_option') !== -1
                    && keys.indexOf(trimString(element.value)) !== -1
                ) {
                    if (element.getAttribute('data-pl-ddp') !== 'true') {
                        return false;
                    }

                    if (element.checked) {
                        checkedDdpRadio = element;
                    }
                }
            }

            let notes = document.querySelectorAll('.pl-ddp-inserted');

            if (checkedDdpRadio === null) {
                return notes.length === 0;
            }

            let price = checkedDdpRadio.getAttribute('data-pl-ddp-price');
            if (!price || price === '0') {
                // Zero duty renders no note, so none on the page is the correct state.
                return notes.length === 0;
            }

            return notes.length === 1
                && notes[0].getAttribute('data-pl-ddp-amount') === price
                && notes[0].getAttribute('data-pl-ddp-for') === trimString(checkedDdpRadio.value);
        }

        // Private utility methods.

        /**
         * Walks from a delivery-option radio up to the option's container element — the insertion
         * point the drop-off section also uses. Returns null when the theme's markup is not
         * recognised, in which case nothing is injected rather than something broken.
         *
         * @param {Element} option
         * @return {Element|null}
         */
        function findDeliveryOptionContainer(option) {
            let point = option.parentElement;
            while (point
                && (!point.classList
                    || !(point.classList.contains('delivery-option')
                        || point.classList.contains('delivery_option')
                        || point.classList.contains('checkout-delivery-line')))
                ) {
                point = point.parentElement;
            }

            return point;
        }

        /**
         * Builds the duties-paid note element. The text goes in through textContent, so a label or
         * amount can never inject markup.
         *
         * @param {string} text
         * @param {string} amount Formatted duty amount, stamped for the correctness check.
         * @param {string} optionId Carrier reference id the note belongs to.
         * @return {Element}
         */
        function createDdpMessageElement(text, amount, optionId) {
            let element = document.createElement('div');
            element.className = 'row pl-ddp-message pl-ddp-inserted';
            element.setAttribute('data-pl-ddp-amount', amount);
            element.setAttribute('data-pl-ddp-for', optionId);

            let column = document.createElement('div');
            column.className = 'col-md-12';

            let message = document.createElement('p');
            message.style.fontSize = '12px';
            message.textContent = text;

            column.appendChild(message);
            element.appendChild(column);

            return element;
        }

        /**
         * Returns the object's keys without the empty-string key.
         *
         * @param {Object} data
         * @return {Array}
         */
        function filterEmptyKeys(data) {
            let keys = [];

            for (let key of Object.keys(data)) {
                if (key !== '') {
                    keys.push(key);
                }
            }

            return keys;
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

    Packlink.shippingService = new ShippingService16Constructor();
})();