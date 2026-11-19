<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Api;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use ErnestDefoe\Calendar\Event;
use Flarum\Foundation\ValidationException;
use Illuminate\Support\Str;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Cleans what a member types into the event form. EventResource's field
 * setters call the static cleaners one field at a time; validate() then checks
 * the rules that span fields before the event is saved.
 */
class EventInput
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    /** Reject an event without a title or a start, or one that ends before it starts. */
    public function validate(Event $event): void
    {
        $errors = [];

        if (trim((string) $event->title) === '') {
            $errors['title'] = $this->translator->trans('ernestdefoe-calendar.api.title_required');
        }
        if (! $event->start_at) {
            $errors['start'] = $this->translator->trans('ernestdefoe-calendar.api.start_required');
        } elseif ($event->end_at && $event->end_at->lt($event->start_at)) {
            $errors['end'] = $this->translator->trans('ernestdefoe-calendar.api.end_before_start');
        }

        if ($errors) {
            throw new ValidationException($errors);
        }
    }

    public static function title(mixed $value): string
    {
        return mb_substr(trim((string) $value), 0, 255);
    }

    /** A date the member typed, in UTC; null when it isn't one (validate() reports it). */
    public static function date(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->utc();
        } catch (InvalidFormatException) {
            return null;
        }
    }

    public static function timezone(mixed $tz): string
    {
        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC';
    }

    public static function str(mixed $v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    public static function url(mixed $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        // Root-relative paths (e.g. FoF Upload's local file URLs) are allowed, but
        // never protocol-relative "//host" which could point off-site.
        if (str_starts_with($v, '/') && ! str_starts_with($v, '//')) {
            return mb_substr($v, 0, 600);
        }
        // Absolute URLs must be valid AND http/https. filter_var(FILTER_VALIDATE_URL)
        // alone accepts javascript:/data:/vbscript: schemes, which become stored XSS
        // when rendered as an <a href>. Allowlist the scheme.
        if (filter_var($v, FILTER_VALIDATE_URL)) {
            $scheme = strtolower((string) parse_url($v, PHP_URL_SCHEME));
            if (in_array($scheme, ['http', 'https'], true)) {
                return mb_substr($v, 0, 600);
            }
        }

        return null;
    }

    /** Keep only a safe RRULE-ish charset; the expander/iCal tolerate the rest. */
    public static function rrule(mixed $v): ?string
    {
        $v = strtoupper(trim((string) $v));
        if ($v === '') {
            return null;
        }
        $v = preg_replace('/[^A-Z0-9;:=,\-]/', '', $v);

        return mb_substr($v, 0, 600);
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $i = 2;
        while (Event::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
