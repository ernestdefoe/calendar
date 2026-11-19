<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Access;

use ErnestDefoe\Calendar\Event;
use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;

class EventPolicy extends AbstractPolicy
{
    /** Managers edit every event; an author edits their own while they may still create events. */
    public function edit(User $actor, Event $event): ?string
    {
        if ($actor->isGuest()) {
            return $this->deny();
        }

        if ($actor->hasPermission('calendar.manage')
            || ($event->user_id && (int) $actor->id === (int) $event->user_id && $actor->hasPermission('calendar.create'))) {
            return $this->allow();
        }

        return null;
    }

    public function delete(User $actor, Event $event): ?string
    {
        return $actor->can('edit', $event) ? $this->allow() : null;
    }

    /**
     * RSVP to a published event (an author may also RSVP to their draft), unless
     * its category has the RSVP buttons switched off ("game releases").
     */
    public function rsvp(User $actor, Event $event): ?string
    {
        if ($actor->isGuest() || ($event->category && ! $event->category->rsvp_enabled)) {
            return $this->deny();
        }

        return $event->is_published || (int) $event->user_id === (int) $actor->id ? $this->allow() : $this->deny();
    }
}
