{* Generate HTML code for printing link to order draft on Packlink *}
<span class="btn-group-action">
    <span class="btn-group">
        {* Generate HTML code for printing Delivery Icon with link *}
        {if $draftStatus === Logeecom\Infrastructure\TaskExecution\QueueItem::COMPLETED }
          <a class="pl-draft-button btn btn-default"
                  {if $deleted }
                    disabled
                  {else}
                    href="{html_entity_decode($orderDraftLink|escape:'html':'UTF-8')}" target="_blank"
                  {/if}
                  style="padding: 5px 15px; text-align: left; min-width: 177px; pointer-events: none;"
          >
            <img
                    src="{html_entity_decode($imgSrc|escape:'html':'UTF-8')}"
                    alt="{l s='Packlink order draft' mod='packlink'}"
                    style="width: 25px;"
            >
            <span>{l s='View on Packlink' mod='packlink'}</span>
          </a>

{elseif $draftStatus === Logeecom\Infrastructure\TaskExecution\QueueItem::QUEUED}

          <span class="pl-draft-in-progress" data-pl-order-id="">
              {l s='Draft is currently being created.' mod='packlink'}
            </span>

{else}

          <a
                  class="pl-create-draft-button btn btn-default"
                  data-pl-order-id="{html_entity_decode($statusMessage|escape:'html':'UTF-8')}"
                  style="padding: 5px 15px; text-align: left; min-width: 177px;"
          >
              <img
                      src="{html_entity_decode($imgSrc|escape:'html':'UTF-8')}"
                      alt="{l s='Packlink order draft' mod='packlink'}"
                      style="width: 25px;"
              >
              <span>{l s='Send with Packlink' mod='packlink'}</span>
            </a>
        {/if}
    </span>
</span>
