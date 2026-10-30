/**
 * Backend module "NAPI-Status": live check of the API
 */
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import RegularEvent from '@typo3/core/event/regular-event.js';
import Notification from '@typo3/backend/notification.js';

const PHASES = [
  ['dns', 'DNS'],
  ['connect', 'Verbindung'],
  ['tls', 'TLS'],
  ['wait', 'Server'],
  ['transfer', 'Übertragung'],
];

const STATUS_LABELS = {
  ok: 'In Ordnung',
  slow: 'Langsam',
  auth: 'Zugang ungültig',
  down: 'Nicht erreichbar',
  config: 'Konfiguration unvollständig',
};

const el = (tag, attributes = {}, ...children) => {
  const element = document.createElement(tag);
  Object.entries(attributes).forEach(([name, value]) => {
    if (name === 'class') {
      element.className = value;
    } else if (name === 'style') {
      element.style.cssText = value;
    } else {
      element.setAttribute(name, value);
    }
  });
  element.append(...children.filter((child) => child !== null && child !== undefined).map((child) => (child instanceof Node ? child : String(child))));
  return element;
};

const ms = (value) => (value === null || value === undefined ? '–' : `${Math.round(value)} ms`);

class NapiStatus {
  constructor() {
    new RegularEvent('click', (event, button) => {
      event.preventDefault();
      this.check(button);
    }).delegateTo(document, '.t3js-napi-check');

    const button = document.querySelector('.t3js-napi-check');
    if (button) {
      this.check(button);
    }
  }

  async check(button) {
    const target = document.querySelector('.t3js-napi-check-result');
    button.disabled = true;
    target.replaceChildren(el('p', {}, 'Prüfe NAPI …'));

    try {
      const response = await new AjaxRequest(button.dataset.url).post({});
      this.render(target, await response.resolve());
    } catch (error) {
      target.replaceChildren();
      Notification.error('NAPI-Status', 'Der Check konnte nicht ausgeführt werden.');
    } finally {
      button.disabled = false;
    }
  }

  render(target, report) {
    const checkedAt = new Date(report.checkedAt * 1000).toLocaleTimeString();
    const overall = el('div', { class: 'napi-status-overall' },
      el('span', { class: 'napi-status-dot', 'data-state': report.state }),
      el('strong', {}, STATUS_LABELS[report.status] ?? report.status),
      el('span', {}, report.results.length ? `⌀ ${ms(report.averageMs)}, max. ${ms(report.maxMs)} · ${checkedAt}` : checkedAt),
    );

    if (report.missingSettings.length) {
      target.replaceChildren(overall, el('p', {}, `Fehlende Einstellungen: ${report.missingSettings.join(', ')}`));
      return;
    }

    const maxTotal = Math.max(...report.results.map((result) => result.totalMs), 1);
    const rows = report.results.map((result) => {
      const bar = el('div', { class: 'napi-status-bar', title: PHASES.map(([key, label]) => `${label}: ${ms(result.phases[key])}`).join(', ') },
        ...PHASES.map(([key]) => el('span', { class: `phase-${key}`, style: `width: ${(result.phases[key] / maxTotal) * 100}%` })));

      return el('tr', {},
        el('td', {}, el('code', {}, result.endpoint)),
        el('td', {}, el('span', { class: `badge badge-${result.state}` }, STATUS_LABELS[result.status] ?? result.status)),
        el('td', {}, result.httpStatus || '–'),
        el('td', {}, ms(result.totalMs)),
        el('td', {}, ms(result.serverMs)),
        el('td', {}, bar),
        el('td', {}, result.recordCount ?? '–'),
        el('td', {}, result.error || ''),
      );
    });

    const table = el('table', { class: 'table table-striped' },
      el('thead', {}, el('tr', {}, ...['Endpunkt', 'Status', 'HTTP', 'Gesamt', 'NAPI-Server', 'Zeitanteile', 'Datensätze', 'Hinweis'].map((label) => el('th', {}, label)))),
      el('tbody', {}, ...rows),
    );

    const legend = el('div', { class: 'napi-status-legend' },
      ...PHASES.map(([key, label]) => el('span', {}, el('i', { class: `phase-${key}` }), label)));

    const ip = report.results.find((result) => result.ip)?.ip;
    target.replaceChildren(overall, table, legend, el('p', { class: 'napi-status-hint' }, `${report.baseUri}${ip ? ` (${ip})` : ''}`));
  }
}

export default new NapiStatus();
