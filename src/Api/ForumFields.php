<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Extension\ExtensionManager;

/** What the forum payload tells the frontend about the viewer's calendar permissions. */
class ForumFields
{
    public function __construct(private ExtensionManager $extensions)
    {
    }

    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('canCreateEvent')
                ->get(fn ($forum, Context $context) => $context->getActor()->can('calendar.create')),
            Schema\Boolean::make('canManageCalendar')
                ->get(fn ($forum, Context $context) => $context->getActor()->can('calendar.manage')),
            // FoF Upload enabled AND the viewer may upload: the event form then
            // offers a file picker for the cover (else a plain URL field).
            Schema\Boolean::make('calendarCoverUploads')
                ->get(fn ($forum, Context $context) => $this->extensions->isEnabled('fof-upload')
                    && $context->getActor()->hasPermission('fof-upload.upload')),
        ];
    }
}
