<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Tags;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/** No tags extension: discussions have no tags to check or leave out. */
class NoTags implements TagSupport
{
    public function inUse(): bool
    {
        return false;
    }

    public function refused(User $author, array $tagIds): array
    {
        return [];
    }

    public function exclude(Builder $discussions, array $tagIds): void
    {
    }
}
