<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Tags;

use Flarum\Foundation\AbstractServiceProvider;

/** Registered only while flarum/tags is enabled (Extend\Conditional in extend.php). */
class FlarumTagsServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(TagSupport::class, FlarumTags::class);
    }
}
