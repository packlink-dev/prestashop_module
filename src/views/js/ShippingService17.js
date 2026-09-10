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
                if (element.type === 'radio' && element.getAttribute('name').includes('delivery_option')) {
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
                    }

                    result.push(element);
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

            let point = dropoff.closest(
                '.js-delivery-option, .delivery-option, .delivery-option__item, .checkout-delivery-line, .delivery-options__item'
            );

            if (!point) {
                return;
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

            codElement.querySelector('p').textContent =
                `This service supports ${paymentMethod}. If you choose the ${paymentMethod} payment method, additional fee of ${codPrice} will be applied.`;

            let point = dropoff.closest(
                '.js-delivery-option, .delivery-option, .delivery-option__item, .checkout-delivery-line, .delivery-options__item'
            );

            if (!point) {
                return;
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
         * Tags the delivery-option radios that carry duties, with the duty portion of their price.
         *
         * @param {Object} ddpReferences Duty amount keyed by carrier reference id.
         * @return {Array} The tagged radio elements.
         */
        function markDdpMethods(ddpReferences, transportAmounts) {
            let result = [];

            if (!ddpReferences || !Object.keys(ddpReferences).length) {
                return result;
            }

            let inputElements = document.getElementsByTagName('input');
            if (!inputElements.length) {
                return result;
            }

            let ddpKeys = Object.keys(ddpReferences).filter(k => k !== "");

            for (let element of inputElements) {
                if (element.type === 'radio' && element.getAttribute('name').includes('delivery_option')) {
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
         * Syncs the summary panel with the selected duties-paid option. Presentation only: the amount
         * is already inside the carrier price, so the row explains that price rather than adding to it.
         *
         * @param {Element} option The selected delivery-option radio.
         * @param {string} label Translated 'Delivery Duty Paid' label.
         */
        function showDdpMessage(option, label) {
            let price = option.getAttribute('data-pl-ddp-price');
            if (!price || price === "0") {
                hideDdpSummaryRow();

                return;
            }

            showDdpSummaryRow(price, label, option.getAttribute('data-pl-transport-price'));
        }

        /**
         * Whether the duties-paid presentation on the page matches the given costs: every duties-paid
         * option is marked and labelled, and the summary row is present exactly when the selected
         * option is a duties-paid one. Lets the checkout observer detect that a theme re-render
         * destroyed the labels, without re-applying on every unrelated mutation.
         *
         * @param {Object} ddpCosts Duty amount keyed by carrier reference id.
         * @return {boolean}
         */
        function isDdpDisplayCorrect(ddpCosts) {
            let keys = ddpCosts ? Object.keys(ddpCosts).filter(k => k !== '') : [];
            if (!keys.length) {
                return true;
            }

            let ddpRadios = [];
            let checkedDdpRadio = null;

            let inputElements = document.getElementsByTagName('input');
            for (let element of inputElements) {
                if (element.type === 'radio'
                    && (element.getAttribute('name') || '').includes('delivery_option')
                    && keys.indexOf(trimString(element.value)) !== -1
                ) {
                    ddpRadios.push(element);
                    if (element.checked) {
                        checkedDdpRadio = element;
                    }
                }
            }

            for (let radio of ddpRadios) {
                if (radio.getAttribute('data-pl-ddp') !== 'true') {
                    return false;
                }
            }

            let summaryRows = document.querySelectorAll('.pl-ddp-summary-inserted');
            // Never count our own inserted row as a split shipping line.
            let splitRows = document.querySelectorAll('[data-pl-ddp-split]:not(.pl-ddp-summary-inserted)');

            if (checkedDdpRadio === null) {
                // No duty applies: no row of ours, and the shipping line back to its own figure.
                return summaryRows.length === 0 && splitRows.length === 0;
            }

            // With a duties-paid option selected: one row showing that option's amount, and the shipping
            // line split whenever there is a transport figure to put on it.
            let transport = checkedDdpRadio.getAttribute('data-pl-transport-price');
            let splitOk = !transport || splitRows.length === 1;

            if (summaryRows.length === 1
                && splitOk
                && summaryRows[0].getAttribute('data-pl-ddp-amount')
                    === checkedDdpRadio.getAttribute('data-pl-ddp-price')
            ) {
                return true;
            }

            // Nothing to fix while the panel itself is absent from the page.
            return summaryRows.length === 0 && findSummaryShippingRow() === null;
        }

        function hideDdpMessage() {
            hideDdpSummaryRow();
        }

        /**
         * Adds a 'Delivery Duty Paid' row to the order-summary panel, mirroring the shipping row's own
         * markup so it inherits the theme's styling.
         *
         * Display only: the amount is already inside the shipping figure above it, so this row explains
         * that figure and must never be added to the total.
         *
         * @param {string} price Formatted duty amount.
         * @param {string} label Translated 'Delivery Duty Paid' label.
         */
        function showDdpSummaryRow(price, label, transportPrice) {
            hideDdpSummaryRow();

            let shippingRow = findSummaryShippingRow();
            if (!shippingRow) {
                // Summary panel not rendered yet; the checkout observer re-applies once it appears.
                return;
            }

            // Split the shipping line: transport on it, duty on the row inserted below. The two sum to
            // the carrier price already inside the order total, so the panel adds up exactly. The order
            // total itself is never touched — only how the shipping figure is broken out.
            if (transportPrice) {
                let shippingValue = shippingRow.querySelector('.cart-summary__value, .value, dd');
                if (shippingValue) {
                    if (shippingRow.getAttribute('data-pl-ddp-original') === null) {
                        shippingRow.setAttribute('data-pl-ddp-original', shippingValue.textContent);
                    }

                    shippingValue.textContent = transportPrice;
                    shippingRow.setAttribute('data-pl-ddp-split', '1');
                }
            }

            let row = shippingRow.cloneNode(true);
            row.classList.add('pl-ddp-summary-inserted');
            row.removeAttribute('id');
            // The clone inherits the split markers from the row it was copied from. Leaving them on it
            // makes the correctness check count two split rows, never agree, and re-apply forever —
            // which locks the page up. Strip them.
            row.removeAttribute('data-pl-ddp-split');
            row.removeAttribute('data-pl-ddp-original');
            // Stamp the shown amount so a correctness check can spot a stale row without re-rendering.
            row.setAttribute('data-pl-ddp-amount', price);

            // Drop any sub-label the theme puts under the shipping row (e.g. the carrier name).
            row.querySelectorAll('small, .sub, .carrier-name').forEach(el => el.remove());

            let labelEl = row.querySelector('.cart-summary__label, .label, dt');
            let valueEl = row.querySelector('.cart-summary__value, .value, dd, .price');

            if (!labelEl || !valueEl || labelEl === valueEl) {
                // Unknown row markup: fall back to the first two child elements.
                labelEl = row.children.length > 1 ? row.children[0] : null;
                valueEl = row.children.length > 1 ? row.children[row.children.length - 1] : null;

                if (!labelEl || !valueEl) {
                    return;
                }
            }

            labelEl.textContent = label;
            valueEl.textContent = price;

            shippingRow.after(row);
        }

        function hideDdpSummaryRow() {
            let existing = document.querySelectorAll('.pl-ddp-summary-inserted');
            existing.forEach(el => el.remove());

            // Put the combined figure back when duty no longer applies, so a base option never shows a
            // transport-only shipping line.
            let split = document.querySelectorAll('[data-pl-ddp-split]');
            split.forEach(function (row) {
                let original = row.getAttribute('data-pl-ddp-original');
                let value = row.querySelector('.cart-summary__value, .value, dd');

                if (original !== null && value) {
                    value.textContent = original;
                }

                row.removeAttribute('data-pl-ddp-split');
                row.removeAttribute('data-pl-ddp-original');
            });
        }

        /**
         * Finds the shipping line in the order-summary panel across themes, by looking for a summary
         * line whose label mentions shipping or delivery. Returns null when the theme's markup is not
         * recognised, in which case no row is injected rather than a broken one.
         *
         * @return {Element|null}
         */
        function findSummaryShippingRow() {
            // PrestaShop ids the shipping subtotal row, which is stable across themes.
            let row = document.getElementById('cart-subtotal-shipping');
            if (row) {
                return row;
            }

            // Older/other themes: the shipping row inside the subtotals container.
            let containers = document.querySelectorAll(
                '.js-cart-summary-subtotals-container, .cart-summary__subtotals, .cart-summary-subtotals-container'
            );

            for (let container of containers) {
                let lines = container.querySelectorAll('.cart-summary__line, .cart-summary-line');
                for (let line of lines) {
                    if (line.classList.contains('pl-ddp-summary-inserted')) {
                        continue;
                    }

                    if (/(shipping|delivery|livraison|env[íi]o|versand|spedizione)/i
                        .test(line.textContent || '')) {
                        return line;
                    }
                }
            }

            return null;
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

            var paymentStep = document.getElementById('checkout-payment-step');
            if (paymentStep) {
                paymentStep.classList.add('pl-disabled');
            }

            clickOnDeliveryStep();
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
