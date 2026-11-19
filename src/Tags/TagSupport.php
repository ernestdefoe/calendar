<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Tags;

use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/** What Calendar does with flarum/tags: NoTags without it, FlarumTags while it is enabled. */
interface TagSupport
{
    /** Whether a new discussion carries tags (with the tags extension, it must). */
    public function inUse(): bool;

    /**
     * The names of the tags, among $tagIds, that $author may not start a discussion in.
     *
     * @param int[] $tagIds
     * @return string[]
     */
    public function refused(User $author, array $tagIds): array;

    /**
     * Leave out discussions carrying any of $tagIds.
     *
     * @param int[] $tagIds
     */
    public function exclude(Builder $discussions, array $tagIds): void;
}
