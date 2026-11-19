<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTableIfNotExists('calendar_categories', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name', 100);
    $table->string('slug', 100);
    $table->string('color', 20)->default('#3b5bdb');
    $table->unsignedInteger('position')->default(0);
    $table->timestamps();

    $table->unique('slug');
    $table->index('position');
});
