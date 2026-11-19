<?php

namespace ErnestDefoe\Calendar\Recurrence;

use Carbon\Carbon;

/**
 * Minimal but practical RFC 5545 RRULE expander. Supports the rules that cover
 * the vast majority of real calendars:
 *
 *   FREQ = DAILY | WEEKLY | MONTHLY | YEARLY
 *   INTERVAL, COUNT, UNTIL, and BYDAY (for weekly, e.g. MO,WE,FR)
 *
 * It returns the occurrence START times that fall within [$from, $to]. iCal
 * export passes the raw RRULE straight through (calendar clients expand it
 * themselves); this expander is only for rendering the in-app calendar views,
 * so it is deliberately bounded (hard caps prevent any runaway loop).
 *
 * Unsupported parts (BYMONTHDAY, BYSETPOS, etc.) are simply ignored — the series
 * still renders via its base frequency, never errors.
 */
class RecurrenceExpander
{
    private const DAY_MAP = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];
    private const MAX_ITERATIONS = 5000;

    /** @return Carbon[] occurrence start times within [$from, $to], in ascending order. */
    public static function occurrences(string $rrule, Carbon $start, Carbon $from, Carbon $to, int $cap = 400): array
    {
        $rule = self::parse($rrule);
        $freq = strtoupper($rule['FREQ'] ?? '');
        if (! in_array($freq, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
            return [];
        }

        $interval = max(1, (int) ($rule['INTERVAL'] ?? 1));
        $count = isset($rule['COUNT']) ? max(0, (int) $rule['COUNT']) : null;
        $until = isset($rule['UNTIL']) ? self::parseUntil((string) $rule['UNTIL'], $start) : null;
        $byday = isset($rule['BYDAY']) ? self::parseByday((string) $rule['BYDAY']) : [];

        if ($freq === 'WEEKLY' && $byday) {
            return self::expandWeeklyByday($start, $from, $to, $interval, $count, $until, $byday, $cap);
        }

        return self::expandSimple($freq, $start, $from, $to, $interval, $count, $until, $cap);
    }

    private static function expandSimple(string $freq, Carbon $start, Carbon $from, Carbon $to, int $interval, ?int $count, ?Carbon $until, int $cap): array
    {
        $out = [];
        // Without COUNT nothing before the window matters, so jump to it: an
        // old daily series would otherwise spend every iteration getting there.
        $occ = $count === null ? self::fastForward($freq, $start, $from, $interval) : $start->copy();
        $emitted = 0;
        $i = 0;

        while ($i++ < self::MAX_ITERATIONS) {
            if ($until && $occ->gt($until)) {
                break;
            }
            if ($count !== null && $emitted >= $count) {
                break;
            }
            if ($occ->gt($to)) {
                break;
            }

            if ($occ->gte($from)) {
                $out[] = $occ->copy();
                if (count($out) >= $cap) {
                    break;
                }
            }
            $emitted++;

            switch ($freq) {
                case 'DAILY':   $occ->addDays($interval);
                    break;
                case 'WEEKLY':  $occ->addWeeks($interval);
                    break;
                case 'MONTHLY': $occ->addMonthsNoOverflow($interval);
                    break;
                case 'YEARLY':  $occ->addYearsNoOverflow($interval);
                    break;
            }
        }

        return $out;
    }

    /** @param int[] $days Carbon dayOfWeek values (0=Sun..6=Sat) */
    private static function expandWeeklyByday(Carbon $start, Carbon $from, Carbon $to, int $interval, ?int $count, ?Carbon $until, array $days, int $cap): array
    {
        $out = [];
        $emitted = 0;
        $i = 0;
        // Anchor on the Sunday of the series-start week; step whole weeks by
        // $interval, jumping straight to the window when there's no COUNT.
        $weekStart = $start->copy()->startOfWeek(Carbon::SUNDAY);
        if ($count === null) {
            $weekStart = self::fastForward('WEEKLY', $weekStart, $from->copy()->startOfWeek(Carbon::SUNDAY), $interval);
        }

        while ($i++ < self::MAX_ITERATIONS) {
            foreach ($days as $dow) {
                $occ = $weekStart->copy()->addDays($dow)->setTime($start->hour, $start->minute, $start->second);
                if ($occ->lt($start)) {
                    continue;
                }                 // never before the series anchor
                if ($until && $occ->gt($until)) {
                    return $out;
                }
                if ($count !== null && $emitted >= $count) {
                    return $out;
                }
                $emitted++;
                if ($occ->gte($from) && $occ->lte($to)) {
                    $out[] = $occ;
                    if (count($out) >= $cap) {
                        return $out;
                    }
                }
            }
            $weekStart->addWeeks($interval);
            if ($weekStart->gt($to)) {
                break;
            }
        }

        return $out;
    }

    /** The last step of the series on or before $from (or $start itself, when $from is earlier). */
    private static function fastForward(string $freq, Carbon $start, Carbon $from, int $interval): Carbon
    {
        if ($from->lte($start)) {
            return $start->copy();
        }

        $units = (int) match ($freq) {
            'DAILY' => $start->diffInDays($from, true),
            'WEEKLY' => $start->diffInWeeks($from, true),
            'MONTHLY' => $start->diffInMonths($from, true),
            default => $start->diffInYears($from, true),
        };
        $steps = intdiv($units, $interval) * $interval;

        return match ($freq) {
            'DAILY' => $start->copy()->addDays($steps),
            'WEEKLY' => $start->copy()->addWeeks($steps),
            'MONTHLY' => $start->copy()->addMonthsNoOverflow($steps),
            default => $start->copy()->addYearsNoOverflow($steps),
        };
    }

    /**
     * When the series' last occurrence starts: the COUNT-th one, or UNTIL;
     * null when it repeats forever (or more often than we will ever expand).
     */
    public static function lastStart(string $rrule, Carbon $start): ?Carbon
    {
        $rule = self::parse($rrule);

        if (isset($rule['COUNT'])) {
            $count = max(1, (int) $rule['COUNT']);
            if ($count > self::MAX_ITERATIONS) {
                return null;
            }
            $all = self::occurrences($rrule, $start, $start, $start->copy()->addYears(200), $count);

            return $all ? end($all) : $start->copy();
        }

        return isset($rule['UNTIL']) ? self::parseUntil((string) $rule['UNTIL'], $start) : null;
    }

    /** Parse "FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE" into an upper-cased key map. */
    private static function parse(string $rrule): array
    {
        $rrule = trim(preg_replace('/^RRULE:/i', '', trim($rrule)));
        $out = [];
        foreach (explode(';', $rrule) as $part) {
            if (strpos($part, '=') === false) {
                continue;
            }
            [$k, $v] = explode('=', $part, 2);
            $out[strtoupper(trim($k))] = trim($v);
        }

        return $out;
    }

    /** @return int[] */
    private static function parseByday(string $byday): array
    {
        $days = [];
        foreach (explode(',', strtoupper($byday)) as $code) {
            $code = preg_replace('/[^A-Z]/', '', $code); // strip any ordinal prefix like "2MO"
            $two = substr($code, -2);
            if (isset(self::DAY_MAP[$two])) {
                $days[self::DAY_MAP[$two]] = self::DAY_MAP[$two];
            }
        }
        $days = array_values($days);
        sort($days);

        return $days;
    }

    /**
     * UNTIL may be a DATE (YYYYMMDD) or DATE-TIME (YYYYMMDDTHHMMSSZ); anything
     * else means open-ended. The patterns guarantee createFromFormat a parseable
     * string (an out-of-range digit rolls over rather than failing).
     */
    private static function parseUntil(string $until, Carbon $start): ?Carbon
    {
        $clean = preg_replace('/[^0-9TZ]/', '', strtoupper($until));

        if (preg_match('/^\d{8}$/', $clean)) {
            return Carbon::createFromFormat('Ymd', $clean, $start->getTimezone())->endOfDay();
        }
        if (preg_match('/^\d{8}T\d{6}Z?$/', $clean)) {
            $tz = str_ends_with($clean, 'Z') ? 'UTC' : $start->getTimezone();

            return Carbon::createFromFormat('Ymd\THis', rtrim($clean, 'Z'), $tz);
        }

        return null;
    }
}
