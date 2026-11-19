<?php

namespace ErnestDefoe\Calendar;

use Carbon\Carbon;
use ErnestDefoe\Calendar\Recurrence\RecurrenceExpander;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A calendar event.
 *
 * Hybrid model: an Event is its own first-class record (so the calendar and the
 * iCal feed stay clean and self-contained), but may optionally link to a
 * Discussion (`discussion_id`) that hosts comments/notifications for it.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $category_id
 * @property int|null $discussion_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property \Carbon\Carbon $start_at
 * @property \Carbon\Carbon|null $end_at
 * @property \Carbon\Carbon|null $finishes_at when it, or the last of its series, is over; null = repeats forever
 * @property bool $all_day
 * @property string $timezone
 * @property string|null $location
 * @property string|null $url
 * @property string|null $cover_url
 * @property string|null $rrule
 * @property bool $is_published
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property int|null $going_count     selected by EventResource::scope()
 * @property int|null $interested_count selected by EventResource::scope()
 * @property string|null $my_rsvp       selected by EventResource::scope()
 */
class Event extends AbstractModel
{
    use ScopeVisibilityTrait;

    protected $table = 'calendar_events';

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'finishes_at' => 'datetime',
        'all_day' => 'boolean',
        'is_published' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'all_day' => false,
        'timezone' => 'UTC',
        'is_published' => true,
    ];

    protected static function booted(): void
    {
        // Kept with every save, so a window query can skip what is over.
        static::saving(fn (self $event) => $event->finishes_at = $event->finishesAt());
    }

    /** When the event, or the last occurrence of its series, is over; null when it repeats forever. */
    public function finishesAt(): ?Carbon
    {
        $duration = $this->end_at ? (int) $this->start_at->diffInSeconds($this->end_at, true) : 0;

        if (! $this->isRecurring()) {
            return $this->start_at->copy()->addSeconds($duration);
        }

        return RecurrenceExpander::lastStart($this->rrule, $this->start_at)?->addSeconds($duration);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<EventCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'category_id');
    }

    /** @return BelongsTo<Discussion, $this> */
    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class, 'discussion_id');
    }

    /** @return HasMany<EventRsvp, $this> */
    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class, 'event_id');
    }

    public function isRecurring(): bool
    {
        return ! empty($this->rrule);
    }

    /**
     * Events, and series, with anything inside the [$from, $to] window; a
     * series' own occurrences are worked out by RecurrenceExpander.
     */
    public function scopeInWindow(Builder $query, Carbon $from, Carbon $to): Builder
    {
        // Started by the window's end, and not over before it starts. An event
        // with no end is a point in time; a series repeating forever is never
        // over (finishes_at null), and its occurrences are expanded later.
        return $query->where('start_at', '<=', $to)
            ->where(fn (Builder $q) => $q->whereNull('finishes_at')->orWhere('finishes_at', '>=', $from));
    }
}
