<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Api\Resource;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use ErnestDefoe\Calendar\Api\EventInput;
use ErnestDefoe\Calendar\Api\EventLinks;
use ErnestDefoe\Calendar\Attendees;
use ErnestDefoe\Calendar\Event;
use ErnestDefoe\Calendar\EventRsvp;
use ErnestDefoe\Calendar\Events\DiscussionRequested;
use ErnestDefoe\Calendar\Events\EventSaved;
use ErnestDefoe\Calendar\Events\RsvpChanged;
use ErnestDefoe\Calendar\LocationSetting;
use ErnestDefoe\Calendar\Recurrence\RecurrenceExpander;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Tobyz\JsonApiServer\Context as OriginalContext;
use Tobyz\JsonApiServer\Exception\ForbiddenException;

/**
 * Calendar events: `calendar-events`.
 *
 * The list takes `filter[from]`, `filter[to]` and `filter[category]` and
 * returns the events in that window, each with the `occurrences` that fall inside it, so a
 * recurring series is one resource however many times it repeats. RSVP counts
 * and the viewer's own answer come from subqueries in the same SELECT, never a
 * query per event.
 *
 * @extends AbstractDatabaseResource<Event>
 */
class EventResource extends AbstractDatabaseResource
{
    use ReadsFilters;
    use UniqueSlugs;

    /** A list never loads more series than this. */
    public const LIST_LIMIT = 400;

    /** The widest window a list may ask for, so a series can't expand without end. */
    public const MAX_WINDOW_DAYS = 420;

    public function __construct(
        private EventInput $input,
        private Attendees $attendees,
        private LocationSetting $location,
        private EventLinks $links,
    ) {
    }

    public function type(): string
    {
        return 'calendar-events';
    }

    public function model(): string
    {
        return Event::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $actor = $context->getActor();
        $query->whereVisibleTo($actor);

        $query->withCount([
            'rsvps as going_count' => fn (Builder $q) => $q->where('status', EventRsvp::GOING),
            'rsvps as interested_count' => fn (Builder $q) => $q->where('status', EventRsvp::INTERESTED),
        ]);

        if (! $actor->isGuest()) {
            $query->addSelect(['my_rsvp' => EventRsvp::query()->select('status')
                ->whereColumn('event_id', 'calendar_events.id')
                ->where('user_id', $actor->id)
                ->limit(1)]);
        }

        if ($context->listing(self::class)) {
            [$from, $to] = $this->window($context);

            // The calendar lists published events only; drafts open from their link.
            $query->where('calendar_events.is_published', true)->inWindow($from, $to);

            if ($category = trim((string) self::filterValue($context, 'category'))) {
                $query->whereHas('category', fn (Builder $q) => $q->where('slug', $category)->orWhere('id', (int) $category));
            }

            $query->orderBy('start_at')->limit(self::LIST_LIMIT);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->can('viewForum')
                ->defaultInclude(['user', 'category'])
                // scope() applies the window and category filters; the default
                // query would hand them to a model searcher, which events lack.
                ->query(fn ($query, $pagination, Context $context) => $context->withQuery($query)),
            Endpoint\Show::make()
                ->defaultInclude(['user', 'category']),
            Endpoint\Create::make()
                ->authenticated()
                ->can('calendar.create')
                ->defaultInclude(['user', 'category']),
            Endpoint\Update::make()
                ->authenticated()
                ->can('edit')
                ->defaultInclude(['user', 'category']),
            Endpoint\Delete::make()
                ->authenticated()
                ->can('delete'),
            Endpoint\Endpoint::make('rsvp')
                ->route('POST', '/{id}/rsvp')
                ->authenticated()
                ->action(fn (Context $context) => $this->rsvp($context))
                ->defaultInclude(['user', 'category']),
            Endpoint\Endpoint::make('discussion')
                ->route('POST', '/{id}/discussion')
                ->authenticated()
                ->action(fn (Context $context) => $this->startDiscussion($context))
                ->defaultInclude(['user', 'category']),
        ];
    }

    public function fields(): array
    {
        return [
            ...$this->detailFields(),
            ...$this->whenFields(),
            ...$this->computedFields(),

            Schema\Relationship\ToOne::make('user')
                ->includable()
                ->type('users'),
            Schema\Relationship\ToOne::make('category')
                ->includable()
                ->type('calendar-categories'),
        ];
    }

