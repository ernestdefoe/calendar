<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;

/** What the forum payload tells the frontend about the viewer's calendar permissions. */
class ForumFields
{
    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('canCreateEvent')
                ->get(fn ($forum, Context $context) => $context->getActor()->can('calendar.create')),
            Schema\Boolean::make('canManageCalendar')
                ->get(fn ($forum, Context $context) => $context->getActor()->can('calendar.manage')),
        ];
    }
}
