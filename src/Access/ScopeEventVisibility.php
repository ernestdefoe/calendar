<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Access;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who sees which events: nobody on a forum they can't view; published events
 * for everyone else; drafts only for calendar managers and their author.
 */
class ScopeEventVisibility
{
    public function __invoke(User $actor, Builder $query): void
    {
        if (! $actor->can('viewForum')) {
            $query->whereRaw('1 = 0'); // a constant false: no row is visible

            return;
        }

        if ($actor->hasPermission('calendar.manage')) {
            return;
        }

        $query->where(function (Builder $q) use ($actor) {
            $q->where('calendar_events.is_published', true);

            if (! $actor->isGuest()) {
                $q->orWhere('calendar_events.user_id', $actor->id);
            }
        });
    }
}
