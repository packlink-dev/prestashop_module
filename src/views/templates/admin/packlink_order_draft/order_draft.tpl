{* Generate HTML code for printing link to order draft on Packlink *}
<span class="btn-group-action">
    <span class="btn-group">
        {* Generate HTML code for printing Delivery Icon with link *}
        {if !$deleted }<a href="{html_entity_decode($orderDraftLink|escape:'html':'UTF-8')}" target="_blank">{/if}
            <img
                    src="{html_entity_decode($imgSrc|escape:'html':'UTF-8')}"
                    alt="{l s='Packlink order draft' mod='packlink'}"
                    style="width: 32px;"
            >
        {if !$deleted }</a>{/if}
    </span>
</span>
