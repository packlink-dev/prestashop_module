{*
* Packlink customs product attributes (CR-SET-66).
* Rendered in the product edit page via the displayAdminProductsExtra hook.
* HS code + country of origin are persisted per product in ProductCustomsData and fed
* into the core customs invoice during draft creation; empty values fall back to the
* configured customs mapping defaults.
*}
<div class="panel" id="packlink-product-customs">
    <h3>
        <i class="icon-truck"></i> {$packlinkCustomsLabels.title|escape:'html':'UTF-8'}
    </h3>
    <p class="text-muted">{$packlinkCustomsLabels.description|escape:'html':'UTF-8'}</p>

    <div class="form-group">
        <label for="packlink_hs_code">{$packlinkCustomsLabels.hsCode|escape:'html':'UTF-8'}</label>
        <input type="text"
               name="packlink_hs_code"
               id="packlink_hs_code"
               class="form-control"
               maxlength="8"
               pattern="[0-9]{ldelim}6,8{rdelim}"
               value="{$packlinkProductCustoms.hsCode|escape:'html':'UTF-8'}"
               placeholder="{$packlinkCustomsLabels.hsCodePlaceholder|escape:'html':'UTF-8'}"/>
        <p class="help-block">{$packlinkCustomsLabels.hsCodeHelp|escape:'html':'UTF-8'}</p>
    </div>

    <div class="form-group">
        <label for="packlink_country_of_origin">{$packlinkCustomsLabels.country|escape:'html':'UTF-8'}</label>
        <select name="packlink_country_of_origin" id="packlink_country_of_origin" class="form-control">
            <option value="">{$packlinkCustomsLabels.countryNone|escape:'html':'UTF-8'}</option>
            {foreach from=$packlinkCountryCodes item=code}
                <option value="{$code|escape:'html':'UTF-8'}"{if $code == $packlinkProductCustoms.countryOfOrigin} selected="selected"{/if}>{$code|escape:'html':'UTF-8'}</option>
            {/foreach}
        </select>
        <p class="help-block">{$packlinkCustomsLabels.countryHelp|escape:'html':'UTF-8'}</p>
    </div>

    {* Marker so actionProductUpdate only touches customs data when this tab was submitted. *}
    <input type="hidden" name="packlink_customs_submitted" value="1"/>
</div>
