<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;

/**
 * Whether the viewer may upload with FoF Upload: the event form then offers a
 * file picker for the cover (else a plain URL field). Added to the forum
 * payload only while FoF Upload is enabled (Extend\Conditional in extend.php).
 */
class UploadFields
{
    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('calendarCoverUploads')
                ->get(fn ($forum, Context $context) => $context->getActor()->hasPermission('fof-upload.upload')),
        ];
    }
}
