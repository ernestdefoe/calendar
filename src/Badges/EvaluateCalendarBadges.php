<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Badges;

use Flarum\User\User;
use FoF\Badges\BadgeAwarder;
use FoF\Badges\Trigger\MetricManager;
use FoF\Badges\Trigger\TriggerEvaluator;

/**
 * Awards FoF Badges that use Calendar's metrics when an RSVP changes or an
 * event is saved.
 *
 * fof/badges' own listener only knows which user an event is about for the
 * events it ships with, so Calendar's events are evaluated here, with the
 * extractor each metric declares. Posts and sign-ins are left to fof/badges.
 */
class EvaluateCalendarBadges
{
    public function __construct(
        private MetricManager $metrics,
        private TriggerEvaluator $evaluator,
        private BadgeAwarder $awarder,
    ) {
    }

    public function handle(object $event): void
    {
        $class = get_class($event);

        $users = [];
        foreach ($this->metrics->getTypesForEvent($class) as $type) {
            $user = ($this->metrics->getUserExtractor($type, $class))($event);
            if ($user instanceof User && $user->exists) {
                $users[$user->id] = $user;
            }
        }

        foreach ($users as $user) {
            foreach ($this->evaluator->evaluateForEvent($user, $class) as $badge) {
                $this->awarder->award($user, $badge);
            }
        }
    }
}
