<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/*
 * Opt-in birthdays: month and day only ("07-04"), never a year, so
 * celebrating them can never leak a member's age. Their own table, keyed on
 * the user. (Calendar 2.8 and older added users.cal_birthday here; forums that
 * ran that get the table from 2026_11_19_000000 and their birthdays moved by
 * 2026_11_19_000001.)
 */
return Migration::createTableIfNotExists('calendar_birthdays', function (Blueprint $table) {
    $table->unsignedInteger('user_id')->primary();
    $table->string('birthday', 5);

    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
});
