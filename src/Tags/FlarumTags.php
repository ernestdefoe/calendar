<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Tags;

use Flarum\Tags\Tag;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/** Tags through flarum/tags. Bound only while it is enabled (Extend\Conditional in extend.php). */
class FlarumTags implements TagSupport
{
    public function inUse(): bool
    {
        return true;
    }

    public function refused(User $author, array $tagIds): array
    {
        if (! $tagIds) {
            return [];
        }

        return Tag::query()->whereIn('id', $tagIds)->get()
            // A restricted tag must also be visible to it, or core answers 404.
            ->reject(fn (Tag $tag) => $author->can('viewForum', $tag) && $author->can('startDiscussion', $tag))
            ->pluck('name')
            ->all();
    }

    public function exclude(Builder $discussions, array $tagIds): void
    {
        if ($tagIds) {
            $discussions->whereDoesntHave('tags', fn (Builder $q) => $q->whereIn('tags.id', $tagIds));
        }
    }
}
