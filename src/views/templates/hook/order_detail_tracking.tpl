{*
* Packlink shared tracking page link for the customer order detail page.
*
* The displayOrderDetail hook renders this block at a fixed, theme-defined position
* (above the carrier section in the bundled themes). On page load we move it directly
* underneath the "selected carrier" information so it matches the spec on every theme:
*   - Hummingbird: <section class="order-carriers">
*   - Classic (and Classic-derived): the carrier box (.box) holding .shipping-lines
* If neither anchor exists (a heavily customised theme), the link simply stays at the
* hook's default position so it is always shown.
*}
{if isset($packlinkSharedTrackingUrl) && $packlinkSharedTrackingUrl}
    <div id="pl-shared-tracking" class="packlink-shared-tracking" style="margin-top: 10px;">
        <a href="{$packlinkSharedTrackingUrl|escape:'html':'UTF-8'}"
           target="_blank"
           rel="noopener noreferrer">
            {l s='View tracking page' mod='packlink'}
        </a>
    </div>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            var box = document.getElementById('pl-shared-tracking');
            if (!box) {
                return;
            }

            var target = document.querySelector('.order-carriers');

            if (!target) {
                var shippingLines = document.querySelector('.shipping-lines');
                if (shippingLines && shippingLines.closest) {
                    target = shippingLines.closest('.box');
                }
            }

            if (target) {
                target.appendChild(box);
            }
        });
    </script>
{/if}