    /** What the event is: title, text, place, links and category. */
    private function detailFields(): array
    {
        return [
            Schema\Str::make('title')
                ->writable()
                ->set(fn (Event $event, $value) => $event->title = EventInput::title($value)),
            Schema\Str::make('slug'),
            Schema\Str::make('description')
                ->nullable()
                ->writable()
                ->set(fn (Event $event, $value) => $event->description = mb_substr((string) $value, 0, 20000)),
            // Ignored, and never shown, while the admin has the location field switched off.
            Schema\Str::make('location')
                ->nullable()
                ->writable()
                ->get(fn (Event $event) => $this->location->of($event))
                ->set(function (Event $event, $value) {
                    if ($this->location->enabled()) {
                        $event->location = EventInput::str($value, 255);
                    }
                }),
            Schema\Str::make('url')
                ->nullable()
                ->writable()
                ->set(fn (Event $event, $value) => $event->url = EventInput::url($value)),
            Schema\Str::make('coverUrl')
                ->nullable()
                ->writable()
                ->property('cover_url')
                ->set(fn (Event $event, $value) => $event->cover_url = EventInput::url($value)),
            Schema\Integer::make('categoryId')
                ->nullable()
                ->writable()
                ->rule('exists:calendar_categories,id')
                ->property('category_id')
                ->set(fn (Event $event, $value) => $event->category_id = $value ? (int) $value : null),
        ];
    }

    /** When it happens: start, end, all day, time zone and repeat rule. */
    private function whenFields(): array
    {
        return [
            Schema\Str::make('start')
                ->writable()
                ->get(fn (Event $event) => $event->start_at?->toIso8601String())
                ->set(fn (Event $event, $value) => $event->start_at = EventInput::date($value)),
            Schema\Str::make('end')
                ->nullable()
                ->writable()
                ->get(fn (Event $event) => $event->end_at?->toIso8601String())
                ->set(fn (Event $event, $value) => $event->end_at = EventInput::date($value)),
            Schema\Boolean::make('allDay')
                ->writable()
                ->property('all_day'),
            Schema\Str::make('timezone')
                ->writable()
                ->set(fn (Event $event, $value) => $event->timezone = EventInput::timezone($value)),
            Schema\Str::make('rrule')
                ->nullable()
                ->writable()
                ->set(fn (Event $event, $value) => $event->rrule = EventInput::rrule($value)),
            Schema\Boolean::make('recurring')
                ->get(fn (Event $event) => $event->isRecurring()),
            // A draft (false) is hidden from the calendar and gets no thread until
            // it is published; its author and calendar managers can still open it.
            Schema\Boolean::make('isPublished')
                ->writable()
                ->property('is_published'),
        ];
    }

    /** Read-only: thread, permissions, export links, occurrences and RSVPs. */
    private function computedFields(): array
    {
        return [
            Schema\Integer::make('discussionId')
                ->nullable()
                ->property('discussion_id'),
            Schema\Boolean::make('canEdit')
                ->get(fn (Event $event, Context $context) => $context->getActor()->can('edit', $event)),
            Schema\Str::make('icalUrl')
                ->get(fn (Event $event) => 'calendar/events/'.$event->id.'/ical'), // a path; the client prefixes baseUrl
            Schema\Str::make('googleUrl')
                ->get(fn (Event $event) => $this->links->google($event, $event->start_at, $event->end_at)),
            Schema\Arr::make('occurrences')
                ->visible(fn (Event $event, Context $context) => $context->listing(self::class))
                ->get(fn (Event $event, Context $context) => $this->occurrences($event, $context)),
            Schema\Arr::make('rsvp')
                ->get(fn (Event $event, Context $context) => $this->rsvpSummary($event, $context)),
        ];
    }

    public function creating(object $model, OriginalContext $context): ?object
    {
        $model->user_id = $context->getActor()->id;

        return $model;
    }

    public function saving(object $model, OriginalContext $context): ?object
    {
        $this->input->validate($model);

        if (! $model->slug || $model->isDirty('title')) {
            $model->slug = $this->uniqueSlug($model, $model->title, 'event');
        }

        return $model;
    }

    public function saved(object $model, OriginalContext $context): ?object
    {
        // EventThreads starts or renames the event's thread; FoF Badges checks its triggers.
        $this->events->dispatch(new EventSaved($model, $context->getActor(), $context->creating(self::class), $context->request));

        return $model;
    }

