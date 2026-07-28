{*
* Packlink customs product attributes.
* Rendered in the product Shipping tab: natively through displayAdminProductsShippingStepBottom on
* the legacy product form (1.7.x - 8.0), and through displayAdminProductsExtra elsewhere - on the
* new product page (8.1+) the script below moves the panel from the Modules tab into the Shipping
* tab, since that tab exposes no hook there.
* HS code + country of origin are persisted per product in ProductCustomsData and fed into the core
* customs invoice during draft creation; empty values fall back to the configured customs defaults.
*}
<div class="panel card" id="packlink-product-customs">
    <h3 class="card-header">
        <i class="icon-truck"></i> {$packlinkCustomsLabels.title|escape:'html':'UTF-8'}
    </h3>
    <div class="card-body">
        <p class="text-muted">{$packlinkCustomsLabels.description|escape:'html':'UTF-8'}</p>

        <div class="form-group">
            <label for="packlink_hs_code">{$packlinkCustomsLabels.hsCode|escape:'html':'UTF-8'}</label>
            <input type="text"
                   name="packlink_hs_code"
                   id="packlink_hs_code"
                   class="form-control"
                   maxlength="8"
                   inputmode="numeric"
                   autocomplete="off"
                   pattern="[0-9]{ldelim}6,8{rdelim}"
                   value="{$packlinkProductCustoms.hsCode|escape:'html':'UTF-8'}"
                   placeholder="{$packlinkCustomsLabels.hsCodePlaceholder|escape:'html':'UTF-8'}"
                   data-packlink-invalid-message="{$packlinkCustomsLabels.hsCodeInvalid|escape:'html':'UTF-8'}"/>
            {* Filled in by the validator below; kept always-visible-capable so both the legacy panel
               and the new product page show the message without relying on Bootstrap version. *}
            <p class="text-danger packlink-hs-code-error" id="packlink_hs_code_error" style="display:none;"></p>
            <p class="help-block form-text">{$packlinkCustomsLabels.hsCodeHelp|escape:'html':'UTF-8'}</p>
        </div>

        <div class="form-group">
            <label for="packlink_country_of_origin">{$packlinkCustomsLabels.country|escape:'html':'UTF-8'}</label>
            {* Merchant picks the country name; the submitted/stored value is the ISO 3166-1 alpha-2 code. *}
            <select name="packlink_country_of_origin" id="packlink_country_of_origin" class="form-control">
                <option value="">{$packlinkCustomsLabels.countryNone|escape:'html':'UTF-8'}</option>
                {foreach from=$packlinkCountryOptions item=country}
                    <option value="{$country.iso|escape:'html':'UTF-8'}"{if $country.iso == $packlinkProductCustoms.countryOfOrigin} selected="selected"{/if}>{$country.name|escape:'html':'UTF-8'} ({$country.iso|escape:'html':'UTF-8'})</option>
                {/foreach}
            </select>
            <p class="help-block form-text">{$packlinkCustomsLabels.countryHelp|escape:'html':'UTF-8'}</p>
        </div>

        {* Marker so actionProductUpdate only touches customs data when this tab was submitted. *}
        <input type="hidden" name="packlink_customs_submitted" value="1"/>
    </div>
</div>

{* {literal} is required: this script contains regex quantifiers like {6,8}, which Smarty would
   otherwise parse as tags and fail to compile. No Smarty variable may be used inside - the
   validation message reaches the script through the input's data attribute instead. *}
<script type="text/javascript">
    {literal}
    (function () {
        'use strict';

        // New product page (8.1+): the Shipping tab has no hook, so the panel is rendered in the
        // Modules tab and moved here. The target pane lives inside the product form, so the inputs
        // keep being submitted after the move. On the legacy form the panel is already in the
        // Shipping tab and no pane matches, so this is a no-op.
        var SELECTORS = ['#product_shipping-tab', '#product_shipping', '#tab-product_shipping'];
        var MAX_ATTEMPTS = 20;
        var RETRY_DELAY = 250;

        function findShippingPane() {
            for (var i = 0; i < SELECTORS.length; i++) {
                var pane = document.querySelector(SELECTORS[i]);
                if (pane) {
                    return pane;
                }
            }

            return null;
        }

        function relocate(attempt) {
            var panel = document.getElementById('packlink-product-customs');
            if (!panel) {
                return;
            }

            var pane = findShippingPane();
            if (!pane) {
                if (attempt < MAX_ATTEMPTS) {
                    window.setTimeout(function () {
                        relocate(attempt + 1);
                    }, RETRY_DELAY);
                }

                return;
            }

            if (!pane.contains(panel)) {
                pane.appendChild(panel);
            }
        }

        // HS code validator. Mirrors the server-side rule in Packlink::hookActionProductUpdate and
        // the core rule in CustomsMapping (/^[0-9]{6,8}$/); an empty field is valid and means "use
        // the configured customs default", so it must never be flagged.
        var HS_CODE_PATTERN = /^[0-9]{6,8}$/;

        function isHsCodeValid(value) {
            return value === '' || HS_CODE_PATTERN.test(value);
        }

        function showHsCodeError(input, error, show) {
            var message = input.getAttribute('data-packlink-invalid-message') || '';

            if (show) {
                input.classList.add('is-invalid');
                input.setAttribute('aria-invalid', 'true');
                error.textContent = message;
                error.style.display = '';

                return;
            }

            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
            error.textContent = '';
            error.style.display = 'none';
        }

        function bindHsCodeValidation() {
            var input = document.getElementById('packlink_hs_code');
            var error = document.getElementById('packlink_hs_code_error');
            if (!input || !error || input.getAttribute('data-packlink-bound') === '1') {
                return;
            }
            input.setAttribute('data-packlink-bound', '1');

            function validate() {
                // Strip anything that is not a digit as it is typed or pasted, so the only
                // reachable invalid state is a wrong length - which the message then explains.
                var digitsOnly = input.value.replace(/[^0-9]/g, '');
                if (digitsOnly !== input.value) {
                    var caret = input.selectionStart;
                    input.value = digitsOnly;
                    if (caret !== null) {
                        try {
                            input.setSelectionRange(caret - 1, caret - 1);
                        } catch (e) {
                            // Selection APIs are unavailable on some input states; harmless.
                        }
                    }
                }

                var valid = isHsCodeValid(input.value);
                showHsCodeError(input, error, !valid);

                return valid;
            }

            input.addEventListener('input', validate);
            input.addEventListener('blur', validate);
            input.addEventListener('paste', function () {
                window.setTimeout(validate, 0);
            });

            // Legacy product form posts natively, so a bad value can be stopped outright. The new
            // product page saves over AJAX and never fires this, which is why the server-side guard
            // in hookActionProductUpdate stays authoritative there.
            var form = input.form;
            if (form) {
                form.addEventListener('submit', function (event) {
                    if (!validate()) {
                        event.preventDefault();
                        input.focus();
                    }
                });
            }

            // An already-stored malformed value (e.g. seeded before this validation existed) should
            // surface immediately rather than only after the merchant touches the field.
            if (input.value !== '') {
                validate();
            }
        }

        function init() {
            relocate(0);
            bindHsCodeValidation();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    {/literal}
</script>
