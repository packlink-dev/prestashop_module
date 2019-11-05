var Packlink = window.Packlink || {};

/**
 * Sets shipment label on orders page to have been printed.
 *
 * @param {object} element
 */
function plPrintLabelOnOrdersPage(element) {
  let childNodes = element.childNodes,
      iconElement = childNodes[1],
      labelPrintedText = document.getElementById('pl-label-printed');
  if (iconElement.style.color !== 'grey') {
    element.title = labelPrintedText ? labelPrintedText.innerText : 'Printed';
    iconElement.style.color = 'grey';
  }

  let printLabelsUrl = document.getElementById('pl-print-labels-url').innerText;
  plOpenPdfTab(printLabelsUrl, ['orders[]=' + element.getAttribute('data-order')]);
}

/**
 * Sets shipment label on order details page to have been printed.
 *
 * @param {object} element
 */
function plPrintLabelOnOrderDetailsPage(element) {
  let printed = element.dataset.labelPrinted,
      labelPrintedText = document.getElementById('pl-label-printed');
  if (!printed) {
    let labelRow = element.parentElement.parentElement,
        status = labelRow.childNodes[5]; // Table data element that represent shipment label status.

    status.innerText = labelPrintedText ? labelPrintedText.innerText : 'Printed';
  }

  let printLabelsUrl = document.getElementById('pl-print-labels-url').innerText;
  plOpenPdfTab(printLabelsUrl, ['orders[]=' + element.getAttribute('data-order')]);
}

/**
 * Send AJAX request for printing shipment label.
 *
 * @param {object} element
 */
function ajaxLabelPrint(element) {
  let orderId = parseInt(element.dataset.order),
      labelLink = element.dataset.link,
      printLabelUrl = element.dataset.printLabelUrl,
      ajaxService = Packlink.ajaxService;

  ajaxService.post(
      printLabelUrl,
      {link: labelLink, orderId: orderId},
      function () {
      },
      function () {
      }
  );
}

/**
 * Overrides default send bulk action function.
 *
 * @param {form} form
 * @param {string} action
 */
function sendBulkAction(form, action) {
  if (action === 'submitBulkprintShipmentLabelsorder') {
    let orders = document.getElementsByName('orderBox[]'),
        labels = document.getElementsByClassName('shipment-label'),
        labelPrintedText = document.getElementById('pl-label-printed'),
        selectedOrders = [];

    orders.forEach(function (order) {
      if (order.checked) {
        selectedOrders.push('orders[]=' + parseInt(order.defaultValue));
      }
    });

    if (selectedOrders.length > 0 && labels !== undefined && labels.length > 0) {
      let printLabelsUrl = document.getElementById('pl-print-labels-url').innerText;

      for (let i = 0; i < labels.length; i++) {
        let childNodes = labels[i].childNodes,
            iconElement = childNodes[1];

        if (selectedOrders.includes(parseInt(labels[i].dataset.order))
            && iconElement.style.color !== 'grey'
        ) {
          labels[i].title = labelPrintedText ? labelPrintedText.innerText : 'Printed';
          iconElement.style.color = 'grey';
        }
      }

      plOpenPdfTab(printLabelsUrl, selectedOrders);
    }
  } else {
    // Default function behaviour.
    String.prototype.splice = function (index, remove, string) {
      return (this.slice(0, index) + string + this.slice(index + Math.abs(remove)));
    };

    var form_action = $(form).attr('action');

    if (form_action.replace(/(?:(?:^|\n)\s+|\s+(?:$|\n))/g, '').replace(/\s+/g, ' ') == '')
      return false;

    if (form_action.indexOf('#') == -1)
      $(form).attr('action', form_action + '&' + action);
    else
      $(form).attr('action', form_action.splice(form_action.lastIndexOf('&'), 0, '&' + action));

    $(form).submit();
  }
}

function plOpenPdfTab(printLabelsUrl, selectedOrders) {
  let disablePopupText = document.getElementById('pl-disable-popup');
  let pdfTab = window.open(
      printLabelsUrl + '&' + selectedOrders.join('&'),
      '_blank'
  );

  if (!pdfTab || pdfTab.closed) {
    alert(disablePopupText
        ? disablePopupText.innerText
        : 'Please disable pop-up blocker on this page in order to bulk open shipment labels'
    );
  }
}
