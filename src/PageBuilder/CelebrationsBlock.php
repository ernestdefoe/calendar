<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\PageBuilder;

/** The celebrations block: today's birthdays and join anniversaries. */
class CelebrationsBlock extends CalendarBlock
{
    public function type(): string
    {
        return 'celebrations';
    }

    public function icon(): string
    {
        return 'fas fa-birthday-cake';
    }

    public function settingsSchema(): array
    {
        return [
            $this->titleField(),
        ];
    }
}
