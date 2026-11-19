<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Api\Resource;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Tobyz\JsonApiServer\Context;

/** Readable unique slugs for a resource's models: "meetup", "meetup-2", … */
trait UniqueSlugs
{
    /** The first free slug for $text, found with one query. */
    protected function uniqueSlug(Model $model, string $text, string $fallback): string
    {
        // Str::slug() leaves only letters, digits and dashes: nothing LIKE treats specially.
        $base = Str::slug($text) ?: $fallback;

        $taken = $model->newQuery()
            ->when($model->exists, fn ($q) => $q->whereKeyNot($model->getKey()))
            ->where(fn ($q) => $q->where('slug', $base)->orWhere('slug', 'like', $base.'-%'))
            ->pluck('slug')
            ->flip();

        for ($i = 2, $slug = $base; isset($taken[$slug]); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /**
     * The slug is check-then-insert, so two requests can pick the same one and
     * collide on the unique index: retry with a random suffix, not a 500.
     */
    public function create(object $model, Context $context): object
    {
        $baseSlug = $model->slug;

        for ($attempt = 0;; $attempt++) {
            try {
                return parent::create($model, $context);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
                $model->slug = $baseSlug.'-'.Str::lower(Str::random(5));
            }
        }
    }
}
