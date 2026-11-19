<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Api;

use Carbon\Carbon;
use ErnestDefoe\Calendar\Event;
use ErnestDefoe\Calendar\LocationSetting;

/** "Add to Google Calendar" template links: no API or OAuth, so they work for everyone. */
class EventLinks
{
    public function __construct(private LocationSetting $location)
    {
    }

    public function google(Event $event, ?Carbon $start, ?Carbon $end): string
    {
        $start ??= Carbon::now();

        if ($event->all_day) {
            $dates = $start->format('Ymd').'/'.($end ?: $start)->copy()->addDay()->format('Ymd'); // exclusive end
        } else {
            $dates = $start->copy()->utc()->format('Ymd\THis\Z').'/'
                .($end ?: $start->copy()->addHour())->copy()->utc()->format('Ymd\THis\Z');
        }

        $params = [
            'action' => 'TEMPLATE',
            'text' => $event->title,
            'dates' => $dates,
            'details' => trim(strip_tags((string) $event->description)),
            'location' => (string) $this->location->of($event),
        ];

        return 'https://calendar.google.com/calendar/render?'.http_build_query(array_filter($params, fn ($v) => $v !== ''));
    }
}
