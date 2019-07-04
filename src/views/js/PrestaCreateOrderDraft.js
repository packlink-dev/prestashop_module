var Packlink = window.Packlink || {};

/**
 * Creates order draft for the order with provided ID.
 *
 * @param {object} element
 */
function plCreateOrderDraft(element) {
  let ajaxService = Packlink.ajaxService,
      orderId = parseInt(element.dataset.order),
      controllerUrl = element.dataset.createDraftUrl;

  element.disabled = true;
  ajaxService.post(
      controllerUrl,
      {orderId: orderId},
      function () {
        window.location.reload();
      },
      function () {
        element.disabled = false;
      }
  );
}