    private function rsvp(Context $context): Event
    {
        $actor = $context->getActor();
        /** @var Event $event found through scope(), so only if the actor may see it */
        $event = $context->model;

        if (! $actor->can('rsvp', $event)) {
            throw new ForbiddenException();
        }

        $status = (string) ($context->body()['data']['attributes']['status'] ?? '');
        $existing = EventRsvp::query()->where('event_id', $event->id)->where('user_id', $actor->id)->first();
        $before = $existing?->status;

        if ($status === 'none' || $status === '') {
            $existing?->delete();
        } elseif (in_array($status, [EventRsvp::GOING, EventRsvp::INTERESTED], true)) {
            try {
                $this->saveRsvp($existing ?: new EventRsvp(), $event, $actor->id, $status);
            } catch (UniqueConstraintViolationException) {
                // A double click: the other request inserted the row first, so update it.
                $this->saveRsvp(EventRsvp::query()->where('event_id', $event->id)->where('user_id', $actor->id)->firstOrFail(), $event, $actor->id, $status);
            }
        }

        $event = $this->reload($context);
        if ($event->my_rsvp !== $before) {
            $this->events->dispatch(new RsvpChanged($event, $actor, $event->my_rsvp));
        }

        return $event;
    }

    private function saveRsvp(EventRsvp $rsvp, Event $event, int $userId, string $status): void
    {
        // 🚨 Never construct with an attribute array: Flarum's AbstractModel
        // guards every attribute, so it threw MassAssignmentException.
        $rsvp->event_id = $event->id;
        $rsvp->user_id = $userId;
        $rsvp->status = $status;
        $rsvp->save();
    }

    private function startDiscussion(Context $context): Event
    {
        /** @var Event $event found through scope(), so only if the actor may see it */
        $event = $context->model;

        if (! $context->getActor()->can('edit', $event)) {
            throw new ForbiddenException();
        }

        // A draft's thread would show it to everyone before it is published.
        if (! $event->is_published) {
            throw new ModelNotFoundException();
        }

        $this->events->dispatch(new DiscussionRequested($event, $context->getActor(), $context->request));

        return $this->reload($context);
    }

    /** The event again, for the RSVP counts, the actor's answer and the thread id as just saved. */
    private function reload(Context $context): Event
    {
        return $this->query($context)->findOrFail($context->modelId);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function window(OriginalContext $context): array
    {
        $from = $this->date(self::filterValue($context, 'from')) ?? Carbon::now()->startOfMonth();
        $to = $this->date(self::filterValue($context, 'to')) ?? (clone $from)->addMonths(2);

        if ($to->lt($from)) {
            $to = (clone $from)->addMonth();
        }
        if ($from->diffInDays($to) > self::MAX_WINDOW_DAYS) {
            $to = (clone $from)->addDays(self::MAX_WINDOW_DAYS);
        }

        return [$from, $to];
    }

    private function date(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (InvalidFormatException) {
            return null; // a bad date means the default window
        }
    }

    /** The series' occurrences inside the listed window; a single event has one. */
    private function occurrences(Event $event, OriginalContext $context): array
    {
        [$from, $to] = $this->window($context);
        $starts = $event->isRecurring()
            ? RecurrenceExpander::occurrences($event->rrule, $event->start_at, $from, $to)
            : [null];

        return array_map(function (?Carbon $start) use ($event) {
            $end = $event->end_at && $start ? $start->copy()->addSeconds($event->start_at->diffInSeconds($event->end_at)) : $event->end_at;
            $start ??= $event->start_at;

            return [
                'occurrence' => $event->isRecurring() ? $start->toIso8601String() : null,
                'start' => $start->toIso8601String(),
                'end' => $end?->toIso8601String(),
                'googleUrl' => $this->links->google($event, $start, $end),
            ];
        }, $starts);
    }

    private function rsvpSummary(Event $event, Context $context): array
    {
        return [
            'going' => (int) $event->going_count,
            'interested' => (int) $event->interested_count,
            'mine' => $event->my_rsvp,
            // Names and avatars for a single event only: one query, never per list row.
            'attendees' => $context->listing(self::class) ? null : $this->attendees->build((int) $event->id),
        ];
    }
}
