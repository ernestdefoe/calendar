import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import { dayParts } from '../../common/calendarSystem';
import {
  systems,
  startOfDay,
  monthMatrix,
  monthOf,
  shiftMonth,
  sameMonth,
  sameDay,
  isToday,
  addDays,
  dayNum,
  weekdayNames,
  loc,
  dateLoc,
  toLocalInput,
} from '../../common/dates';

declare const m: any;

const t = (key: string) => app.translator.trans('ernestdefoe-calendar.forum.' + key) as string;

interface CalendarDateInputAttrs {
  /** Local wall time, "YYYY-MM-DDTHH:mm" (all day: the date part is used). */
  value: string;
  allDay: boolean;
  weekStart: number;
  /** Receives the new local value in the same format; '' when cleared. */
  onchange: (value: string) => void;
  /** The field's label, for the dialog's name. */
  label: string;
  /** May be left empty (the end date). */
  optional?: boolean;
}

const pad = (n: number) => String(n).padStart(2, '0');
const datePart = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

/** "YYYY-MM-DD…" → local midnight of that day, or null. */
function parseDay(value: string): Date | null {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || '');

  return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null;
}

/**
 * A date (and time) field in the admin's calendar system. A browser's own date
 * input only knows the Gregorian calendar, so for any other system this shows
 * the date in that calendar and opens a month grid of it to pick from. Arrow
 * keys move by day and week, Page Up / Page Down by month of that calendar,
 * Home / End to the week's ends, Enter picks and Escape closes; every day is
 * announced in the same calendar. The value stays a Gregorian local date, so
 * nothing downstream changes. Gregorian forums keep the native input.
 */
export default class CalendarDateInput extends Component<CalendarDateInputAttrs> {
  open = false;
  focused: Date = startOfDay(new Date());
  dayName: Intl.DateTimeFormat | null = null;
  outside = (e: MouseEvent) => {
    if (this.open && !this.element.contains(e.target as Node)) {
      this.open = false;
      m.redraw();
    }
  };

  oncreate(vnode: any) {
    super.oncreate(vnode);
    document.addEventListener('mousedown', this.outside);
  }

  onremove(vnode: any) {
    super.onremove(vnode);
    document.removeEventListener('mousedown', this.outside);
  }

