<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Api;

use ErnestDefoe\Calendar\Birthday;
use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\User\User;

/**
 * The opt-in birthday (month and day only) on the user resource. Members set
 * their own; someone who may edit the user can too. Only the member reads it
 * back (their settings page), so a user list never loads birthdays.
 */
class UserFields
{
    public function __invoke(): array
    {
        return [
            Schema\Str::make('calendarBirthday')
                ->nullable()
                ->visible(fn (User $user, Context $context) => $context->getActor()->id === $user->id)
                ->writable(fn (User $user, Context $context) => $context->getActor()->id === $user->id
                    || $context->getActor()->can('editUser', $user))
                ->get(fn (User $user) => Birthday::query()->find($user->id)?->birthday)
                ->set(fn () => null) // stored after the user is saved, below
                ->save(fn (User $user, ?string $value) => Birthday::setFor((int) $user->id, $value)),
        ];
    }
}
