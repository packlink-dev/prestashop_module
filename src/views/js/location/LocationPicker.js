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
  function LocationPickerConstructor() {
    const days = [
      'monday',
      'tuesday',
      'wednesday',
      'thursday',
      'friday',
      'saturday',
      'sunday'
    ];

    const searchKeys = [
      'id',
      'zip',
      'name',
      'address',
      'city'
    ];

    let lang = null;
    let selectCallback = null;

    let templateContainer = null;

    let selectedLocation = null;
    let dropOffs = {};
    let renderedLocations = [];

    let locations = {};

    // Register public method and properties.
    this.display = display;

    /**
     * Initializes location picker.map.event.trigger('resize');
     *
     * @param {array} locations Array of Drop-Off locations.
     * @param {function} selectLocationCallback callback that is called when user selects location.
     * @param {string} [language] Translation language code. Defaults to 'en' for English.
     */
    function display(locations, selectLocationCallback, language) {
      templateContainer = document.getElementById('template-container');
      lang = typeof language === 'undefined' ? 'en' : language;
      selectCallback = selectLocationCallback;
      initializeSearchBox();
      renderLocations(locations);
    }


    /**
     * Message receiver.
     *
     * @param {object} payload
     */
    function renderLocations(payload) {
      renderedLocations = [];
      for (let loc of payload) {
        locations[loc.id] = loc;
        renderedLocations.push(loc.id);
      }

      addLocations();
    }

    /**
     * Initializes search-box.
     */
    function initializeSearchBox() {
      let searchBox = getElement(document, 'search-box');

      if (searchBox) {
        searchBox.addEventListener('keyup', debounce(200, searchBoxKeyupHandler));

        let searchLabel = getElement(document, 'search-box-label');
        searchLabel.innerHTML = getTranslation(lang, ['searchLabel']);
      }
    }

    /**
     * Handles keyup on search-box.
     *
     * @param event
     */
    function searchBoxKeyupHandler(event) {
      let value = event.target.value;

      renderedLocations = Object.keys(locations);

      if (value !== '') {
        renderedLocations = [];

        for (let term of searchKeys) {
          let result = filterByKey(locations, term, value);
          renderedLocations.push(...result);
        }

        renderedLocations = renderedLocations.filter(function (value, index, array) {
          return array.indexOf(value) === index;
        })
      }

      addLocations();
    }

    /**
     * Adds Locations.
     */
    function addLocations() {
      let locationsNode = getElement(document, 'locations');

      while (locationsNode.firstChild) {
        locationsNode.firstChild.remove();
      }

      for (let displayed in dropOffs) {
        if (dropOffs.hasOwnProperty(displayed)) {
          dropOffs[displayed].remove();
        }
      }

      dropOffs = {};
      selectedLocation = null;

      for (let id of renderedLocations) {
        let location = locations[id];
        let locationElement = createLocation(location);
        dropOffs[id] = locationElement;
        locationsNode.appendChild(locationElement);
      }
    }



    /**
     * Creates DropOff location.
     *
     * @param location
     * @return {Node}
     */
    function createLocation(location) {
      let template = getTemplate('location-template');

      getElement(template, 'location-name').innerHTML = location.name;
      getElement(template, 'location-street').innerHTML = location.address;
      getElement(template, 'location-city').innerHTML = location.city + ', ' + location.zip;
      getElement(template, 'composite-address').innerHTML = '<b>' + location.name + '</b>' + ', '
          + location.address + ', ' + location.city + ', ' + location.zip;

      let showOnMapBtn = getElement(template, 'show-on-map');
      showOnMapBtn.href = `https://www.google.com/maps/search/?api=1&query=${location['lat']},${location['long']}`;
      showOnMapBtn.title = getTranslation(lang, ['showOnMapTitle']);

      let showWorkingHoursBtn = getElement(template, 'show-working-hours-btn');
      showWorkingHoursBtn.innerHTML = getTranslation(lang, ['workingHoursLabel']);
      showWorkingHoursBtn.setAttribute('data-id', location['id']);
      showWorkingHoursBtn.addEventListener('click', workingHoursButtonClickHandler);

      let showCompositeHoursBtn = getElement(template, 'show-composite-working-hours-btn');
      showCompositeHoursBtn.innerHTML = getTranslation(lang, ['workingHoursLabel']);
      showCompositeHoursBtn.setAttribute('data-id', location['id']);
      showCompositeHoursBtn.addEventListener('click', workingHoursButtonClickHandler);


      let workingHoursNode = getElement(template, 'working-hours');
      let compositeHoursNode = getElement(template, 'composite-working-hours');

      for (let day of days) {
        if (location['workingHours'][day]) {
          let workingHoursTemplate = getWorkingHoursTemplate(day, location['workingHours'][day]);
          workingHoursNode.appendChild(workingHoursTemplate);
          compositeHoursNode.appendChild(workingHoursTemplate.cloneNode(true));
        }
      }

      let selectBtn = getElement(template, 'select-btn');
      selectBtn.innerHTML = getTranslation(lang, ['selectLabel']);
      selectBtn.addEventListener('click', function () {
        selectCallback(location['id']);
      });

      template.setAttribute('data-id', location.id);
      template.addEventListener('click', locationClickedHandler);

      return template;
    }

    /**
     * Handles click event on location.
     */
    function locationClickedHandler(event) {
      if (event.target.classList.contains('excluded') || event.target === selectedLocation) {
        return;
      }

      unselectSelectedLocation();

      let id = event.target.getAttribute('data-id');

      selectedLocation = dropOffs[id];
      selectedLocation.classList.add('selected');
    }

    /**
     * Un-selects location.
     */
    function unselectSelectedLocation() {
      if (selectedLocation) {
        selectedLocation.classList.remove('selected');
        selectedLocation = null;
      }
    }

    /**
     * Retrieves working hours template.
     *
     * @param {string} day
     * @param {string} hours
     * @return {Node}
     */
    function getWorkingHoursTemplate(day, hours) {
      let element = getTemplate('working-hours-template');

      let dayElement = getElement(element, 'day');
      dayElement.innerHTML = getTranslation(lang, ['days', day]);

      let hourElement = getElement(element, 'hours');
      hourElement.innerHTML = hours;

      return element;
    }

    /**
     * Handles working hours button clicked event.
     *
     * @param event
     */
    function workingHoursButtonClickHandler(event) {
      let id = event.target.getAttribute('data-id');
      let workingHoursNode = null;

      if (event.target.hasAttribute('data-lp-composite')) {
        workingHoursNode = getElement(dropOffs[id], 'composite-working-hours');
      } else {
        workingHoursNode = getElement(dropOffs[id], 'working-hours');
      }

      if (workingHoursNode.classList.contains('enabled')) {
        event.target.innerHTML = getTranslation(lang, ['workingHoursLabel']);
        workingHoursNode.classList.remove('enabled');
      } else {
        event.target.innerHTML = getTranslation(lang, ['hideWorkingHoursLabel']);
        workingHoursNode.classList.add('enabled');
      }

      dropOffs[id].click();
    }

    /**
     * Returns template element
     *
     * @param {string} template
     * @return {Node}
     */
    function getTemplate(template) {
      return templateContainer.querySelector(`[data-lp-id=${template}]`).cloneNode(true);
    }

    /**
     * Retrieves element in template.
     *
     * @param {Node} node
     * @param {string} element
     * @return {Element}
     */
    function getElement(node, element) {
      return node.querySelector(`[data-lp-id=${element}]`);
    }

    /**
     * Retrieves translation.
     *
     * @param {string} lang
     * @param {array} keys
     * @return {string}
     */
    function getTranslation(lang, keys) {
      let object = LocationPickerTranslations;

      object = object.hasOwnProperty(lang) ? object[lang] : object['en'];

      for (let key of keys) {
        object = object[key];

        if (typeof object === 'undefined') {
          return keys.join('.');
        }
      }

      return object;
    }

    /**
     * Filters hash-map by value.
     *
     * @param {object} collection
     * @param {string} key
     * @param {string} value
     */
    function filterByKey(collection, key, value) {
      let result = [];

      value = value.toUpperCase();

      for (let id in collection) {
        if (collection.hasOwnProperty(id)) {
          let target = collection[id][key].toUpperCase();
          if (target.match(new RegExp('.*' + value + '.*')) !== null) {
            result.push(id);
          }
        }
      }

      return result;
    }

    /**
     * Debounces function.
     *
     * @param {number} delay
     * @param {function} target
     * @return {Function}
     */
    function debounce(delay, target) {
      let timerId;
      return function (...args) {
        if (timerId) {
          clearTimeout(timerId);
        }

        timerId = setTimeout(function () {
          target(...args);
          timerId = null;
        }, delay);
      }
    }
  }

  Packlink.locationPicker = new LocationPickerConstructor();
})();