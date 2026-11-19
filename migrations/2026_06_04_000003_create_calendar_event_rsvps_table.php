<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTableIfNotExists('calendar_event_rsvps', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('event_id');
    $table->unsignedInteger('user_id');
    $table->string('status', 20)->default('going'); // going | interested
    $table->timestamps();

    $table->unique(['event_id', 'user_id']);
    $table->index(['event_id', 'status']);

    $table->foreign('event_id')->references('id')->on('calendar_events')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
});
