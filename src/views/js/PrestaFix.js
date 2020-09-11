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

  let content = document.getElementById('pl-page');
  let localOffset = offset + content.offsetTop + 20;

  let footer = document.getElementById('footer');
  if (footer) {
    localOffset += footer.clientHeight;
  }

  content.style.height = `calc(100vh - ${localOffset}px`;

  setTimeout(calculateContentHeight, 250, offset);
}