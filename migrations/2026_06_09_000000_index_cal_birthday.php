<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * Index the birthday: the celebrations widget looks up today's month and day.
 * There is no Migration helper for adding an index. (Calendar 2.8 and older
 * indexed users.cal_birthday here.)
 */
return [
    'up' => function (Builder $schema) {
        if (! $schema->hasIndex('calendar_birthdays', ['birthday'])) {
            $schema->table('calendar_birthdays', function (Blueprint $table) {
                $table->index('birthday');
            });
        }
    },

    'down' => function (Builder $schema) {
        if ($schema->hasTable('calendar_birthdays') && $schema->hasIndex('calendar_birthdays', ['birthday'])) {
            $schema->table('calendar_birthdays', function (Blueprint $table) {
                $table->dropIndex(['birthday']);
            });
        }
    },
];
