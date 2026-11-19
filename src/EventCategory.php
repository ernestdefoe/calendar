<?php

namespace ErnestDefoe\Calendar;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admin-defined event category with a display colour.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $color
 * @property int $position
 * @property bool $rsvp_enabled
 * @property string|null $going_label
 * @property string|null $interested_label
 */
class EventCategory extends AbstractModel
{
    protected $table = 'calendar_categories';

    protected $casts = [
        'position' => 'integer',
        'rsvp_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'color' => '#3b5bdb',
        'rsvp_enabled' => true,
    ];

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'category_id');
    }
}
