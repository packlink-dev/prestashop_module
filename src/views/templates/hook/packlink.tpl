<script type="text/javascript" src="{$fancyBoxPath}"></script>

<div class="container-fluid pl-main-wrapper" id="pl-main-page-holder">
  <div class="pl-spinner" id="pl-spinner">
    <div></div>
  </div>

  <div class="pl-page-wrapper">
    <div class="pl-sidebar-wrapper">
      <div class="pl-logo-wrapper">
        <img src="{html_entity_decode($dashboardLogo|escape:'html':'UTF-8')}"
             class="pl-dashboard-logo"
             alt="{l s='Packlink PRO Shipping' mod='packlink'}">
      </div>
      <div id="pl-sidebar-shipping-methods-btn" class="col-sm-12 pl-sidebar-link-wrapper"
           data-pl-sidebar-btn="shipping-methods">
        <div class="pl-sidebar-link-wrapper-line"></div>
        <div class="pl-sidebar-link-wrapper-inner">
          <i class="material-icons pl-sidebar-icon">
            local_shipping
          </i>
          <div class="pl-sidebar-text-wrapper">
              {l s='SHIPPING SERVICES' mod='packlink'}
          </div>
        </div>
      </div>
      <div id="pl-sidebar-basic-settings-btn" class="col-sm-12 pl-sidebar-link-wrapper"
           data-pl-sidebar-btn="basic-settings">
        <div class="pl-sidebar-link-wrapper-line"></div>
        <div class="pl-sidebar-link-wrapper-inner">
          <i class="material-icons pl-sidebar-icon">
            settings
          </i>
          <div class="pl-sidebar-text-wrapper">
              {l s='BASIC SETTINGS' mod='packlink'}
          </div>
        </div>
      </div>

      <div id="pl-sidebar-extension-point"></div>

      <div class="pl-help">
        <a class="pl-link" href="{html_entity_decode($helpLink|escape:'html':'UTF-8')}" target="_blank">
          <span>{l s='Help' mod='packlink'}</span>
        </a>
        <a class="pl-link" href="{html_entity_decode($helpLink|escape:'html':'UTF-8')}" target="_blank">
          <i class="material-icons">help</i>
        </a>
        <div class="pl-contact">{l s='Contact us' mod='packlink'}:</div>
        <a href="mailto:business@packlink.com" class="pl-link" target="_blank">business@packlink.com</a>
      </div>
    </div>

    <div class="pl-content-wrapper">
      <div class="pl-input-mask" id="pl-input-mask"></div>
      <div class="row">

        <div class="col-sm-12 pl-content-wrapper-panel" id="pl-content-extension-point"></div>

      </div>
    </div>
  </div>
  <div id="pl-footer-extension-point"></div>
</div>

