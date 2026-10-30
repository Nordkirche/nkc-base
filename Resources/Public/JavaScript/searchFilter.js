/**
 * Predefined values of the NAPI search forms (cities, locations, categories)
 *
 *   <ul data-search-field="city" data-search-reset="location">
 *     <li data-value="">Orte</li>                <- resets the field
 *     <li data-value="Kiel"><a href="#">Kiel</a> <- sets the field and searches
 *       <ul data-search-field="location" data-search-parent-field="city">
 *         <li data-value="Dom">Dom</li>          <- sets location and city ("Kiel")
 *
 * The field is the input "...[searchRequest][<field>]" of the same form.
 * Theme scripts can take over by calling event.preventDefault() first.
 */
(function () {
  'use strict';

  var findField = function (form, name) {
    return name ? form.querySelector('[name$="[searchRequest][' + name + ']"]') : null;
  };

  var setField = function (form, name, value) {
    var field = findField(form, name);
    if (field) {
      field.value = value;
    }
  };

  document.addEventListener('click', function (event) {
    if (event.defaultPrevented || !event.target.closest) {
      return;
    }
    var item = event.target.closest('li[data-value]');
    var list = item && item.parentElement;
    var form = item && item.closest('form');
    if (!form || !list || !list.matches('ul[data-search-field]') || !findField(form, list.dataset.searchField)) {
      return;
    }

    event.preventDefault();
    setField(form, list.dataset.searchField, item.dataset.value);

    (list.dataset.searchReset || '').split(',').forEach(function (name) {
      setField(form, name.trim(), '');
    });

    if (list.dataset.searchParentField) {
      var parentItem = list.closest('li[data-value]');
      setField(form, list.dataset.searchParentField, parentItem ? parentItem.dataset.value : '');
    }

    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit();
    } else {
      form.submit();
    }
  });
})();
