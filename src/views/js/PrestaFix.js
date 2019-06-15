/**
 * Hides presta shop spinner.
 */
function hidePrestaSpinner() {
  let prestaSpinner = document.getElementById("ajax_running");

  if (prestaSpinner) {
    prestaSpinner.style.display = "none";
  }
}

/**
 * Calculates content height.
 *
 * Footer can be dynamically hidden or displayed by Prestashop,
 * so we have to periodically recalculate content height.
 *
 * @param {number} offset
 */
function calculateContentHeight(offset) {
  if (typeof offset === 'undefined') {
    offset = 0;
  }

  let localOffset = offset;

  let footer = document.getElementById('footer');
  if (footer) {
    localOffset += footer.clientHeight;
  }

  let alerts = document.getElementsByClassName('alert');

  for (let alert of alerts) {
    if (alert.clientHeight) {
      localOffset += 71;
    }
  }

  let content = document.getElementById('pl-main-page-holder');
  content.style.height = `calc(100% - ${localOffset}px`;

  setTimeout(calculateContentHeight, 250, offset);
}