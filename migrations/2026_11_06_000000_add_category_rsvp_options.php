<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

use Flarum\Database\Migration;

// Per category: RSVP buttons on or off, and the wording on them (blank = the
// language's own "Going" / "Interested").
return Migration::addColumns('calendar_categories', [
    'rsvp_enabled' => ['boolean', 'default' => true],
    'going_label' => ['string', 'length' => 50, 'nullable' => true],
    'interested_label' => ['string', 'length' => 50, 'nullable' => true],
]);
