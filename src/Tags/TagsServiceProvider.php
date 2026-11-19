<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Tags;

use Flarum\Foundation\AbstractServiceProvider;

/** Discussions have no tags, unless FlarumTagsServiceProvider binds flarum/tags. */
class TagsServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(TagSupport::class, NoTags::class);
    }
}
