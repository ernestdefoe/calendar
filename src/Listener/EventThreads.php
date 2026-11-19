<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Listener;

use ErnestDefoe\Calendar\EventDiscussion;
use ErnestDefoe\Calendar\Events\DiscussionRequested;
use ErnestDefoe\Calendar\Events\EventSaved;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Keeps each event's comment thread in step: started with a new published
 * event (when the admin turned that on), renamed with it, or started on
 * request. A listener rather than part of EventResource because starting a
 * thread goes through the JSON:API, which builds every resource, this one's
 * included: injected straight into the resource, that is a loop.
 */
class EventThreads
{
    public function __construct(private EventDiscussion $discussions)
    {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(EventSaved::class, [$this, 'saved']);
        $events->listen(DiscussionRequested::class, [$this, 'requested']);
    }

    public function saved(EventSaved $saved): void
    {
        if (! $saved->created) {
            $this->discussions->syncTitle($saved->event);

            return;
        }

        // A draft's thread would show it to everyone before it is published.
        if ($saved->request && $saved->event->is_published !== false && $this->discussions->automatic()) {
            $this->discussions->startQuietly($saved->event, $saved->actor, $saved->request);
        }
    }

    public function requested(DiscussionRequested $requested): void
    {
        if ($requested->event->is_published && ! $requested->event->discussion_id) {
            $this->discussions->start($requested->event, $requested->actor, $requested->request);
        }
    }
}
