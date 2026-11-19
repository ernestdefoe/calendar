<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Events;

use ErnestDefoe\Calendar\Event;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface;

/** Someone who may edit an event asked for its comment thread to be started. */
class DiscussionRequested
{
    public function __construct(
        public Event $event,
        public User $actor,
        public ServerRequestInterface $request,
    ) {
    }
}
