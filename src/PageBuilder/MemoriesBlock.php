<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\PageBuilder;

/** The on-this-day block: discussions started on today's date in earlier years. */
class MemoriesBlock extends CalendarBlock
{
    public function type(): string
    {
        return 'memories';
    }

    public function icon(): string
    {
        return 'fas fa-history';
    }

    public function settingsSchema(): array
    {
        return [
            $this->titleField(),
            $this->countField(6),
        ];
    }
}
