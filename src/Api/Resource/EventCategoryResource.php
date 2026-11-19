<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Api\Resource;

use ErnestDefoe\Calendar\EventCategory;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Foundation\ValidationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * Event categories: `calendar-categories`. Anyone who can view the forum lists
 * them; only admins create, change or delete them. Deleting one keeps its
 * events, without a category (the foreign key is nullOnDelete).
 *
 * @extends AbstractDatabaseResource<EventCategory>
 */
class EventCategoryResource extends AbstractDatabaseResource
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function type(): string
    {
        return 'calendar-categories';
    }

    public function model(): string
    {
        return EventCategory::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $query->orderBy('position')->orderBy('name');
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->can('viewForum'),
            Endpoint\Show::make()->can('viewForum'),
            Endpoint\Create::make()->admin(),
            Endpoint\Update::make()->admin(),
            Endpoint\Delete::make()->admin(),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')
                ->writable()
                ->set(fn (EventCategory $category, $value) => $category->name = mb_substr(trim((string) $value), 0, 100)),
            Schema\Str::make('slug'),
            Schema\Str::make('color')
                ->writable()
                ->set(fn (EventCategory $category, $value) => $category->color = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $value) ? $value : '#3b5bdb'),
            Schema\Boolean::make('rsvp')
                ->writable()
                ->property('rsvp_enabled'),
            Schema\Str::make('goingLabel')
                ->nullable()
                ->writable()
                ->property('going_label')
                ->set(fn (EventCategory $category, $value) => $category->going_label = self::label($value)),
            Schema\Str::make('interestedLabel')
                ->nullable()
                ->writable()
                ->property('interested_label')
                ->set(fn (EventCategory $category, $value) => $category->interested_label = self::label($value)),
            Schema\Integer::make('position')
                ->writable(),
        ];
    }

    public function saving(object $model, OriginalContext $context): ?object
    {
        if (trim((string) $model->name) === '') {
            throw new ValidationException(['name' => $this->translator->trans('ernestdefoe-calendar.api.category_name_required')]);
        }

        if (! $model->slug || $model->isDirty('name')) {
            $model->slug = self::uniqueSlug($model->name, $model->id);
        }

        return $model;
    }

    /** The slug is check-then-insert: on a collision, retry with a fresh suffix instead of a 500. */
    public function create(object $model, OriginalContext $context): object
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

    private static function label(mixed $value): ?string
    {
        $label = trim((string) $value);

        return $label === '' ? null : mb_substr($label, 0, 50);
    }

    private static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;
        while (EventCategory::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
