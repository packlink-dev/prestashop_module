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

var Packlink = window.Packlink || {};

(function () {
  function AjaxService() {
    this.get = get;
    this.post = post;

    /**
     * Performs GET ajax request.
     *
     * @param {string} url
     * @param {function} onSuccess
     * @param {function} [onError]
     */
    function get(url, onSuccess, onError) {
      call('GET', url, {}, onSuccess, onError)
    }

    /**
     * Performs POST ajax request.
     *
     * @note You can not post data that has fields with special values such as infinity, undefined etc.
     *
     * @param {string} url
     * @param {object} data
     * @param {function} onSuccess
     * @param {function} [onError]
     */
    function post(url, data, onSuccess, onError) {
      call('POST', url, data, onSuccess, onError)
    }

    /**
     * Performs ajax call.
     *
     * @param {'GET' | 'POST'} method
     * @param {string} url
     * @param {object} data
     * @param {function} onSuccess
     * @param {function} [onError]
     */
    function call(method, url, data, onSuccess, onError) {
      let request = getRequest();
      request.open(method, url, true);

      request.onreadystatechange = function () {
        if (this.readyState === 4) {
          if (this.status >= 200 && this.status < 300) {
            onSuccess(JSON.parse(this.responseText || '{}'));
          } else {
            if (typeof onError !== 'undefined') {
              onError(JSON.parse(this.responseText || '{}'));
            }
          }
        }
      };

      if (method === 'POST') {
        request.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        request.send(getFormattedData(data));
      } else {
        request.send();
      }
    }

    /**
     * Creates instance of request.
     *
     * @return {XMLHttpRequest | ActiveXObject}
     */
    function getRequest() {
      if (typeof XMLHttpRequest !== 'undefined') {
        return new XMLHttpRequest();
      }

      let versions = [
        'MSXML2.XmlHttp.6.0',
        'MSXML2.XmlHttp.5.0',
        'MSXML2.XmlHttp.4.0',
        'MSXML2.XmlHttp.3.0',
        'MSXML2.XmlHttp.2.0',
        'Microsoft.XmlHttp',
      ];

      let xhr;
      for (let version of versions) {
        try {
          xhr = new ActiveXObject(version);
          break;
        } catch (e) {
        }
      }

      return xhr;
    }

    /**
     * Returns data formatted for post request.
     *
     * @param {object} data
     *
     * @return {string}
     */
    function getFormattedData(data) {
      return 'plPostData=' + encodeURIComponent(JSON.stringify(data));
    }
  }

  Packlink.ajaxService = new AjaxService();
})();