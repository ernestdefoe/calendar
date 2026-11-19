<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar;

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * The admin can switch the location field off (`location_mode` = off) for
 * forums whose events all happen online. The stored value is kept, so
 * switching it back on restores it, but while it is off every reader (the API,
 * the .ics feed, Google Calendar links, event threads) sees none.
 */
class LocationSetting
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    public function enabled(): bool
    {
        return $this->settings->get('ernestdefoe-calendar.location_mode') !== 'off';
    }

    /** The event's location as readers may see it. */
    public function of(Event $event): ?string
    {
        return $this->enabled() ? $event->location : null;
    }
}
