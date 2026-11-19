<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\PageBuilder;

/** The forum pulse block: comments per day and the most active members. */
class PulseBlock extends CalendarBlock
{
    public function type(): string
    {
        return 'pulse';
    }

    public function icon(): string
    {
        return 'fas fa-chart-line';
    }

    public function settingsSchema(): array
    {
        return [
            $this->titleField(),
            $this->countField(5, 20, 'pulse.count_label'),
            ['key' => 'weeks', 'type' => 'range', 'label' => $this->t('pulse.weeks_label'), 'default' => 14, 'min' => 4, 'max' => 53],
        ];
    }
}
