/**
 * 2019 Packlink
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Packlink <support@packlink.com>
 * @copyright 2019 Packlink Shipping S.L
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

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