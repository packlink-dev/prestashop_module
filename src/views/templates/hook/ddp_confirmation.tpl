{*
* Packlink duty line for the order confirmation page.
*
* Shown only for orders that bought a duties-paid (DDP) shipping option. It states the
* duty amount the shopper already paid as part of the shipping price — it is purely
* informative and never adds to any total. Both values are assigned pre-formatted by
* getDdpConfirmationBlock() in packlink.php.
*}
<div class="pl-ddp-confirmation">
    <span class="pl-ddp-label">{$plDdpLabel|escape:'html':'UTF-8'}:</span>
    <span class="pl-ddp-amount">{$plDdpAmount|escape:'html':'UTF-8'}</span>
</div>
