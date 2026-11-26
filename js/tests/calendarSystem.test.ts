import { test } from 'node:test';
import assert from 'node:assert/strict';
import { usable, localeTag, monthStart, daysInMonth, addMonths, monthMatrix, dayParts, GREGORIAN } from '../src/common/calendarSystem.ts';

const day = (y: number, m: number, d: number) => new Date(y, m - 1, d, 12);
const iso = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

test('a supported system is kept', () => {
  assert.deepEqual(usable('fa', { calendar: 'persian', numerals: 'arabext' }), { calendar: 'persian', numerals: 'arabext' });
  assert.equal(localeTag('fa_IR', { calendar: 'persian', numerals: 'arabext' }), 'fa-IR-u-ca-persian-nu-arabext');
});

test('an engine without the calendar or numerals falls back to Gregorian and Latin, never half-converted', () => {
  // An engine that ignores the requested calendar and numbering system, as one without them does.
  const engine = (locale: string) =>
    ({ resolvedOptions: () => ({ locale, calendar: 'gregory', numberingSystem: 'latn' }) }) as unknown as Intl.DateTimeFormat;

  assert.deepEqual(usable('fa', { calendar: 'persian', numerals: 'arabext' }, engine), GREGORIAN);
});

test('an unknown setting is Gregorian and Latin', () => {
  assert.deepEqual(usable('en', { calendar: 'martian' as any, numerals: 'roman' as any }), GREGORIAN);
});

test('Persian months: 20 November 2026 is 29 Aban 1405, and Aban has 30 days', () => {
  const nov20 = day(2026, 11, 20);
  assert.equal(dayParts(nov20, 'persian').day, 29);
  assert.equal(iso(monthStart(nov20, 'persian')), '2026-10-23'); // 1 Aban 1405
  assert.equal(daysInMonth(nov20, 'persian'), 30);
  assert.equal(iso(addMonths(nov20, 1, 'persian')), '2026-11-22'); // 1 Azar
  assert.equal(iso(addMonths(nov20, -1, 'persian')), '2026-09-23'); // 1 Mehr
});

test('Hijri and Hebrew months start where Intl says', () => {
  for (const cal of ['islamic-umalqura', 'hebrew', 'buddhist', 'japanese', 'gregory'] as const) {
    const first = monthStart(day(2026, 11, 20), cal);
    assert.equal(dayParts(first, cal).day, 1, cal);
    assert.equal(dayParts(new Date(first.getTime() - 12 * 3600 * 1000), cal).day >= 29, true, cal + ' previous day ends a month');
  }
});

test('a month grid is 42 days from the configured week start, holding the whole month', () => {
  const grid = monthMatrix(day(2026, 11, 20), 'persian', 6);
  assert.equal(grid.length, 42);
  assert.equal(grid[0].getDay(), 6);
  assert.ok(grid.some((d) => iso(d) === '2026-10-23') && grid.some((d) => iso(d) === '2026-11-21'));
});

test('twelve months forward from a Gregorian 31st lands on the 1st of each month', () => {
  let d = day(2026, 1, 31);
  for (let i = 0; i < 12; i++) {
    d = addMonths(d, 1, 'gregory');
    assert.equal(d.getDate(), 1);
  }
  assert.equal(iso(d), '2027-01-01');
});
