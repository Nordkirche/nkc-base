/**
 * NAPI item selector (FormEngine element "napiItemSelector"):
 * suggest search and search wizard.
 *
 * Selected items are added with the FormEngine API of the core, which also
 * updates the hidden value field and runs the validation.
 */
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import DebounceEvent from '@typo3/core/event/debounce-event.js';
import RegularEvent from '@typo3/core/event/regular-event.js';
import FormEngine from '@typo3/backend/form-engine.js';
import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';

const MIN_CHARACTERS = 3;

class NapiSelector {
  constructor() {
    this.request = null;

    new DebounceEvent('input', (event, field) => this.suggest(field), 250)
      .delegateTo(document, '.t3js-napi-suggest');

    new RegularEvent('keydown', (event, field) => {
      if (event.key === 'Escape') {
        this.hideResults(field);
      }
    }).delegateTo(document, '.t3js-napi-suggest');

    new RegularEvent('click', (event, item) => {
      event.preventDefault();
      this.addItem(item);
    }).delegateTo(document, '.t3js-napi-suggest-item');

    new RegularEvent('click', (event, button) => {
      event.preventDefault();
      this.openWizard(button);
    }).delegateTo(document, '.t3js-napi-wizard');

    new RegularEvent('click', (event) => {
      document.querySelectorAll('.t3js-napi-suggest-results.is-open').forEach((results) => {
        if (!results.parentElement.contains(event.target)) {
          results.classList.remove('is-open');
        }
      });
    }).bindTo(document);
  }

  async suggest(field) {
    const search = field.value.trim();
    this.request?.abort();

    if (search.length < MIN_CHARACTERS) {
      this.hideResults(field);
      return;
    }

    this.request = new AjaxRequest(TYPO3.settings.ajaxUrls['NkcBase::suggest'])
      .withQueryArguments({ search, allowed: field.dataset.allowed });

    try {
      const response = await this.request.get();
      const data = await response.resolve();
      this.renderResults(field, data.items || []);
    } catch (error) {
      if (error?.name === 'AbortError') {
        return;
      }
      let message = '';
      try {
        message = (await error.response?.json())?.error ?? '';
      } catch {
        // no JSON body
      }
      Notification.error('NAPI', message || 'Suggest request failed');
    }
  }

  renderResults(field, items) {
    const results = this.getResultsElement(field);
    results.replaceChildren();

    items.forEach((item) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'napi-suggest-item t3js-napi-suggest-item';
      button.setAttribute('role', 'option');
      button.dataset.value = item.uri;
      button.dataset.label = item.name;
      button.dataset.fieldName = field.dataset.fieldName;

      const label = document.createElement('span');
      label.textContent = item.name;
      const id = document.createElement('small');
      id.textContent = `[${item.id}]`;
      button.append(label, id);
      results.append(button);
    });

    results.classList.toggle('is-open', items.length > 0);
  }

  addItem(item) {
    const { fieldName, value, label } = item.dataset;
    FormEngine.setSelectOptionFromExternalSource(fieldName, value, label, label);

    const container = item.closest('.napi-suggest');
    const field = container?.querySelector('.t3js-napi-suggest');
    if (field) {
      field.value = '';
      this.hideResults(field);
      field.focus();
    }
  }

  openWizard(button) {
    Modal.advanced({
      type: Modal.types.iframe,
      title: button.title,
      content: button.dataset.url,
      size: Modal.sizes.large,
    });
  }

  hideResults(field) {
    this.getResultsElement(field)?.classList.remove('is-open');
  }

  getResultsElement(field) {
    return field.closest('.napi-suggest')?.querySelector('.t3js-napi-suggest-results');
  }
}

export default new NapiSelector();
