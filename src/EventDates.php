<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use IntlDateFormatter;

/**
 * An event's date in words for its discussion thread, in the calendar system
 * and numerals the admin chose for Calendar, with the second calendar beside
 * it: "Friday, 29 Aban 1405 | November 20, 2026". Gregorian with Western
 * numerals (the default) keeps the wording the calendar always had; so does a
 * server without PHP's intl extension, which also leaves out the second
 * calendar rather than show half-converted dates.
 */
class EventDates
{
    private const CALENDARS = ['gregory', 'persian', 'hebrew', 'islamic-umalqura', 'japanese', 'buddhist'];

    private const NUMERALS = ['latn', 'arabext', 'arab', 'beng', 'deva'];

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /** "Friday, 20 November 2026" (all day) or "…, 18:00 (Europe/London)" in `$locale`. */
    public function when(Carbon $start, bool $allDay, string $tz, string $locale, string $dateLocale): string
    {
        $calendar = $this->pick('calendar_system', self::CALENDARS, 'gregory');
        $numerals = $this->pick('numerals', self::NUMERALS, 'latn');
        $intl = class_exists(IntlDateFormatter::class);
        $plain = ($calendar === 'gregory' && $numerals === 'latn') || ! $intl;

        $when = $plain
            ? $start->copy()->locale($dateLocale)->isoFormat('dddd, LL')
            : $this->format($start, $dateLocale, $calendar, $numerals, IntlDateFormatter::FULL, IntlDateFormatter::NONE);

        $second = $this->pick('second_calendar', self::CALENDARS, 'none');
        if ($intl && $second !== 'none' && $second !== $calendar) {
            // Each half in its own bidi isolate, so mixed scripts never reorder.
            $when = "\u{2068}$when\u{2069} | \u{2068}".$this->format($start, $dateLocale, $second, $numerals, IntlDateFormatter::LONG, IntlDateFormatter::NONE)."\u{2069}";
        }

        if ($allDay) {
            return $when;
        }

        $time = $plain
            ? $start->copy()->locale($locale)->isoFormat('LT')
            : $this->format($start, $locale, $calendar, $numerals, IntlDateFormatter::NONE, IntlDateFormatter::SHORT);

        return $when.', '.$time.' ('.$tz.')';
    }

    private function format(Carbon $date, string $locale, string $calendar, string $numerals, int $dateType, int $timeType): string
    {
        $formatter = new IntlDateFormatter(
            $locale.'@calendar='.$calendar.';numbers='.$numerals,
            $dateType,
            $timeType,
            $date->getTimezone(),
            $calendar === 'gregory' ? IntlDateFormatter::GREGORIAN : IntlDateFormatter::TRADITIONAL
        );

        $text = $formatter->format($date);

        // ICU refuses only on a broken locale or pattern: then Carbon's wording, never an empty date.
        return $text === false ? $date->copy()->locale(explode('@', $locale)[0])->isoFormat('LL') : $text;
    }

    /** A setting's value when it is one of `$allowed`, else `$fallback`. */
    private function pick(string $key, array $allowed, string $fallback): string
    {
        $value = (string) $this->settings->get('ernestdefoe-calendar.'.$key);

        return in_array($value, $allowed, true) || ($fallback === 'none' && $value === 'none') ? $value : $fallback;
    }
}
