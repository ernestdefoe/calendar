/**
 * Calendar systems and numerals for Calendar's own dates, on top of Intl.
 *
 * Dates are stored and handled as ordinary (Gregorian, UTC) Date objects; only
 * how they are named and how they group into months changes. Intl names them
 * in any calendar (`fa-u-ca-persian-nu-arabext`), but it has no "first day of
 * this Hijri month", so months are found by reading a day's numbers back with
 * formatToParts and walking day by day: at most 31 steps, from one cached
 * formatter per calendar.
 *
 * Pure module, no Flarum imports: dates.ts feeds it the forum's settings, and
 * the unit tests (js/tests) run it directly.
 */

export const CALENDARS = ['gregory', 'persian', 'hebrew', 'islamic-umalqura', 'japanese', 'buddhist'] as const;
export const NUMERALS = ['latn', 'arabext', 'arab', 'beng', 'deva'] as const;

export type CalendarId = (typeof CALENDARS)[number];
export type NumeralsId = (typeof NUMERALS)[number];

export interface CalendarSystem {
  calendar: CalendarId;
  numerals: NumeralsId;
}

export const GREGORIAN: CalendarSystem = { calendar: 'gregory', numerals: 'latn' };

type MakeFormat = (locale: string, options?: Intl.DateTimeFormatOptions) => Intl.DateTimeFormat;

const makeFormat: MakeFormat = (locale, options) => new Intl.DateTimeFormat(locale, options);

/** The language part of a locale, without any `-u-` extension: "fa-IR-u-ca-gregory" → "fa-IR". */
export function baseLocale(locale: string | undefined): string {
  return (locale || 'en').replace(/_/g, '-').split('-u-')[0];
}

/** A locale tag that names dates in `system`: "fa" → "fa-u-ca-persian-nu-arabext". */
export function localeTag(locale: string | undefined, system: CalendarSystem): string {
  return `${baseLocale(locale)}-u-ca-${system.calendar}-nu-${system.numerals}`;
}

/**
 * The system as this browser can show it. An engine without the calendar (or
 * the numerals) silently formats in its default instead, which would mix two
 * calendars on one page; so each part the engine doesn't confirm falls back to
 * Gregorian / Latin, quietly, and every date stays in one consistent system.
 */
export function usable(locale: string | undefined, wanted: Partial<CalendarSystem>, make: MakeFormat = makeFormat): CalendarSystem {
  const calendar = (CALENDARS as readonly string[]).includes(wanted.calendar as string) ? (wanted.calendar as CalendarId) : 'gregory';
  const numerals = (NUMERALS as readonly string[]).includes(wanted.numerals as string) ? (wanted.numerals as NumeralsId) : 'latn';

  try {
    const resolved = make(localeTag(locale, { calendar, numerals })).resolvedOptions();

    return {
      calendar: resolved.calendar === calendar ? calendar : 'gregory',
      numerals: resolved.numberingSystem === numerals ? numerals : 'latn',
    };
  } catch (e) {
    return GREGORIAN;
  }
}

const PARTS: Record<string, Intl.DateTimeFormat> = {};

/** A day's year, month and day numbers in `calendar` (Latin digits, for arithmetic). */
export function dayParts(date: Date, calendar: CalendarId): { year: string; month: string; day: number } {
  const fmt =
    PARTS[calendar] ||
    (PARTS[calendar] = makeFormat(`en-u-ca-${calendar}-nu-latn`, { year: 'numeric', month: 'numeric', day: 'numeric', era: 'short' }));
  const parts = fmt.formatToParts(date);
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? '';

  // The era keeps two Japanese eras (or BC/AD) with the same year number apart.
  return { year: get('era') + get('year') + get('relatedYear'), month: get('month'), day: parseInt(get('day'), 10) };
}

function shift(date: Date, days: number): Date {
  const x = new Date(date);
  x.setDate(x.getDate() + days);
  return x;
}

/** Midnight of the first day of the `calendar` month that holds `date`. */
export function monthStart(date: Date, calendar: CalendarId): Date {
  const x = new Date(date);
  x.setHours(12, 0, 0, 0); // noon: a daylight-saving hour never moves it across a day
  const back = dayParts(x, calendar).day - 1;
  const first = shift(x, -back);
  first.setHours(0, 0, 0, 0);

  return first;
}

/** Whether two days fall in the same `calendar` month. */
export function sameMonth(a: Date, b: Date, calendar: CalendarId): boolean {
  const pa = dayParts(a, calendar);
  const pb = dayParts(b, calendar);

  return pa.year === pb.year && pa.month === pb.month;
}

/** How many days the `calendar` month holding `date` has. */
export function daysInMonth(date: Date, calendar: CalendarId): number {
  const first = monthStart(date, calendar);
  let n = 28;
  while (n < 31 && sameMonth(shift(first, n), first, calendar)) n++;

  return n;
}

/** Midnight of the first day of the month `delta` months from the one holding `date`. */
export function addMonths(date: Date, delta: number, calendar: CalendarId): Date {
  let first = monthStart(date, calendar);
  for (let i = 0; i < Math.abs(delta); i++) {
    first = delta > 0 ? shift(first, daysInMonth(first, calendar)) : monthStart(shift(first, -1), calendar);
  }

  return first;
}

/** The 42 days (6 weeks) of a month grid for the `calendar` month holding `date`. */
export function monthMatrix(date: Date, calendar: CalendarId, weekStartsOn = 0): Date[] {
  const first = monthStart(date, calendar);
  const start = shift(first, -((first.getDay() - weekStartsOn + 7) % 7));

  return Array.from({ length: 42 }, (_, i) => shift(start, i));
}
