/**
 * "Load more" pagination of the NAPI list views (no dependencies)
 */
(function () {
  'use strict';

  document.addEventListener('click', function (event) {
    var button = event.target.closest('.ajax-list-wrapper .ajax-list__next-page');
    if (!button) {
      return;
    }
    event.preventDefault();

    var spinner = button.querySelector('.load-more');
    var requestUri = button.dataset.requestUri || '';
    if (requestUri === '' || (spinner && spinner.classList.contains('load-more--loading'))) {
      return;
    }

    spinner && spinner.classList.add('load-more--loading');

    fetch(requestUri, { credentials: 'same-origin' })
      .then(function (response) {
        return response.ok ? response.text() : '';
      })
      .then(function (html) {
        var result = new DOMParser().parseFromString(html, 'text/html');
        var elements = result.querySelectorAll('.ajax-list > *');
        var list = document.querySelector('.ajax-list');

        if (elements.length === 0 || !list) {
          button.hidden = true;
          return;
        }

        var nextPage = result.querySelector('.ajax-list__next-page');
        if (nextPage && nextPage.dataset.requestUri) {
          button.dataset.requestUri = nextPage.dataset.requestUri;
        } else {
          button.hidden = true;
        }

        elements.forEach(function (element) {
          list.appendChild(document.importNode(element, true));
        });

        if (window.nordkirche && window.nordkirche.lazyload) {
          window.nordkirche.lazyload.update();
        }
      })
      .catch(function () {
        button.hidden = true;
      })
      .finally(function () {
        spinner && spinner.classList.remove('load-more--loading');
      });
  });
})();
