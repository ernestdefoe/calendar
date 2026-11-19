<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/*
 * Calendar 2.8 and older kept birthdays in users.cal_birthday. Copy them into
 * calendar_birthdays, then drop the column and its index from core's table.
 * On a fresh install there is no column and nothing to do.
 * There is no Migration helper for moving data between tables.
 */
return [
    'up' => function (Builder $schema) {
        if (! $schema->hasColumn('users', 'cal_birthday')) {
            return;
        }

        $db = $schema->getConnection();
        $db->table('users')->whereNotNull('cal_birthday')->select(['id', 'cal_birthday'])->orderBy('id')
            ->chunk(500, function ($rows) use ($db) {
                $db->table('calendar_birthdays')->insertOrIgnore($rows->map(fn ($row) => [
                    'user_id' => $row->id,
                    'birthday' => $row->cal_birthday,
                ])->all());
            });

        // Older Calendar versions added this index under a fixed name.
        if ($schema->hasIndex('users', 'users_cal_birthday_index')) {
            $schema->table('users', function (Blueprint $table) {
                $table->dropIndex('users_cal_birthday_index');
            });
        }
        $schema->table('users', function (Blueprint $table) {
            $table->dropColumn('cal_birthday');
        });
    },

    // Copy them back while the column is still there; this never adds it to
    // users again, so uninstalling leaves core's table as it was.
    'down' => function (Builder $schema) {
        if (! $schema->hasColumn('users', 'cal_birthday') || ! $schema->hasTable('calendar_birthdays')) {
            return;
        }

        $db = $schema->getConnection();
        $db->table('calendar_birthdays')->orderBy('user_id')->chunk(500, function ($rows) use ($db) {
            foreach ($rows as $row) {
                $db->table('users')->where('id', $row->user_id)->update(['cal_birthday' => $row->birthday]);
            }
        });
    },
];
