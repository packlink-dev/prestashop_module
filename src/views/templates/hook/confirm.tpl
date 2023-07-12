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
    position: relative;
    max-width: 840px;
    min-height: 170px;
    display: flex;
    justify-content: center;
    flex-flow: column;
    padding: 10px 30px;
    background: white;
    border-radius: 3px;
  }

  .pl-button-wrapper {
    display: flex;
    justify-content: space-between;
    width: 100%;
  }

  .pl-message-wrapper {
    display: inline-block;
    padding-right: 20px;
  }

  .pl-btn {
    display: inline-block;
    align-self: flex-start;
  }

  .pl-close-msg-btn {
    box-shadow: none !important;
  }

  .pl-confirm-message {
    margin-bottom: 30px;
  }

  .pl-hidden {
    display: none !important;
  }
</style>


<div class="pl-confirm-mask" id="pl-confirm-mask">
  <div class="pl-confirm-message-box">
    <div class="pl-close-modal pl-close-msg-btn pl-hidden" id="pl-close-message-box-btn">X</div>
    <p class="pl-confirm-message">
        {l s='Selected shipping method delivers only to predefined drop-off locations. Please choose your drop-off location.' mod='packlink'}
    </p>

    <div class="pl-button-wrapper">
      <div class="pl-message-wrapper">
        <span id="pl-no-locations" class="pl-hidden">
          <i>{l s='There are no delivery locations available.' mod='packlink'}</i>
        </span>
        <span id="pl-message" class="pl-hidden">
            <i>{l s='Package will be delivered to:' mod='packlink'}</i> <br />
            <span id="pl-location-address"></span>
        </span>
      </div>
      <div class="btn btn-primary pl-btn" id="pl-dropoff-button">
        <span id="pl-select-label">{l s='Select drop-off location' mod='packlink'}</span>
        <span id="pl-change-label" class="pl-hidden">{l s='Change drop-off location' mod='packlink'}</span>
      </div>
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
        let configuration = JSON.parse(
            '{$configuration|json_encode|escape:'htmlall':'UTF-8'|htmlspecialchars_decode:3}'
                .replace(/&quot;/g, '"')
                .replace(/&amp;/g, '&')
        );
        let closeMessageBoxBtn = document.getElementById('pl-close-message-box-btn');

        selectBtn.addEventListener('click', onSelectButtonClicked);
        closeMessageBoxBtn.addEventListener('click', onCloseMessageBoxBtnClicked);

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

                if (payload.type === 'no-locations') {
                    displayNoLocations();
                }

                if (payload.type === 'success') {
                    displaySuccess()
                }

                function displayNoLocations() {
                  closeMessageBoxBtn.classList.remove('pl-hidden');
                  document.getElementById('pl-no-locations').classList.remove('pl-hidden');
                  selectBtn.classList.add('pl-hidden');
                }

                function displaySuccess() {
                  closeMessageBoxBtn.classList.remove('pl-hidden');
                  document.getElementById('pl-message').classList.remove('pl-hidden');
                  document.getElementById('pl-location-address').innerText = payload.address;
                  document.getElementById('pl-select-label').classList.add('pl-hidden');
                  document.getElementById('pl-change-label').classList.remove('pl-hidden');
                }
            }
        }

        function onCloseMessageBoxBtnClicked() {
            confirmMask.remove();
        }
    })();
</script>