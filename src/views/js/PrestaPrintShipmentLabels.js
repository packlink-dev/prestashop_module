var Packlink = window.Packlink || {};

document.addEventListener('DOMContentLoaded', function () {
  let bulkDownloadAction = document.getElementById('order_grid_bulk_action_packlink_bulk_download_labels');
  if (bulkDownloadAction) {
    bulkDownloadAction.addEventListener('click', bulkDownloadLabels);
  }

  let bulkBrowserPrintAction = document.getElementById('order_grid_bulk_action_packlink_bulk_browser_print_labels');
  if (bulkBrowserPrintAction) {
    bulkBrowserPrintAction.addEventListener('click', bulkBrowserPrintLabels);
  }
});

/**
 * Orders-list per-row Download click handler. Opens the PDF in a new tab
 * (existing UX) and immediately greys both row buttons since either action
 * marks the label as "actioned" server-side; greying matches what the next
 * page render will show.
 *
 * @param {object} element
 */
function plDownloadLabelOnOrdersPage(element) {
  plGreyOrdersListRowButtons(element);

  let printLabelsUrl = document.getElementById('pl-print-labels-url').textContent;
  plOpenPdfTab(printLabelsUrl, [element.getAttribute('data-order')]);
}

/**
 * Orders-list per-row Print click handler. Triggers the browser print
 * dialog via the bulk endpoint with mode=print + a single orders[]; greys
 * both row buttons immediately so the row already reflects the actioned
 * state behind the dialog.
 *
 * @param {object} element
 */
function plPrintLabelOnOrdersPage(element) {
  plGreyOrdersListRowButtons(element);

  let printLabelsUrl = document.getElementById('pl-print-labels-url').textContent;
  plOpenPdfPrint(printLabelsUrl, [element.getAttribute('data-order')]);
}

/**
 * Applies the "actioned" grey style to every <a> button in the same row as
 * `element`. Used after either Download or Print is clicked so both buttons
 * visually settle into the same state without waiting for a page reload.
 *
 * @param {object} element
 */
function plGreyOrdersListRowButtons(element) {
  let parent = element.parentElement;
  if (!parent) {
    return;
  }
  let buttons = parent.children;
  for (let i = 0; i < buttons.length; i++) {
    let btn = buttons[i];
    if (btn.tagName !== 'A') {
      continue;
    }
    btn.style.color = '#c3c3c3';
    let icon = btn.querySelector('i');
    if (icon) {
      icon.style.color = '#c3c3c3';
    }
  }
}

/**
 * Sets shipment label on order details page to have been printed.
 *
 * @param {object} element
 */
