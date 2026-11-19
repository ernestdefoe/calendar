<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Api;

use ErnestDefoe\Calendar\Birthday;
use Flarum\Api\Context;
use Flarum\Api\Resource\UserResource;
use Flarum\Api\Schema;
use Flarum\User\User;

/**
 * The opt-in birthday (month and day only) on the user resource. Members set
 * their own; someone who may edit the user can too. Only the member reads it
 * back, on their settings page, so no other request loads a birthday.
 */
class UserFields
{
    public function __invoke(): array
    {
        return [
            Schema\Str::make('calendarBirthday')
                ->nullable()
                // Read on the member's own settings page (GET /api/users/{id}),
                // never with the user every page carries: no query per page view.
                ->visible(fn (User $user, Context $context) => $context->getActor()->id === $user->id
                    && ($context->showing(UserResource::class) || $context->updating(UserResource::class)))
                ->writable(fn (User $user, Context $context) => $context->getActor()->id === $user->id
                    || $context->getActor()->can('edit', $user))
                // Month and day, or empty to clear it.
                ->rule('regex:'.Birthday::FORMAT)
                ->get(fn (User $user) => Birthday::query()->find($user->id)?->birthday)
                ->save(fn (User $user, ?string $value) => Birthday::setFor((int) $user->id, $value)),
        ];
    }
}