<div class="pl-template-section">
  <div id="pl-sidebar-subitem-template">
    <div class="row pl-sidebar-subitem-wrapper" data-pl-sidebar-btn="order-state-mapping">
      <div>
          {l s='Map order statuses' mod='packlink'}
      </div>
    </div>
    <div class="row pl-sidebar-subitem-wrapper" data-pl-sidebar-btn="default-warehouse">
      <div>
          {l s='Default warehouse' mod='packlink'}
      </div>
    </div>
    <div class="row pl-sidebar-subitem-wrapper" data-pl-sidebar-btn="default-parcel">
      <div>
          {l s='Default parcel' mod='packlink'}
      </div>
    </div>
  </div>


  <div id="pl-order-state-mapping-template">
    <div class="row">
      <div class="pl-basic-settings-page-wrapper pl-mapping-page-wrapper">
        <div class="row">
          <div class="col-sm-12 pl-basic-settings-page-title-wrapper">
              {l s='Map order statuses' mod='packlink'}
          </div>
        </div>
        <div class="row">
          <div class="col-sm-12 pl-basic-settings-page-description-wrapper">
              {l s='Packlink offers you the possibility to update your PrestaShop order status with the shipping info. You can edit anytime.' mod='packlink'}
          </div>
        </div>
        <div>
          <div class="pl-mapping-page-select-section">
              {l s='Packlink PRO Shipping Status' mod='packlink'}
          </div>
          <div class="pl-mapping-page-wrapper-equals">
          </div>
          <div class="pl-mapping-page-select-section">
              {l s='PrestaShop Order Status' mod='packlink'}
          </div>
        </div>

        <div>
          <div class="pl-mapping-page-select-section">
            <input type="text" value="{l s='Pending' mod='packlink'}" readonly>
          </div>
          <div class="pl-mapping-page-wrapper-equals">
            =
          </div>
          <div class="pl-mapping-page-select-section">
            <select data-pl-status="pending">
              <option value="" selected>({l s='None' mod='packlink'})</option>
            </select>
          </div>
        </div>

        <div>
          <div class="pl-mapping-page-select-section">
            <input type="text" value="{l s='Processing' mod='packlink'}" readonly>
          </div>
          <div class="pl-mapping-page-wrapper-equals">
            =
          </div>
          <div class="pl-mapping-page-select-section">
            <select data-pl-status="processing">
              <option value="" selected>({l s='None' mod='packlink'})</option>
            </select>
          </div>
        </div>

        <div>
          <div class="pl-mapping-page-select-section">
            <input type="text" value="{l s='Ready for shipping' mod='packlink'}" readonly>
          </div>
          <div class="pl-mapping-page-wrapper-equals">
            =
          </div>
          <div class="pl-mapping-page-select-section">
            <select data-pl-status="readyForShipping">
              <option value="" selected>({l s='None' mod='packlink'})</option>
            </select>
          </div>
        </div>

        <div>
          <div class="pl-mapping-page-select-section">
            <input type="text" value="{l s='In transit' mod='packlink'}" readonly>
          </div>
          <div class="pl-mapping-page-wrapper-equals">
            =
          </div>
          <div class="pl-mapping-page-select-section">
            <select data-pl-status="inTransit">
              <option value="" selected>({l s='None' mod='packlink'})</option>
            </select>
          </div>
        </div>

        <div>
          <div class="pl-mapping-page-select-section">
            <input type="text" value="{l s='Delivered' mod='packlink'}" readonly>
          </div>
          <div class="pl-mapping-page-wrapper-equals">
            =
          </div>
          <div class="pl-mapping-page-select-section">
            <select data-pl-status="delivered">
              <option value="" selected>({l s='None' mod='packlink'})</option>
            </select>
          </div>
        </div>

        <div>
          <div class="pl-mapping-page-select-section">
            <button class="btn btn-primary btn-lg"
                    id="pl-save-mappings-btn">{l s='Save changes' mod='packlink'}</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="pl-default-parcel-template">
    <div class="row">
      <div class="col-sm-12 pl-basic-settings-page-wrapper">
        <div class="row">
          <div class="col-sm-12 pl-basic-settings-page-title-wrapper">
              {l s='Set default parcel' mod='packlink'}
          </div>
        </div>
        <div class="row">
          <div class="col-sm-12 pl-basic-settings-page-description-wrapper">
              {l s='We will use the default parcel in case any item has not defined dimensions and weight. You can edit anytime.' mod='packlink'}
          </div>
        </div>
        <div class="row">
          <div class="pl-basic-settings-page-form-wrapper">
            <div class="row">
              <div class=" pl-basic-settings-page-form-input-item">
                <div class=" pl-form-section-input pl-text-input pl-parcel-input">
                  <input type="text" id="pl-default-parcel-weight"/>
                  <span class="pl-text-input-label">{l s='Weight' mod='packlink'} (kg)</span>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="pl-basic-settings-page-form-input-item pl-inline-input">
                <div class=" pl-form-section-input pl-text-input pl-parcel-input">
                  <input type="text" id="pl-default-parcel-length"/>
                  <span class="pl-text-input-label">{l s='Length' mod='packlink'} (cm)</span>
                </div>
                <div class=" pl-form-section-input pl-text-input pl-parcel-input">
                  <input type="text" id="pl-default-parcel-width"/>
                  <span class="pl-text-input-label">{l s='Width' mod='packlink'} (cm)</span>
                </div>
                <div class=" pl-form-section-input pl-text-input pl-parcel-input">
                  <input type="text" id="pl-default-parcel-height"/>
                  <span class="pl-text-input-label">{l s='Height' mod='packlink'} (cm)</span>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="pl-basic-settings-page-form-input-item pl-parcel-button">
                <button type="button" class="btn btn-primary btn-lg"
                        id="pl-default-parcel-submit-btn">{l s='Save changes' mod='packlink'}</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>


  <div id="pl-default-warehouse-template">
    <div class="row">
      <div class="col-sm-12 pl-basic-settings-page-wrapper">
        <div class="row">
          <div class="col-sm-12 pl-basic-settings-page-title-wrapper">
              {l s='Set default warehouse' mod='packlink'}
          </div>
        </div>
        <div class="row">
          <div class="col-sm-12 pl-basic-settings-page-description-wrapper">
              {l s='We will use the default Warehouse address as your sender address. You can edit anytime.' mod='packlink'}
          </div>
        </div>
        <div class="row">
          <div class="pl-basic-settings-page-form-wrapper">
            <div class="row">
              <div class=" pl-basic-settings-page-form-input-item">
                <div class=" pl-form-section-input pl-text-input">
                  <input type="text" class="pl-warehouse-input" id="pl-default-warehouse-alias"/>
                  <span class="pl-text-input-label">{l s='Warehouse name' mod='packlink'}</span>
                </div>
              </div>
            </div>
            <div class="row">
              <div class=" pl-basic-settings-page-form-input-item">
                <div class=" pl-form-section-input pl-text-input">
                  <input type="text" class="pl-warehouse-input" id="pl-default-warehouse-name"/>
                  <span class="pl-text-input-label">{l s='Contact person name' mod='packlink'}</span>
                </div>
              </div>
            </div>
            <div class="row">
              <div class=" pl-basic-settings-page-form-input-item">
                <div class=" pl-form-section-input pl-text-input">
                  <input type="text" class="pl-warehouse-input" id="pl-default-warehouse-surname"/>
                  <span class="pl-text-input-label">{l s='Contact person surname' mod='packlink'}</span>
                </div>
              </div>
            </div>
            <div class="row">
              <div class=" pl-basic-settings-page-form-input-item">
                <div class=" pl-form-section-input pl-text-input">
                  <input type="text" class="pl-warehouse-input" id="pl-default-warehouse-company"/>
                  <span class="pl-text-input-label">{l s='Company name' mod='packlink'}</span>
                </div>
              </div>
            </div>
            <div class="row">
              <div class=" pl-basic-settings-page-form-input-item">
                <div class=" pl-form-section-input pl-text-input">
                  <input
                          type="text"
                          class="pl-warehouse-input"
                          id="pl-default-warehouse-country"
                          value="{$warehouseCountry}"
                          readonly
                          tabindex="-1"
                  />
                  <span class="pl-text-input-label">{l s='Country' mod='packlink'}</span>
                </div>
              </div>
            </div>
            <div class="row">
              <div class=" pl-basic-settings-page-form-input-item">
                <div class=" pl-form-section-input pl-text-input">
                  <input autocomplete="new-password" type="text" class="pl-warehouse-input"
                         id="pl-default-warehouse-postal_code"/>
                  <span class="pl-text-input-label">{l s='City or postal code' mod='packlink'}</span>
                  <span class="pl-input-search-icon" data-pl-id="search-icon"><i
                            class="material-icons">search</i></span>
                </div>
              </div>
            </div>
            <div class="row">
              <div class=" pl-basic-settings-page-form-input-item">
                <div class=" pl-form-section-input pl-text-input">
                  <input type="text" class="pl-warehouse-input" id="pl-default-warehouse-address"/>
                  <span class="pl-text-input-label">{l s='Address' mod='packlink'}</span>
                </div>
              </div>
            </div>
            <div class="row">
              <div class=" pl-basic-settings-page-form-input-item">
                <div class=" pl-form-section-input pl-text-input">
                  <input type="text" class="pl-warehouse-input" id="pl-default-warehouse-phone"/>
                  <span class="pl-text-input-label">{l s='Phone number' mod='packlink'}</span>
                </div>
              </div>
            </div>
            <div class="row">
              <div class=" pl-basic-settings-page-form-input-item">
                <div class="pl-form-section-input pl-text-input">
                  <input type="text" class="pl-warehouse-input" id="pl-default-warehouse-email"/>
                  <span class="pl-text-input-label">{l s='Email' mod='packlink'}</span>
                </div>
              </div>
            </div>
            <div class="row">
              <div class=" pl-basic-settings-page-form-input-item">
                <button type="button" class="btn btn-primary btn-lg"
                        id="pl-default-warehouse-submit-btn">{l s='Save changes' mod='packlink'}</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>


  <div id="pl-shipping-methods-page-template">

    <!-- DELETE SHIPPING METHODS MODAL -->

    <div class="pl-dashboard-modal-wrapper hidden" id="pl-disable-methods-modal-wrapper">
      <div class="pl-dashboard-modal pl-disable-methods-modal" id="pl-disable-methods-modal">
        <div class="pl-shipping-modal-title">
            {l s='Congrats! Your first Shipping Method has been successfully created.' mod='packlink'}
        </div>
        <div class="pl-shipping-modal-body">
            {l s='In order to offer you the best possible service, it\'s important to disable your previous carriers. Do you want us to disable them? (recommended)' mod='packlink'}
        </div>
        <div class="pl-shipping-modal-row">
          <button class="btn btn-lg btn-outline-secondary pl-shipping-modal-btn"
                  id="pl-disable-methods-modal-cancel">{l s='Cancel' mod='packlink'}</button>
          <button class="btn btn-lg btn-primary"
                  id="pl-disable-methods-modal-accept">{l s='Accept' mod='packlink'}</button>
        </div>
      </div>
    </div>

    <!-- DASHBOARD MODAL SECTION -->

    <div class="pl-dashboard-modal-wrapper hidden" id="pl-dashboard-modal-wrapper">
      <div class="pl-dashboard-modal" id="pl-dashboard-modal">
        <img src="{html_entity_decode($dashboardIcon|escape:'html':'UTF-8')}" alt="Dashboard icon">
        <div class="pl-dashboard-page-title-wrapper">
            {l s='You\'re almost there!' mod='packlink'}
        </div>
        <div class="pl-dashboard-page-subtitle-wrapper">
            {l s='Details synced with your existing account' mod='packlink'}
        </div>
        <div class="pl-dashboard-page-step-wrapper pl-dashboard-page-step" id="pl-parcel-step">
          <div class="pl-empty-checkmark pl-checkmark">
            <i class="material-icons">
              check_box_outline_blank
            </i>
          </div>
          <div class="pl-checked-checkmark pl-checkmark">
            <i class="material-icons">
              check_box
            </i>
          </div>
          <div class="pl-step-title">
              {l s='Set default parcel details' mod='packlink'}
          </div>
        </div>
        <div class="pl-dashboard-page-step-wrapper pl-dashboard-page-step" id="pl-warehouse-step">
          <div class="pl-empty-checkmark pl-checkmark">
            <i class="material-icons">
              check_box_outline_blank
            </i>
          </div>
          <div class="pl-checked-checkmark pl-checkmark">
            <i class="material-icons">
              check_box
            </i>
          </div>
          <div class="pl-step-title">
              {l s='Set default warehouse details' mod='packlink'}
          </div>
        </div>
        <div class="pl-dashboard-page-subtitle-wrapper" id="pl-step-subtitle">
            {l s='Just a few more steps to complete the setup' mod='packlink'}
        </div>
        <div class="pl-dashboard-page-step-wrapper pl-dashboard-page-step" id="pl-shipping-methods-step">
          <div class="pl-empty-checkmark pl-checkmark">
            <i class="material-icons">
              check_box_outline_blank
            </i>
          </div>
          <div class="pl-checked-checkmark pl-checkmark">
            <i class="material-icons">
              check_box
            </i>
          </div>
          <div class="pl-step-title">
              {l s='Select shipping services' mod='packlink'}
          </div>
        </div>
      </div>
    </div>

    <!-- SHIPPING PAGE SECTION -->

    <div class="row pl-shipping-page">
      <div class="pl-flash-msg-wrapper">
        <div class="pl-flash-msg" id="pl-flash-message">
          <div class="pl-flash-msg-text-section">
            <i class="material-icons success">
              check_circle
            </i>
            <i class="material-icons warning">
              warning
            </i>
            <i class="material-icons danger">
              error
            </i>
            <span id="pl-flash-message-text"></span>
          </div>
          <div class="pl-flash-msg-close-btn">
            <i class="material-icons" id="pl-flash-message-close-btn">
              close
            </i>
          </div>
        </div>
      </div>
      <div class="col-sm-2 pl-filter-wrapper">
        <div id="pl-shipping-methods-filters-extension-point"></div>
      </div>
      <div class="col-sm-10 pl-methods-tab-wrapper">
        <div id="pl-shipping-methods-nav-extension-point"></div>
        <div class="row">
          <div class="col-sm-12 pl-clear-padding">
            <div id="pl-shipping-methods-result-extension-point"></div>
          </div>
        </div>
        <div class="col-sm-12 pl-table-wrapper" id="pl-table-scroll">
          <div id="pl-shipping-methods-table-extension-point"></div>
          <div class="pl-shipping-services-message hidden" id="pl-getting-shipping-services">
            <div class="title">{l s='We are importing the best shipping services for your shipments.' mod='packlink'}</div>
            <div class="subtitle">{l s='This process could take a few seconds.' mod='packlink'}</div>
            <div class="pl-spinner" id="pl-getting-services-spinner">
              <div></div>
            </div>
          </div>
          <div class="pl-shipping-services-message hidden" id="pl-no-shipping-services">
            <div class="title">{l s='We are having troubles getting shipping services.' mod='packlink'}</div>
            <div class="subtitle">{l s='Do you want to retry?' mod='packlink'}</div>
            <button type="button" class="btn btn-primary btn-lg"
                    id="pl-shipping-services-retry-btn">{l s='Retry' mod='packlink'}</button>
          </div>
        </div>
      </div>
    </div>
  </div>


  <div id="pl-shipping-methods-filters-template">
    <div class="row">
      <div class="col-sm-12 pl-filter-method-tile">
          {l s='Filter services' mod='packlink'}
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 pl-filter-method">
        <b>{l s='Type' mod='packlink'}</b>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 pl-filter-method-item">
        <div class="md-checkbox">
          <label>
            <input type="checkbox" data-pl-shipping-methods-filter="title-national" tabindex="-1">
            <i class="md-checkbox-control"></i>
              {l s='National' mod='packlink'}
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 pl-filter-method-item">
        <div class="md-checkbox">
          <label>
            <input type="checkbox" data-pl-shipping-methods-filter="title-international" tabindex="-1">
            <i class="md-checkbox-control"></i>
              {l s='International' mod='packlink'}
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 pl-filter-method">
        <b>{l s='Delivery type' mod='packlink'}</b>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 pl-filter-method-item">
        <div class="md-checkbox">
          <label>
            <input type="checkbox" data-pl-shipping-methods-filter="deliveryType-economic" tabindex="-1">
            <i class="md-checkbox-control"></i>
              {l s='Economic' mod='packlink'}
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 pl-filter-method-item">
        <div class="md-checkbox">
          <label>
            <input type="checkbox" data-pl-shipping-methods-filter="deliveryType-express" tabindex="-1">
            <i class="md-checkbox-control"></i>
              {l s='Express' mod='packlink'}
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 pl-filter-method">
        <b>{l s='Parcel origin' mod='packlink'}</b>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 pl-filter-method-item">
        <div class="md-checkbox">
          <label>
            <input type="checkbox" data-pl-shipping-methods-filter="parcelOrigin-pickup" tabindex="-1">
            <i class="md-checkbox-control"></i>
              {l s='Collection' mod='packlink'}
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 pl-filter-method-item">
        <div class="md-checkbox">
          <label>
            <input type="checkbox" data-pl-shipping-methods-filter="parcelOrigin-dropoff" tabindex="-1">
            <i class="md-checkbox-control"></i>
              {l s='Drop off' mod='packlink'}
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 pl-filter-method">
        <b>{l s='Parcel destination' mod='packlink'}</b>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 pl-filter-method-item">
        <div class="md-checkbox">
          <label>
            <input type="checkbox" data-pl-shipping-methods-filter="parcelDestination-home" tabindex="-1">
            <i class="md-checkbox-control"></i>
              {l s='Delivery' mod='packlink'}
          </label>
        </div>
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 pl-filter-method-item">
        <div class="md-checkbox">
          <label>
            <input type="checkbox" data-pl-shipping-methods-filter="parcelDestination-dropoff" tabindex="-1">
            <i class="md-checkbox-control"></i>
              {l s='Pick up' mod='packlink'}
          </label>
        </div>
      </div>
    </div>
  </div>


  <table>
    <tbody id="pl-shipping-method-configuration-template">
    <tr class="pl-configure-shipping-method-wrapper">
      <td colspan="9">
        <div class="row">
          <div class="col-sm-12 pl-configure-shipping-method-form-wrapper">
            <div class="row pl-shipping-method-form">
              <div class="col-sm-6 pl-form-section-wrapper">
                <div class="row">
                  <div class="col-sm-12 pl-form-section-title-wrapper">
                    <div class="pl-form-section-title">
                        {l s='Add service title' mod='packlink'}
                    </div>
                    <div class="pl-form-section-title-line">
                      <hr>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-sm-12 pl-form-section-subtitle-wrapper">
                      {l s='This title will be visible to your customers' mod='packlink'}
                  </div>
                </div>
                <div class="row">
                  <div class="col-sm-12 pl-form-section-input-wrapper">
                    <div class="form-group pl-form-section-input pl-text-input">
                      <input type="text" class="form-control" id="pl-method-title-input"/>
                      <span class="pl-text-input-label">{l s='Service title' mod='packlink'}</span>
                    </div>
                    <div class="row">
                      <div class="col-sm-12 pl-form-section-title-wrapper">
                        <div class="pl-form-section-title">
                            {l s='Carrier logo' mod='packlink'}
                        </div>
                        <div class="pl-form-section-title-line">
                          <hr>
                        </div>
                      </div>
                    </div>
                    <div class="md-checkbox">
                      <label class="pl-form-section-input-checkbox-label">
                        <input type="checkbox" name="method-show-logo-input" checked id="pl-show-logo">
                        <i class="md-checkbox-control"></i>
                          {l s='Show carrier logo to my customers' mod='packlink'}
                      </label>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 pl-form-section-wrapper">
                <div class="row">
                  <div class="col-sm-12 pl-form-section-title-wrapper">
                    <div class="pl-form-section-title">
                        {l s='Select pricing policy' mod='packlink'}
                    </div>
                    <div class="pl-form-section-title-line">
                      <hr>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-sm-12 pl-form-section-subtitle-wrapper">
                      {l s='Choose the pricing policy to show your customers' mod='packlink'}
                  </div>
                </div>
                <div class="row">
                  <div class="col-sm-12 pl-form-section-input-wrapper">
                    <div class="form-group pl-form-section-input">
                      <select id="pl-pricing-policy-selector">
                        <option value="1">{l s='Packlink prices' mod='packlink'}</option>
                        <option value="2">{l s='% of Packlink prices' mod='packlink'}</option>
                        <option value="3">{l s='Fixed prices based on total weight' mod='packlink'}</option>
                        <option value="4">{l s='Fixed prices based on total price' mod='packlink'}</option>
                      </select>
                    </div>
                  </div>
                </div>

                <div id="pl-pricing-extension-point"></div>


                <div class="row">
                  <div class="col-sm-12 pl-form-section-subtitle-wrapper">
                      {l s='Tax' mod='packlink'}
                  </div>
                </div>
                <div class="row">
                  <div class="col-sm-12 pl-form-section-input-wrapper">
                    <div class="form-group pl-form-section-input">
                      <select id="pl-tax-selector">
                      </select>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-sm-12 text-right pl-configure-shipping-method-button-wrapper">
            <button type="button" class="btn btn-primary btn-lg"
                    id="pl-shipping-method-config-save-btn">{l s='Save' mod='packlink'}</button>
            <button type="button" class="btn btn-outline-secondary btn-lg"
                    id="pl-shipping-method-config-cancel-btn">{l s='Cancel' mod='packlink'}</button>
          </div>
        </div>
      </td>
    </tr>
    </tbody>
  </table>


  <table>
    <tbody id="pl-shipping-methods-row-template">
    <tr class="pl-table-row-wrapper">
      <td class="pl-table-row-method-select">
        <div id="pl-shipping-method-select-btn" class="pl-switch" tabindex="-1">
          <div class="pl-empty-checkbox">
            <i class="material-icons">
              check_box_outline_blank
            </i>
          </div>
          <div class="pl-checked-checkbox">
            <i class="material-icons">
              check_box_outline
            </i>
          </div>
        </div>
      </td>
      <td class="pl-table-row-method-title">
        <h2 id="pl-shipping-method-name">
        </h2>
        <p class="pl-price-indicator" data-pl-price-indicator="packlink">
            {l s='Packlink prices' mod='packlink'}
        </p>
        <p class="pl-price-indicator" data-pl-price-indicator="percent">
            {l s='Packlink percent' mod='packlink'}
        </p>
        <p class="pl-price-indicator" data-pl-price-indicator="fixed-weight">
            {l s='Fixed prices based on total weight' mod='packlink'}
        </p>
        <p class="pl-price-indicator" data-pl-price-indicator="fixed-value">
            {l s='Fixed prices based on total price' mod='packlink'}
        </p>
      </td>
      <td class="pl-table-row-method-logo">
        <img src="" class="pl-method-logo" id="pl-logo"
             alt="Logo">
      </td>
      <td class="pl-table-row-method-delivery-type" id="pl-delivery-type">

      </td>
      <td class="pl-table-row-method-type" id="pl-method-title">
        <div class="pl-national">
            {l s='National' mod='packlink'}
        </div>
        <div class="pl-international">
            {l s='International' mod='packlink'}
        </div>
      </td>
      <td>
        <div class="pl-method-pudo-icon-wrapper" id="pl-pudo-icon-origin">
          <div class="pl-pudo-pickup">
            <p class="pl-method-pudo-icon">
              <i class="material-icons">
                home
              </i>
            </p>
            {l s='Collection' mod='packlink'}
          </div>
          <div class="pl-pudo-dropoff">
            <p class="pl-method-pudo-icon">
              <i class="material-icons">
                location_on
              </i>
            </p>
            {l s='Drop off' mod='packlink'}
          </div>
        </div>
      </td>
      <td class="pl-table-row-arrow-wrapper">
        <div class="pl-table-row-arrow">
          <div class="pl-table-row-arrow-handle">

          </div>
          <i class="material-icons">
            arrow_right_alt
          </i>
        </div>
      </td>
      <td>
        <div class="pl-method-pudo-icon-wrapper" id="pl-pudo-icon-dest">
          <div class="pl-pudo-pickup">
            <p class="pl-method-pudo-icon">
              <i class="material-icons">
                home
              </i>
            </p>
              {l s='Delivery' mod='packlink'}
          </div>
          <div class="pl-pudo-dropoff">
            <p class="pl-method-pudo-icon">
              <i class="material-icons">
                location_on
              </i>
            </p>
              {l s='Pick up' mod='packlink'}
          </div>
        </div>
      </td>
      <td>
        <a href="#" id="pl-shipping-method-config-btn" tabindex="-1">
            {l s='Configure' mod='packlink'}
        </a>
      </td>
    </tr>
    </tbody>
  </table>

  <div id="pl-shipping-methods-nav-template">
    <div class="row">
      <div class="col-sm-12 pl-nav-wrapper">
        <div class="pl-nav-item selected" data-pl-shipping-methods-nav-button="all" tabindex="-1">
            {l s='All shipping services' mod='packlink'}
        </div>
        <div class="pl-nav-item" data-pl-shipping-methods-nav-button="selected" tabindex="-1">
            {l s='Selected shipping services' mod='packlink'}
        </div>
      </div>
    </div>
  </div>

  <div id="pl-shipping-methods-table-template">
    <table class="table pl-table">
      <thead>
      <tr class="pl-table-header-wrapper">
        <th scope="col" class="pl-table-header-select">{l s='SELECT' mod='packlink'}</th>
        <th scope="col" class="pl-table-header-title">{l s='SHIPPING SERVICES' mod='packlink'}</th>
        <th scope="col" class="pl-table-header-carrier">{l s='CARRIER' mod='packlink'}</th>
        <th scope="col" class="pl-table-header-transit">{l s='TRANSIT TIME' mod='packlink'}</th>
        <th scope="col" class="pl-table-header-type">{l s='TYPE' mod='packlink'}</th>
        <th scope="col" class="pl-table-header-origin">{l s='ORIGIN' mod='packlink'}</th>
        <th scope="col" class="pl-table-header-arrow"></th>
        <th scope="col" class="pl-table-header-destination">{l s='DESTINATION' mod='packlink'}</th>
        <th scope="col" class="pl-table-header-actions"></th>
      </tr>
      </thead>
      <tbody id="pl-shipping-method-table-row-extension-point" class="pl-tbody">
      </tbody>
    </table>
  </div>

  <div id="pl-shipping-methods-result-template">
    <div class="pl-num-shipping-method-results-wrapper">
        {l s='Showing' mod='packlink'} <span id="pl-number-showed-methods"></span> {l s='results' mod='packlink'}
    </div>
  </div>

  <div id="pl-packlink-percent-template">
    <div class="row">
      <div class="col-sm-12 pl-form-section-subtitle-wrapper">
          {l s='Please set pricing rule' mod='packlink'}
      </div>
    </div>
    <div class="row">
      <div class="col-sm-12 pl-form-section-input-wrapper pl-price-increase-wrapper">
        <div class="pl-input-price-switch selected" data-pl-packlink-percent-btn="increase">
            {l s='Increase' mod='packlink'}
        </div>
        <div class="pl-input-price-switch" data-pl-packlink-percent-btn="decrease">
            {l s='Reduce' mod='packlink'}
        </div>
        <div class="form-group pl-form-section-input pl-text-input">
          <input type="text" class="form-control" id="pl-perecent-amount"/>
          <span class="pl-text-input-label">{l s='BY' mod='packlink'} %</span>
        </div>
      </div>
    </div>
  </div>

  <div id="pl-fixed-prices-by-weight-template">
    <div class="row">
      <div class="col-sm-12 pl-form-section-subtitle-wrapper">
          {l s='Please add price for each weight criteria' mod='packlink'}
      </div>
    </div>

    <div class="row">
      <div id="pl-fixed-price-criteria-extension-point" style="width: 100%"></div>
    </div>
    <div class="row">
      <div class="col-sm-12 pl-form-section-input-wrapper">
        <div class="pl-fixed-price-add-criteria-button" id="pl-fixed-price-add">
          + {l s='Add price' mod='packlink'}
        </div>
      </div>
    </div>
  </div>

  <div id="pl-fixed-price-by-weight-criteria-template">
    <div class="pl-fixed-price-criteria">
      <div class="row">
        <div class="col-sm-12 pl-form-section-input-wrapper pl-fixed-price-wrapper">
          <div class="form-group pl-form-section-input pl-text-input">
            <input type="text" data-pl-fixed-price="from" disabled tabindex="-1"/>
            <span class="pl-text-input-label">{l s='FROM' mod='packlink'} (kg)</span>
          </div>
          <div class="form-group pl-form-section-input pl-text-input">
            <input type="text" data-pl-fixed-price="to"/>
            <span class="pl-text-input-label">{l s='TO' mod='packlink'} (kg)</span>
          </div>
          <div class="form-group pl-form-section-input pl-text-input">
            <input type="text" data-pl-fixed-price="amount"/>
            <span class="pl-text-input-label">{l s='PRICE' mod='packlink'} (€)</span>
          </div>
          <div class="pl-remove-fixed-price-criteria-btn" data-pl-remove="criteria">
            <i class="material-icons">
              close
            </i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="pl-fixed-prices-by-value-template">
    <div class="row">
      <div class="col-sm-12 pl-form-section-subtitle-wrapper">
          {l s='Please add price for each price criteria' mod='packlink'}
      </div>
    </div>

    <div class="row">
      <div id="pl-fixed-price-criteria-extension-point" style="width: 100%"></div>
    </div>
    <div class="row">
      <div class="col-sm-12 pl-form-section-input-wrapper">
        <div class="pl-fixed-price-add-criteria-button" id="pl-fixed-price-add">
          + {l s='Add price' mod='packlink'}
        </div>
      </div>
    </div>
  </div>

  <div id="pl-fixed-price-by-value-criteria-template">
    <div class="pl-fixed-price-criteria">
      <div class="row">
        <div class="col-sm-12 pl-form-section-input-wrapper pl-fixed-price-wrapper">
          <div class="form-group pl-form-section-input pl-text-input">
            <input type="text" data-pl-fixed-price="from" disabled tabindex="-1"/>
            <span class="pl-text-input-label">{l s='FROM' mod='packlink'} (€)</span>
          </div>
          <div class="form-group pl-form-section-input pl-text-input">
            <input type="text" data-pl-fixed-price="to"/>
            <span class="pl-text-input-label">{l s='TO' mod='packlink'} (€)</span>
          </div>
          <div class="form-group pl-form-section-input pl-text-input">
            <input type="text" data-pl-fixed-price="amount"/>
            <span class="pl-text-input-label">{l s='PRICE' mod='packlink'} (€)</span>
          </div>
          <div class="pl-remove-fixed-price-criteria-btn" data-pl-remove="criteria">
            <i class="material-icons">
              close
            </i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="pl-error-template">
    <div class="pl-error-msg" data-pl-element="error">
      <div class="pl-error-icon-wrapper">
        <i class="material-icons">
          error
        </i>
      </div>
      <div id="pl-error-text">
      </div>
    </div>
  </div>

  <div id="pl-footer-template">
    <div class="row pl-footer-row">
      <div class="pl-system-info-panel hidden loading" id="pl-system-info-panel">
        <div class="pl-system-info-panel-close" id="pl-system-info-close-btn">
          <i class="material-icons">
            close
          </i>
        </div>

        <div class="pl-system-info-panel-content">
          <div class="md-checkbox">
            <label class="pl-form-section-input-checkbox-label">
              <input type="checkbox" id="pl-debug-mode-checkbox">
              <i class="md-checkbox-control"></i>
              <b>{l s='Debug mode' mod='packlink'}</b>
            </label>
          </div>

          <a href="{$getSystemInfoUrl}" value="packlink-debug-data.zip" download>
            <button class="btn btn-primary btn-lg">{l s='Download system info file' mod='packlink'}</button>
          </a>
        </div>

        <div class="pl-system-info-panel-loader">
          <b>{l s='Loading...' mod='packlink'}</b>
        </div>

      </div>


      <div class="col-sm-12 pl-footer-wrapper">
        <div class="pl-footer-system-info-wrapper">
          v{$pluginVersion} <span class="pl-system-info-open-btn"
                                  id="pl-system-info-open-btn">({l s='system info' mod='packlink'})</span>
        </div>
        <div class="pl-footer-copyright-wrapper">
          <a href="{$termsAndConditionsLink}" target="_blank">
              {l s='General conditions' mod='packlink'}
          </a>
          <p>{l s='Developed and managed by Packlink' mod='packlink'}</p>
        </div>
      </div>
    </div>
  </div>

