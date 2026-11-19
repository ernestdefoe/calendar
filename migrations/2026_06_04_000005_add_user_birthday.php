<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * Opt-in birthdays: month and day only ("07-04"), never a year, so
 * celebrating them can never leak a member's age. Their own table, keyed on
 * the user. Calendar 2.8 and older added users.cal_birthday here instead:
 * forums that ran that get the table from 2026_11_19_000000 and their
 * birthdays moved by 2026_11_19_000001. Rolling back removes either, which is
 * why this isn't Migration::createTable.
 */
return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('calendar_birthdays')) {
            $schema->create('calendar_birthdays', function (Blueprint $table) {
                $table->unsignedInteger('user_id')->primary();
                $table->string('birthday', 5);

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('calendar_birthdays');

        if ($schema->hasColumn('users', 'cal_birthday')) {
            $schema->table('users', function (Blueprint $table) {
                $table->dropColumn('cal_birthday');
            });
        }
    },
];
