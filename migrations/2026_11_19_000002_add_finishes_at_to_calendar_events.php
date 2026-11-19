<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

use ErnestDefoe\Calendar\Event;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * When each event, or the last occurrence of its series, is over (null: it
 * repeats forever), so a calendar window skips everything already past. Kept
 * by Event on every save; existing events are filled in here, which no
 * Migration helper does.
 */
return [
    'up' => function (Builder $schema) {
        if (! $schema->hasColumn('calendar_events', 'finishes_at')) {
            $schema->table('calendar_events', function (Blueprint $table) {
                $table->dateTime('finishes_at')->nullable()->index();
            });
        }

        Event::query()->orderBy('id')->chunkById(200, function ($events) {
            foreach ($events as $event) {
                $event->newQuery()->whereKey($event->id)->update(['finishes_at' => $event->finishesAt()]);
            }
        });
    },

    // The index first: SQLite refuses to drop an indexed column.
    'down' => function (Builder $schema) {
        $schema->table('calendar_events', function (Blueprint $table) {
            $table->dropIndex(['finishes_at']);
        });
        $schema->table('calendar_events', function (Blueprint $table) {
            $table->dropColumn('finishes_at');
        });
    },
];