</div>

<script type="application/javascript">
    Packlink.errorMsgs = {
        required: "{l s='This field is required.' mod='packlink'}",
        numeric: "{l s='Value must be valid number.' mod='packlink'}",
        invalid: "{l s='This field is not valid.' mod='packlink'}",
        phone: "{l s='This field must be valid phone number.' mod='packlink'}",
        titleLength: "{l s='Title can have at most 64 characters.' mod='packlink'}",
        greaterThanZero: "{l s='Value must be greater than 0.' mod='packlink'}",
        numberOfDecimalPlaces: "{l s='Field must have 2 decimal places.' mod='packlink'}",
        integer: "{l s='Field must be an integer.' mod='packlink'}"
    };

    Packlink.successMsgs = {
        shippingMethodSaved: '{l s='Shipping service successfully saved.' mod='packlink'}'
    };

    Packlink.state = new Packlink.StateController(
        {
            scrollConfiguration: {
                rowHeight: 75,
                scrollOffset: 0
            },

            shippingServiceMaxTitleLength: 64,
            hasTaxConfiguration: true,

            dashboardGetStatusUrl: "{$dashboardGetStatusUrl}",
            defaultParcelGetUrl: "{$defaultParcelGetUrl}",
            defaultParcelSubmitUrl: "{$defaultParcelSubmitUrl}",
            defaultWarehouseGetUrl: "{$defaultWarehouseGetUrl}",
            defaultWarehouseSubmitUrl: "{$defaultWarehouseSubmitUrl}",
            defaultWarehouseSearchPostalCodesUrl: "{$defaultWarehouseSearchPostalCodesUrl}",
            shippingMethodsGetAllUrl: "{$shippingMethodsGetAllUrl}",
            shippingMethodsActivateUrl: "{$shippingMethodsActivateUrl}",
            shippingMethodsDeactivateUrl: "{$shippingMethodsDeactivateUrl}",
            shippingMethodsSaveUrl: "{$shippingMethodsSaveUrl}",
            getSystemOrderStatusesUrl: "{$getSystemOrderStatusesUrl}",
            orderStatusMappingsGetUrl: "{$orderStatusMappingsGetUrl}",
            orderStatusMappingsSaveUrl: "{$orderStatusMappingsSaveUrl}",
            shopShippingMethodCountGetUrl: "{$shopShippingMethodCountGetUrl}",
            shopShippingMethodsDisableUrl: "{$shopShippingMethodsDisableUrl}",
            debugGetStatusUrl: "{$debugGetStatusUrl}",
            debugSetStatusUrl: "{$debugSetStatusUrl}",
            shippingMethodsGetTaxClasses: "{$shippingMethodsGetTaxClasses}",
            autoConfigureStartUrl: "{$autoConfigureStartUrl}"
        }
    );

    let inheritShowFlashMessage = Packlink.utilityService.showFlashMessage;

    Packlink.utilityService.showFlashMessage = function (message, status) {
        inheritShowFlashMessage(message, status);

        let prestaAlerts = document.getElementsByClassName('alert');

        for (let alert of prestaAlerts) {
            alert.style.display = 'none';
        }
    };

    Packlink.state.display();

    hidePrestaSpinner();
    calculateContentHeight(60);
</script>