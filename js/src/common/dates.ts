import type { CalEvent } from './api';

/** A 6×7 grid of Dates covering the month, padded to whole weeks. */
export function monthMatrix(year: number, month: number, weekStartsOn = 0): Date[] {
  const first = new Date(year, month, 1);
  const start = new Date(first);
  const shift = (first.getDay() - weekStartsOn + 7) % 7;
  start.setDate(first.getDate() - shift);

  const days: Date[] = [];
  for (let i = 0; i < 42; i++) {
    const d = new Date(start);
    d.setDate(start.getDate() + i);
    days.push(d);
  }
  return days;
}

export function sameDay(a: Date, b: Date): boolean {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

export function isToday(d: Date): boolean {
  return sameDay(d, new Date());
}

/** Localised weekday short names, ordered from the configured first day. */
export function weekdayNames(weekStartsOn = 0): string[] {
  const base = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  return [...base.slice(weekStartsOn), ...base.slice(0, weekStartsOn)];
}

export function monthLabel(year: number, month: number): string {
  return new Date(year, month, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
}

/** Does an event (with absolute start/end) touch the given calendar day? */
export function eventOnDay(ev: CalEvent, day: Date): boolean {
  const s = new Date(ev.start);
  const e = ev.end ? new Date(ev.end) : s;
  const dayStart = new Date(day.getFullYear(), day.getMonth(), day.getDate());
  const dayEnd = new Date(dayStart);
  dayEnd.setDate(dayEnd.getDate() + 1);
  return s < dayEnd && e >= dayStart;
}

/** Human-friendly date/time range for an event. */
export function formatRange(ev: CalEvent): string {
  const s = new Date(ev.start);
  const e = ev.end ? new Date(ev.end) : null;
  const dOpts: Intl.DateTimeFormatOptions = { weekday: 'short', month: 'short', day: 'numeric' };
  const tOpts: Intl.DateTimeFormatOptions = { hour: 'numeric', minute: '2-digit' };

  if (ev.allDay) {
    if (e && !sameDay(s, e)) return `${s.toLocaleDateString(undefined, dOpts)} – ${e.toLocaleDateString(undefined, dOpts)}`;
    return s.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
  }

  const date = s.toLocaleDateString(undefined, dOpts);
  const startT = s.toLocaleTimeString(undefined, tOpts);
  if (!e) return `${date}, ${startT}`;
  if (sameDay(s, e)) return `${date}, ${startT} – ${e.toLocaleTimeString(undefined, tOpts)}`;
  return `${date}, ${startT} – ${e.toLocaleDateString(undefined, dOpts)}, ${e.toLocaleTimeString(undefined, tOpts)}`;
}

export function shortTime(iso: string): string {
  return new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
}

/** Format a Date for a datetime-local input value (local wall time). */
export function toLocalInput(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
