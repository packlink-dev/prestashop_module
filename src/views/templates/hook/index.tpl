<div id="pl-page">
  <header id="pl-main-header">
    <div class="pl-main-logo">
      <img src="https://cdn.packlink.com/apps/giger/logos/packlink-pro.svg" alt="logo">
    </div>
    <div class="pl-header-holder" id="pl-header-section"></div>
  </header>

  <main id="pl-main-page-holder"></main>

  <div class="pl-spinner pl-hidden" id="pl-spinner">
    <div></div>
  </div>

  <template id="pl-alert">
    <div class="pl-alert-wrapper">
      <div class="pl-alert">
        <span class="pl-alert-text"></span>
        <i class="material-icons">close</i>
      </div>
    </div>
  </template>

  <template id="pl-modal">
    <div id="pl-modal-mask" class="pl-modal-mask pl-hidden">
      <div class="pl-modal">
        <div class="pl-modal-close-button">
          <i class="material-icons">close</i>
        </div>
        <div class="pl-modal-title">

        </div>
        <div class="pl-modal-body">

        </div>
        <div class="pl-modal-footer">
        </div>
      </div>
    </div>
  </template>

  <template id="pl-error-template">
    <div class="pl-error-message" data-pl-element="error">
    </div>
  </template>
</div>

<script type="text/javascript" src="{$gridResizerScript}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        Packlink.translations = {
            default: {$lang['default']|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3},
            current: {$lang['current']|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}
        };

        let pageConfiguration = {json_encode($urls)|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3};

        Packlink.state = new Packlink.StateController(
            {
                baseResourcesUrl: "{$baseResourcesUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}",
                stateUrl: "{$stateUrl|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}",
                pageConfiguration: pageConfiguration,
                templates: {json_encode($templates)|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}
            }
        );

        Packlink.state.display();

        hidePrestaSpinner();
        calculateContentHeight(5);
    });
</script>
