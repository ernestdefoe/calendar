<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar;

use Flarum\Database\AbstractModel;

/**
 * A member's birthday as month and day ("07-04"), never a year, so the
 * celebrations widget can't leak anyone's age. Its own table, keyed on the
 * user: Calendar adds no columns to core's `users` table.
 *
 * @property int $user_id
 * @property string $birthday
 */
class Birthday extends AbstractModel
{
    public const FORMAT = '/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/';

    protected $table = 'calendar_birthdays';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    /** Store a member's birthday (validated as MM-DD by UserFields), or forget it when empty. */
    public static function setFor(int $userId, ?string $value): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            static::query()->where('user_id', $userId)->delete();

            return;
        }

        $birthday = static::query()->find($userId) ?? new static();
        $birthday->user_id = $userId;
        $birthday->birthday = $value;
        $birthday->save();
    }
}
