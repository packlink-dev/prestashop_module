var Packlink = window.Packlink || {};

(function () {

  function CheckOutControllerConstructor(configuration) {
    let shippingService = Packlink.shippingService;

    this.init = init;

    let currentMethod = null;
    let dropOffIds = [];
    let cashOnDeliveryIds = [];

    let mapController = null;

    let selectedId = null;
    let ids = [];

    // Pending trailing re-applies of the duty presentation. @see scheduleDdpReapply
    let ddpReapplyTimers = [];

    function init() {
      dropOffIds = Object.keys(configuration.dropoffIds);
      cashOnDeliveryIds = configuration.cashOnDelivery;

      let dropOffs = shippingService.getDropOffShippingMethods(dropOffIds);
      if (shippingService.markCashOnDeliveryMethods) {
       ids = shippingService.markCashOnDeliveryMethods(cashOnDeliveryIds);
      }

      let selectedLocation = configuration.selectedLocation;
      let selectedCarrier = configuration.selectedCarrier;

      if (selectedLocation) {
        selectedId = selectedLocation['id'];
      }

      for (let dropOff of dropOffs) {
        if (dropOff.checked && dropOff.getAttribute('data-pl-dropoff') === 'true') {
          currentMethod = dropOff;

          if (currentMethod.getAttribute('data-pl-id') === selectedCarrier) {
            showDropoff(Packlink.trans.change);
            shippingService.setMessage(Packlink.trans.address + '<br /> <i>' + getAddressString(selectedLocation) + '</i>');
          } else {
            showDropoff(Packlink.trans.select, true);
          }
        }

        dropOff.addEventListener('change', dropoffChangedHandler);
      }

      for(cod of ids) {
        if (cod.checked && cod.getAttribute('data-pl-cod') === 'true') {
          shippingService.showCODMessage(cod, configuration.offlineMethod);
        }

        cod.addEventListener('change', codChangedHandler);
      }

      if (shippingService.markDdpMethods) {
        setUpDdpPresentation();
      }
    }

    /**
     * Wires the duties-paid presentation: initial labels, a delegated change listener and a DOM
     * observer. The theme replaces the delivery block over AJAX whenever the shopper changes option,
     * which discards both the marked radios and the injected labels — element-bound listeners and
     * one-shot timers die with it, so the wiring must live on the document instead.
     */
    function setUpDdpPresentation() {
      applyDdpLabels();

      // The step bootstrap can run more than once; one listener and one observer serve the page.
      if (window.plDdpPresentationBound) {
        return;
      }
      window.plDdpPresentationBound = true;

      // Delegated, so it survives the delivery block being replaced.
      document.addEventListener('change', function (event) {
        let el = event.target;
        if (el && el.type === 'radio' && (el.getAttribute('name') || '').includes('delivery_option')) {
          scheduleDdpReapply();
        }
      });

      // Re-apply whenever a theme re-render leaves the labels missing or stale. Deliberately
      // synchronous: mutation callbacks run before the next paint, so a wiped label is restored
      // before the browser ever draws the page without it — a deferred re-apply reads as a blink.
      // applyDdpLabels() is a no-op when the page is already correct, which also ends the cycle
      // its own insertions would otherwise cause.
      let observer = new MutationObserver(function (records) {
        // Ignore our own insertions and anything outside the delivery options / summary panel, so an
        // unrelated script mutating the page does not make us scan the DOM on every mutation.
        for (let record of records) {
          if (isRelevantMutation(record)) {
            applyDdpLabels();

            return;
          }
        }
      });

      observer.observe(document.body, {childList: true, subtree: true});

      // The observer alone is not enough. The theme replaces the delivery block and the summary panel
      // in the same AJAX response, so a mutation can be delivered while the summary is momentarily
      // absent - and with no shipping row on the page isDdpDisplayCorrect() reports "nothing to fix",
      // which turns that pass into a no-op and leaves the label wiped until some later mutation
      // happens to arrive. PrestaShop's own events fire after the re-render has settled, so the panel
      // is present and the re-apply lands.
      if (window.prestashop && typeof window.prestashop.on === 'function') {
        window.prestashop.on('updatedDeliveryForm', scheduleDdpReapply);
        window.prestashop.on('updatedCart', scheduleDdpReapply);
      }
    }

    /**
     * Re-applies the duty presentation now and again shortly after.
     *
     * Re-selecting an option makes the theme replace the delivery block and the summary panel in
     * separate steps, in no guaranteed order. A single pass therefore loses the race: it restores the
     * row, the next replacement wipes it, and the pass that could repair it either already ran or
     * lands while the panel is absent - where isDdpDisplayCorrect() reports "nothing to fix" and
     * no-ops. The trailing passes outlive that sequence, so the last one always sees the settled DOM.
     *
     * Safe to over-call: applyDdpLabels() is idempotent, returns early when the page is already
     * correct, and removes the row itself when the current selection carries no duty.
     */
    function scheduleDdpReapply() {
      applyDdpLabels();

      for (let timer of ddpReapplyTimers) {
        clearTimeout(timer);
      }

      ddpReapplyTimers = [150, 600, 1500].map(function (delay) {
        return setTimeout(applyDdpLabels, delay);
      });
    }

    /**
     * Whether a mutation could have disturbed the duty presentation.
     *
     * @param {MutationRecord} record
     * @return {boolean}
     */
    function isRelevantMutation(record) {
      let target = record.target;
      if (!target || target.nodeType !== 1) {
        return false;
      }

      if (target.closest('.pl-ddp-summary-inserted')) {
        return false;
      }

      // Our row is missing while duty is on offer: repair on ANY structural change, wherever it
      // happened. The theme can replace the summary from a wrapper that matches none of the selectors
      // below - closest() walks upwards, so a mutation reported on that wrapper is judged irrelevant
      // and the synchronous repair never runs, leaving the trailing timer to fix it a visible blink
      // later. Deciding here keeps the repair inside the mutation callback, before the next paint.
      if (configuration.ddpCosts
        && Object.keys(configuration.ddpCosts).length
        && !document.querySelector('.pl-ddp-summary-inserted')
      ) {
        return true;
      }

      return !!target.closest(
        '#checkout-delivery-step, .js-cart-summary-subtotals-container, .cart-summary__subtotals,'
        + ' .cart-summary, #js-checkout-summary, .delivery-options, .delivery-option'
      );
    }

    /**
     * Marks the delivery options, labels every duties-paid one and syncs the summary row with the
     * current selection. Idempotent — each pass replaces what the previous one inserted.
     */
    function applyDdpLabels() {
      // Skip when the page is already right: re-inserting identical labels only makes them flicker.
      if (shippingService.isDdpDisplayCorrect
        && shippingService.isDdpDisplayCorrect(configuration.ddpCosts)
      ) {
        return;
      }

      let marked = shippingService.markDdpMethods(configuration.ddpCosts, configuration.ddpTransport);

      let selected = null;
      for (let el of marked) {
        if (el.checked && el.getAttribute('data-pl-ddp') === 'true') {
          selected = el;
        }
      }

      if (selected) {
        shippingService.showDdpMessage(selected, configuration.ddpLabel);
      } else if (shippingService.hideDdpMessage) {
        shippingService.hideDdpMessage();
      }
    }

    /**
     * Handles changed event
     *
     * @param event
     */
    function codChangedHandler(event) {
      currentMethod = event.target;

      if (currentMethod.getAttribute('data-pl-cod') === 'true') {
        shippingService.showCODMessage(currentMethod,  configuration.offlineMethod);
      } else {
        shippingService.hideCODMessage();
      }
    }

    /**
     * Handles changed event
     *
     * @param event
     */
    function dropoffChangedHandler(event) {
      currentMethod = event.target;

      if (currentMethod.getAttribute('data-pl-dropoff') === 'true') {
        showDropoff(Packlink.trans.select, true);
      } else {
        hideDropoff();
      }
    }

    /**
     * Handles click event on select dropoff button.
     */
    function dropOffButtonClickedHandler() {
      let id = currentMethod.getAttribute('data-pl-id');

      mapController = new Packlink.MapModalController(
          {
            getUrl: configuration.getLocationsUrl,
            methodId: configuration.dropoffIds[id],
            carrierId: id,
            onComplete: modalCompleteCallback,
            dropOffId: selectedId,
            lang: configuration.lang
          }
      );

      mapController.display();
    }

    /**
     * Handles callback from modal.
     *
     * @param payload
     */
    function modalCompleteCallback(payload) {
      mapController.close();
      mapController = null;

      if (payload.type === 'no-locations') {
        shippingService.setMessage(Packlink.trans.wrongAddress);
        return;
      }

      if (payload.type === 'close') {
        if (selectedId) {
          shippingService.enableSubmit();
        }
        return;
      }

      if (payload.type === 'success') {
        selectedId = payload.dropOff.id;
        shippingService.setMessage(Packlink.trans.address + '<br/> <i>' + payload.address + '</i>');
        shippingService.changeBtnText(Packlink.trans.change);
        shippingService.enableSubmit();
      }
    }

    /**
     * Shows select dropoff section.
     *
     * @param {string} btnMsg
     * @param {boolean} [disable]
     */
    function showDropoff(btnMsg, disable) {
      shippingService.showDropOff(dropOffButtonClickedHandler, currentMethod, btnMsg);
      shippingService.setMessage('');

      if (disable) {
        shippingService.disableSubmit();
      }
    }

    /**
     * Hides dropoff select section.
     */
    function hideDropoff() {
      shippingService.hideDropOff();
      shippingService.enableSubmit();
    }

    /**
     * Returns formatted address.
     *
     * @param {object} location
     * @return {string}
     */
    function getAddressString(location) {
      return location['name'] + ', ' + location['address'] + ', ' + location['zip'] + ', ' + location['city'];
    }
  }

  Packlink.CheckOutController = CheckOutControllerConstructor;
})();
