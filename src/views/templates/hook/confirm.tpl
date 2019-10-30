<style>
  .pl-confirm-mask {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: rgba(0.1, 0.1, 0.1, 0.7);
    z-index: 5599;
    flex-flow: column;
  }

  .pl-confirm-message-box {
    max-width: 840px;
    min-height: 170px;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-flow: column;
    padding: 10px 30px;
    background: white;
    border-radius: 3px;
  }

  .pl-confirm-message {
    margin-bottom: 30px;
  }
</style>


<div class="pl-confirm-mask" id="pl-confirm-mask">
  <div class="pl-confirm-message-box">
    <p class="pl-confirm-message">
        {l s='Selected shipping method delivers only to predefined drop-off locations. Please choose your drop-off location.' mod='packlink'}
    </p>
    <div class="btn btn-primary" id="pl-dropoff-button">
        {l s='Select drop-off location' mod='packlink'}
    </div>
  </div>
</div>


<div class="pl-input-mask hidden" id="pl-map-modal">
  <div class="pl-map-modal" id="pl-modal-content">
    <div class="pl-modal-spinner-wrapper disabled" id="pl-modal-spinner">
      <div class="pl-modal-spinner"></div>
    </div>
    <div class="pl-close-modal" id="pl-close-modal-btn">X</div>

    <location-picker>
      <div class="lp-content" data-lp-id="content">
        <div class="lp-locations">
          <div class="lp-input-wrapper">
            <div class="input">
              <input type="text" title="" required data-lp-id="search-box">
              <span class="label" data-lp-id="search-box-label"></span>
            </div>
          </div>

          <div data-lp-id="locations"></div>
        </div>
      </div>
    </location-picker>

  </div>
</div>


<location-picker-template>
  <div class="lp-template" id="template-container">
    <div data-lp-id="working-hours-template" class="lp-hour-wrapper">
      <div class="day" data-lp-id="day">
      </div>
      <div class="hours" data-lp-id="hours">
      </div>
    </div>

    <div class="lp-location-wrapper" data-lp-id="location-template">
      <div class="composite lp-expand">
        <div class="street-name uppercase" data-lp-id="composite-address"></div>
        <div class="lp-working-hours-btn excluded" data-lp-composite data-lp-id="show-composite-working-hours-btn"></div>
        <div data-lp-id="composite-working-hours" class="lp-working-hours">

        </div>
        <div class="lp-select-column">
          <div class="lp-select-button excluded" data-lp-id="composite-select-btn"></div>
          <a class="excluded" href="#" data-lp-id="composite-show-on-map" target="_blank"></a>
        </div>
      </div>
      <div class="name uppercase lp-collapse" data-lp-id="location-name"></div>
      <div class="street lp-collapse">
        <div class="street-name uppercase" data-lp-id="location-street"></div>
        <div class="lp-working-hours-btn excluded" data-lp-id="show-working-hours-btn"></div>
        <div data-lp-id="working-hours" class="lp-working-hours">

        </div>
      </div>
      <div class="city uppercase lp-collapse" data-lp-id="location-city">
      </div>
      <div class="lp-select-column lp-collapse">
        <div class="lp-select-button excluded" data-lp-id="select-btn"></div>
      </div>
      <a class="excluded lp-collapse" href="#" data-lp-id="show-on-map" target="_blank">
        <div class="lp-show-on-map-btn excluded"></div>
      </a>
    </div>
  </div>
</location-picker-template>

<script>
    (function () {
        let selectBtn = document.getElementById('pl-dropoff-button');
        let confirmMask = document.getElementById('pl-confirm-mask');
        let configuration = JSON.parse('{$configuration}'.replace(/&quot;/g, '"').replace(/&amp;/g, '&'));

        selectBtn.addEventListener('click', onSelectButtonClicked);

        function onSelectButtonClicked() {
            let id = configuration.id;
            let mapController = new Packlink.MapModalController(
                {
                    getUrl: configuration.getLocationsUrl,
                    methodId: configuration.dropoffIds[id],
                    carrierId: id,
                    onComplete: modalCompleteCallback,
                    dropOffId: null,
                    lang: configuration.lang,
                    addressId: configuration.addressId,
                    cartId: configuration.cartId,
                    orderId: configuration.orderId
                }
            );

            mapController.display();

            function modalCompleteCallback(payload) {
                mapController.close();
                mapController = null;

                if (payload.type === 'success' || payload.type === 'no-locations') {
                    confirmMask.remove();
                }
            }
        }
    })();
</script>