function plDownloadLabelOnOrderDetailsPage(element) {
  let printed = element.dataset.labelPrinted,
      labelPrintedText = document.getElementById('pl-label-printed');
  if (!printed) {
    let labelRow = element.parentElement.parentElement,
        status = labelRow.childNodes[5]; // Table data element that represent shipment label status.

    status.textContent = labelPrintedText ? labelPrintedText.textContent : 'Printed';
  }

  let printLabelsUrl = document.getElementById('pl-print-labels-url').textContent;
  plOpenPdfTab(printLabelsUrl, [element.getAttribute('data-order')]);
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
 * Documents-table per-row Download click handler. Navigates the browser
 * to the ShipmentDocuments controller; the controller streams with
 * Content-Disposition: attachment so the file is saved without leaving
 * the order-detail page.
 *
 * @param {object} element
 */
function plDownloadDocument(element) {
  let url = plBuildDocumentUrl(element, 'documentDownloadUrl');
  window.location.href = url;
  plMarkDocumentPrintedInDom(element);
}

/**
 * Documents-table per-row Print click handler. Loads the PDF in a hidden
 * iframe via core PrintService, fires the browser print dialog, then
 * flips the row status to "Printed".
 *
 * @param {object} element
 */
function plPrintDocument(element) {
  let url = plBuildDocumentUrl(element, 'documentPrintUrl');
  if (!Packlink.printService) {
    plOpenPdfTab(url, []);
    return;
  }
  Packlink.printService.printPdf(url, function () {
    plMarkDocumentPrintedInDom(element);
  });
}

/**
 * Builds an absolute ShipmentDocuments URL by reading the relevant hidden
 * <p> element (id "pl-document-download-url" or "pl-document-print-url")
 * and appending orderId/type/link from the row's data-attributes.
 *
 * @param {object} element  The clicked <a> with data-order/data-type/data-link.
 * @param {string} dataKey  Either "documentDownloadUrl" or "documentPrintUrl".
 * @returns {string}
 */
function plBuildDocumentUrl(element, dataKey) {
  let elementId = 'pl-' + dataKey.replace(/([A-Z])/g, '-$1').toLowerCase();
  let baseElement = document.getElementById(elementId);
  let url = new URL(baseElement.textContent.trim());
  url.searchParams.set('orderId', element.getAttribute('data-order'));
  url.searchParams.set('type', element.getAttribute('data-type'));
  url.searchParams.set('link', element.getAttribute('data-link'));
  return url.href;
}

/**
 * Flips the row's status text to "Printed" + adds the .pl-printed class.
 *
 * @param {object} element  The clicked <a> inside the document row.
 */
function plMarkDocumentPrintedInDom(element) {
  let row = element.closest('tr');
  if (!row) {
    return;
  }
  let status = row.querySelector('.pl-document-status');
  let printedText = document.getElementById('pl-label-printed');
  if (status && printedText) {
    status.textContent = printedText.textContent;
    status.classList.add('pl-printed');
  }
}

/**
 * PS 1.7+ bulk-Download button click handler.
 *
 * @param {Event} event Click event passed by addEventListener.
 */
function bulkDownloadLabels(event) {
  let orders = document.getElementsByName('order_orders_bulk[]'),
      selectedOrders = [];

  orders.forEach(function (order) {
    if (order.checked) {
      selectedOrders.push(parseInt(order.value));
    }
  });

  bulkDownloadSelectedLabels(selectedOrders);

  event.stopPropagation();
}

/**
 * PS 1.7+ bulk-Print (browser) button click handler.
 *
 * @param {Event} event Click event passed by addEventListener.
 */
function bulkBrowserPrintLabels(event) {
  let orders = document.getElementsByName('order_orders_bulk[]'),
      selectedOrders = [];

  orders.forEach(function (order) {
    if (order.checked) {
      selectedOrders.push(parseInt(order.value));
    }
  });

  event.stopPropagation();

  if (selectedOrders.length === 0) {
    return;
  }

  let printLabelsUrl = document.getElementById('pl-print-labels-url').textContent;
  plOpenPdfPrint(printLabelsUrl, selectedOrders);
}

/**
 * Overrides default send bulk action function (PS 1.6).
 *
 * @param {form} form
 * @param {string} action
 */
function sendBulkAction(form, action) {
  if (action === 'submitBulkdownloadShipmentLabelsorder') {
    let orders = document.getElementsByName('orderBox[]'),
        selectedOrders = [];

    orders.forEach(function (order) {
      if (order.checked) {
        selectedOrders.push(parseInt(order.defaultValue));
      }
    });

    bulkDownloadSelectedLabels(selectedOrders);
  } else if (action === 'submitBulkbrowserPrintShipmentLabelsorder') {
    let orders = document.getElementsByName('orderBox[]'),
        selectedOrders = [];

    orders.forEach(function (order) {
      if (order.checked) {
        selectedOrders.push(parseInt(order.defaultValue));
      }
    });

    if (selectedOrders.length === 0) {
      return;
    }

    let printLabelsUrl = document.getElementById('pl-print-labels-url').textContent;
    plOpenPdfPrint(printLabelsUrl, selectedOrders);
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

function bulkDownloadSelectedLabels(selectedOrders) {
  let labels = document.getElementsByClassName('shipment-label'),
      labelPrintedText = document.getElementById('pl-label-printed'),
      printLabelsUrl = document.getElementById('pl-print-labels-url').textContent;

  if (selectedOrders.length > 0 && labels !== undefined && labels.length > 0) {
    for (let i = 0; i < labels.length; i++) {
      let childNodes = labels[i].childNodes,
          iconElement = childNodes[1];

      if (selectedOrders.includes(parseInt(labels[i].dataset.order))
          && iconElement.style.color !== '#c3c3c3'
      ) {
        labels[i].title = labelPrintedText ? labelPrintedText.textContent : 'Printed';
        iconElement.style.color = '#c3c3c3';
      }
    }
  }

  plOpenPdfTab(printLabelsUrl, selectedOrders);
}

/**
 * Opens the bulk shipment-labels PDF in a new tab. Used by the Download flows
 * (per-row + bulk) so the original orders list stays open.
 */
function plOpenPdfTab(printLabelsUrl, selectedOrders) {
  let disablePopupText = document.getElementById('pl-disable-popup'),
      url = new URL(printLabelsUrl);

  for (let selectedOrder of selectedOrders) {
    url.searchParams.append('orders[]', selectedOrder);
  }

  let pdfTab = window.open(url.href, '_blank');

  if (!pdfTab || pdfTab.closed) {
    alert(disablePopupText
        ? disablePopupText.textContent
        : 'Please disable pop-up blocker on this page in order to bulk open shipment labels'
    );
  }
}

/**
 * Builds the print-mode URL and triggers the browser's native print dialog
 * via core PrintService. Falls back to a new-tab open if PrintService.js is
 * not loaded for some reason.
 */
function plOpenPdfPrint(printLabelsUrl, selectedOrders) {
  let url = new URL(printLabelsUrl);
  url.searchParams.set('mode', 'print');
  for (let selectedOrder of selectedOrders) {
    url.searchParams.append('orders[]', selectedOrder);
  }

  if (!Packlink.printService) {
    plOpenPdfTab(printLabelsUrl, selectedOrders);
    return;
  }

  Packlink.printService.printPdf(url.href, function () {
    window.location.reload();
  });
}
