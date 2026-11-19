<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\PageBuilder;

/** The upcoming events block. */
class EventsBlock extends CalendarBlock
{
    public function type(): string
    {
        return 'events';
    }

    public function icon(): string
    {
        return 'fas fa-calendar-day';
    }

    public function settingsSchema(): array
    {
        return [
            $this->titleField(),
            $this->countField(5),
            ['key' => 'category', 'type' => 'text', 'label' => $this->t('events.category_label'), 'default' => ''],
        ];
    }
}
