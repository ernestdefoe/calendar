<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

// For forums that ran Calendar 2.8 or older, whose 2026_06_04_000005 added a
// column to users instead: their birthdays move here in the next migration.
return Migration::createTableIfNotExists('calendar_birthdays', function (Blueprint $table) {
    $table->unsignedInteger('user_id')->primary();
    $table->string('birthday', 5)->index();

    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
});
