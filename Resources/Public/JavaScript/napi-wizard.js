/**
 * Search wizard of the NAPI item selector (runs inside a modal iframe).
 *
 * A click on a result hands the item to the edit form via the message API of
 * the FormEngine, like the element browser of the core does.
 */
import RegularEvent from '@typo3/core/event/regular-event.js';
import { MessageUtility } from '@typo3/backend/utility/message-utility.js';
import Notification from '@typo3/backend/notification.js';

class NapiWizard {
  constructor() {
    const form = document.getElementById('napi-wizard');
    this.fieldName = form?.dataset.fieldName ?? '';

    new RegularEvent('click', (event, item) => {
      event.preventDefault();
      this.addItem(item);
    }).delegateTo(document, '.t3js-napi-wizard-item');
  }

  addItem(item) {
    const target = this.findEditFormWindow();
    if (target === null) {
      Notification.error('NAPI', 'The edit form could not be found.');
      return;
    }

    MessageUtility.send({
      actionName: 'typo3:elementBrowser:elementAdded',
      fieldName: this.fieldName,
      value: item.dataset.value,
      label: item.dataset.label,
    }, target);

    item.classList.add('is-added');
  }

  /**
   * Finds the frame that contains the field (the content frame or a nested modal)
   */
  findEditFormWindow() {
    const selector = `[data-formengine-input-name="${CSS.escape(this.fieldName)}"]`;
    const candidates = [];
    const collect = (win) => {
      for (let i = 0; i < win.frames.length; i++) {
        try {
          const frame = win.frames[i];
          if (frame !== window) {
            candidates.push(frame);
            collect(frame);
          }
        } catch {
          // frame of another origin
        }
      }
    };
    collect(top);

    return candidates.find((win) => {
      try {
        return win.document.querySelector(selector) !== null;
      } catch {
        return false;
      }
    }) ?? null;
  }
}

export default new NapiWizard();
