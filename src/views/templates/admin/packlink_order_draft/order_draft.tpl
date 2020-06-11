{* Generate HTML code for printing link to order draft on Packlink *}
<span class="btn-group-action">
    <span class="btn-group">
        {* Generate HTML code for printing Delivery Icon with link *}
        {if $draftStatus === 'completed' }
          <a class="btn btn-default pl-draft-button
          {if $deleted } pl-draft-button-disabled"
        {else}" href="{html_entity_decode($orderDraftLink|escape:'html':'UTF-8')}" target="_blank"
        {/if}
          >
          <img
                  class="pl-image"
                  src="{html_entity_decode($imgSrc|escape:'html':'UTF-8')}"
                  alt="{l s='Packlink order draft' mod='packlink'}"
          >
          <span>{l s='View on Packlink' mod='packlink'}</span>
          </a>
          {elseif $draftStatus === 'queued' }
          <span
                  class="pl-draft-in-progress"
                  data-order-id="{html_entity_decode($orderId|escape:'html':'UTF-8')}"
                  data-draft-status-url="{html_entity_decode($draftStatusUrl|escape:'html':'UTF-8')}"
          >
              {l s='Draft is currently being created.' mod='packlink'}
          </span>
{else}
          <a
                  class="btn btn-default pl-create-draft-button"
                  data-order-id="{html_entity_decode($orderId|escape:'html':'UTF-8')}"
          >
              <img
                      class="pl-image"
                      src="{html_entity_decode($imgSrc|escape:'html':'UTF-8')}"
                      alt="{l s='Packlink order draft' mod='packlink'}"
              >
              <span>{l s='Send with Packlink' mod='packlink'}</span>
          </a>
        {/if}
    </span>
</span>

<input type="hidden" class="pl-create-endpoint" value="{html_entity_decode($createDraftUrl|escape:'html':'UTF-8')}"/>
<input type="hidden" class="pl-draft-status" value="{html_entity_decode($draftStatusUrl|escape:'html':'UTF-8')}"/>
<input type="hidden" class="pl-draft-in-progress-message"
       value="{l s='Draft is currently being created.' mod='packlink'}"/>
<input type="hidden" class="pl-draft-failed-message"
       value="{l s='Previous attempt to create a draft failed.' mod='packlink'}"/>

<a class="btn btn-default pl-create-draft-button pl-create-draft-template">
  <img
          class="pl-image"
          src="{html_entity_decode($imgSrc|escape:'html':'UTF-8')}"
          alt="{l s='Packlink order draft' mod='packlink'}"
  >
  <span>{l s='Send with Packlink' mod='packlink'}</span>
</a>

<a class="btn btn-default pl-draft-button pl-draft-button-template" target="_blank">
  <img
          class="pl-image"
          src="{html_entity_decode($imgSrc|escape:'html':'UTF-8')}"
          alt="{l s='Packlink order draft' mod='packlink'}"
  >
  <span>{l s='View on Packlink' mod='packlink'}</span>
</a>