  view() {
    const a = this.attrs;

    if (systems().main.calendar === 'gregory') return this.nativeInput();

    const day = parseDay(a.value);
    const label = day ? day.toLocaleDateString(dateLoc(), { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' }) : t('picker_empty');

    return m('.CalendarDateInput', [
      m(
        'button.FormControl.CalendarDateInput-button',
        {
          type: 'button',
          'aria-haspopup': 'dialog',
          'aria-expanded': this.open ? 'true' : 'false',
          'aria-label': a.label + ': ' + label,
          onclick: () => this.toggle(day),
        },
        label
      ),
      a.allDay
        ? null
        : m('input.FormControl.CalendarDateInput-time', {
            type: 'time',
            'aria-label': a.label + ': ' + t('picker_time'),
            value: (a.value || '').slice(11, 16),
            disabled: !day,
            oninput: (e: any) => day && a.onchange(datePart(day) + 'T' + (e.target.value || '00:00')),
          }),
      this.open ? this.dialog(day) : null,
    ]);
  }

  nativeInput() {
    const a = this.attrs;

    return m('input.FormControl', {
      type: a.allDay ? 'date' : 'datetime-local',
      value: a.allDay ? (a.value || '').slice(0, 10) : a.value,
      oninput: (e: any) => a.onchange(a.allDay ? (e.target.value ? e.target.value + 'T00:00' : '') : e.target.value),
    });
  }

  toggle(day: Date | null) {
    this.open = !this.open;
    this.focused = startOfDay(day || new Date());
  }

  dialog(day: Date | null) {
    const a = this.attrs;
    const cells = monthMatrix(this.focused, a.weekStart);
    const month = monthOf(this.focused);
    const rows = Array.from({ length: 6 }, (_, r) => cells.slice(r * 7, r * 7 + 7));

    // One formatter per redraw for the 42 day names, not one per day.
    this.dayName = new Intl.DateTimeFormat(dateLoc(), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const narrow = weekdayNames(a.weekStart, 'narrow');
    const full = weekdayNames(a.weekStart);

    return m('.CalendarDateInput-dialog', { role: 'dialog', 'aria-label': a.label, onkeydown: (e: KeyboardEvent) => this.escape(e) }, [
      m('.CalendarDateInput-head', [
        m(
          'button.Button.Button--icon.Button--link',
          { type: 'button', 'aria-label': t('picker_previous'), onclick: () => this.moveMonth(-1) },
          m('i.fas.fa-chevron-left')
        ),
        m('span.CalendarDateInput-month', { 'aria-live': 'polite' }, month.toLocaleDateString(loc(), { month: 'long', year: 'numeric' })),
        m(
          'button.Button.Button--icon.Button--link',
          { type: 'button', 'aria-label': t('picker_next'), onclick: () => this.moveMonth(1) },
          m('i.fas.fa-chevron-right')
        ),
      ]),
      m('table.CalendarDateInput-grid', { role: 'grid', onkeydown: (e: KeyboardEvent) => this.key(e) }, [
        // Narrow names ("S M T"): Persian's short ones are whole words and overlap in 300px.
        m(
          'thead',
          m(
            'tr',
            weekdayNames(a.weekStart, 'narrow').map((w, i) => m('th', { scope: 'col', abbr: weekdayNames(a.weekStart)[i] }, w))
          )
        ),
        m(
          'tbody',
          rows.map((row) =>
            m(
              'tr',
              row.map((d) => this.cell(d, day, month))
            )
          )
        ),
      ]),
      a.optional && day
        ? m('button.Button.Button--link.CalendarDateInput-clear', { type: 'button', onclick: () => this.pick(null) }, t('picker_clear'))
        : null,
    ]);
  }

  cell(d: Date, day: Date | null, month: Date) {
    const focused = sameDay(d, this.focused);

    return m(
      'td',
      { role: 'gridcell', 'aria-selected': day && sameDay(d, day) ? 'true' : 'false' },
      m(
        'button.CalendarDateInput-day' +
          (sameMonth(d, month) ? '' : '.is-out') +
          (isToday(d) ? '.is-today' : '') +
          (day && sameDay(d, day) ? '.is-selected' : ''),
        {
          type: 'button',
          tabindex: focused ? 0 : -1,
          'data-focused': focused ? 'true' : undefined,
          'aria-label': this.dayName!.format(d),
          onclick: () => this.pick(d),
        },
        dayNum(d)
      )
    );
  }

  onupdate() {
    // Keyboard focus follows the focused day after each move.
    const el = this.element?.querySelector?.('.CalendarDateInput-day[data-focused]') as HTMLElement | null;
    if (el && this.element.contains(document.activeElement) && document.activeElement !== el) el.focus();
  }

  key(e: KeyboardEvent) {
    const rtl = getComputedStyle(this.element as Element).direction === 'rtl';
    const moves: Record<string, () => Date> = {
      ArrowLeft: () => addDays(this.focused, rtl ? 1 : -1),
      ArrowRight: () => addDays(this.focused, rtl ? -1 : 1),
      ArrowUp: () => addDays(this.focused, -7),
      ArrowDown: () => addDays(this.focused, 7),
      PageUp: () => this.sameDayIn(-1),
      PageDown: () => this.sameDayIn(1),
      Home: () => addDays(this.focused, -((this.focused.getDay() - this.attrs.weekStart + 7) % 7)),
      End: () => addDays(this.focused, 6 - ((this.focused.getDay() - this.attrs.weekStart + 7) % 7)),
    };

    if (moves[e.key]) {
      e.preventDefault();
      this.focused = moves[e.key]();
    } else if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      this.pick(this.focused);
    }
  }

  /** Escape closes the picker from anywhere in it, back on its button. */
  escape(e: KeyboardEvent) {
    if (e.key !== 'Escape') return;

    e.preventDefault();
    e.stopPropagation(); // the modal underneath stays open
    this.open = false;
    (this.element.querySelector('.CalendarDateInput-button') as HTMLElement | null)?.focus();
  }

  /** The same day number `delta` months away in the chosen calendar, or that month's last day. */
  sameDayIn(delta: number): Date {
    const offset = dayParts(this.focused, systems().main.calendar).day - 1;
    const target = shiftMonth(this.focused, delta);
    const candidate = addDays(target, offset);

    return sameMonth(candidate, target) ? candidate : addDays(shiftMonth(target, 1), -1);
  }

  moveMonth(delta: number) {
    this.focused = this.sameDayIn(delta);
  }

  pick(d: Date | null) {
    const a = this.attrs;
    this.open = false;

    if (!d) return a.onchange('');

    const time = a.allDay ? '00:00' : (a.value || '').slice(11, 16) || toLocalInput(new Date()).slice(11, 16);
    a.onchange(datePart(d) + 'T' + time);
  }
}
