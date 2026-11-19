import app from 'flarum/forum/app';
import { num } from '../common/dates';

/**
 * Live countdowns. The formatter renders `[countdown=…]` as an inert
 * `<span class="CalCountdown" data-deadline="…">label</span>` placeholder (server
 * output is cached, so the clock can't run there). This also covers countdowns
 * rendered anywhere else (e.g. the event detail modal), with no per-component
 * hooks.
 *
 * The 1s ticker only runs while a countdown on the page still needs ticking
 * (not yet built, or live). Once every countdown is done, invalid or gone, the
 * ticker stops and a MutationObserver waits for the next placeholder (a new
 * post, page or modal), which starts it again.
 */

/** Countdowns that still need a tick: everything but finished and invalid ones. */
const PENDING = '.CalCountdown[data-deadline]:not([data-state="done"]):not([data-state="invalid"])';

let started = false;
let timer: number | null = null;
let observer: MutationObserver | null = null;

/** Plain-string translation (trans() can return an array; we build innerHTML). */
function tr(key: string): string {
  const r = app.translator.trans('ernestdefoe-calendar.forum.' + key) as any;
  return Array.isArray(r) ? r.join('') : String(r);
}

/** Render every pending countdown; stop ticking and go back to watching when none remain. */
function tick(): void {
  const nodes = document.querySelectorAll<HTMLElement>(PENDING);
  for (let i = 0; i < nodes.length; i++) render(nodes[i]);

  if (!document.querySelector(PENDING)) idle();
}

function run(): void {
  observer?.disconnect();
  tick();
  if (timer === null && document.querySelector(PENDING)) timer = window.setInterval(tick, 1000);
}

function idle(): void {
  if (timer !== null) {
    window.clearInterval(timer);
    timer = null;
  }
  if (!observer) {
    observer = new MutationObserver(() => {
      if (document.querySelector(PENDING)) run();
    });
  }
  observer.observe(document.body, { childList: true, subtree: true });
}

export function startCountdowns(): void {
  if (started || typeof document === 'undefined') return;
  started = true;
  run();
}

function parseDeadline(raw: string): number | null {
  let ts = Date.parse(raw);
  if (isNaN(ts)) ts = Date.parse(raw.trim().replace(' ', 'T')); // tolerate "Y-M-D H:M"
  return isNaN(ts) ? null : ts;
}

function render(el: HTMLElement): void {
  const deadline = parseDeadline(el.getAttribute('data-deadline') || '');
  if (deadline === null) {
    el.setAttribute('data-state', 'invalid');
    return;
  }

  if (el.getAttribute('data-init') !== '1') build(el);

  const diff = deadline - Date.now();
  if (diff <= 0) {
    setDone(el);
    return;
  }

  el.setAttribute('data-state', 'live');
  const secs = Math.floor(diff / 1000);
  setNum(el, 'd', Math.floor(secs / 86400));
  setNum(el, 'h', Math.floor((secs % 86400) / 3600));
  setNum(el, 'm', Math.floor((secs % 3600) / 60));
  setNum(el, 's', secs % 60);
}

function build(el: HTMLElement): void {
  const label = (el.textContent || '').trim();
  el.setAttribute('data-init', '1');
  if (label) el.setAttribute('data-label', label);

  const unit = (u: string, lbl: string) =>
    `<span class="CalCountdown-unit"><b class="CalCountdown-num" data-u="${u}">${num(0)}</b><i class="CalCountdown-lbl">${escapeHtml(lbl)}</i></span>`;

  el.innerHTML =
    (label ? `<span class="CalCountdown-caption">${escapeHtml(label)}</span>` : '') +
    '<span class="CalCountdown-clock">' +
    unit('d', tr('cd_days')) +
    unit('h', tr('cd_hours')) +
    unit('m', tr('cd_mins')) +
    unit('s', tr('cd_secs')) +
    '</span>';
}

function setNum(el: HTMLElement, u: string, v: number): void {
  const n = el.querySelector('.CalCountdown-num[data-u="' + u + '"]');
  if (n) n.textContent = num(v, u === 'd' ? 1 : 2);
}

function setDone(el: HTMLElement): void {
  if (el.getAttribute('data-state') === 'done') return;
  el.setAttribute('data-state', 'done');
  const label = el.getAttribute('data-label') || '';
  const msg = label ? escapeHtml(label) + ' · ' + tr('cd_here') : tr('cd_live');
  el.innerHTML = '<span class="CalCountdown-done">🎉 ' + msg + '</span>';
}

function escapeHtml(s: string): string {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}
