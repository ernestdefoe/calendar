<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar;

use Flarum\Http\SlugManager;
use Flarum\User\User;

/** The few user fields the calendar's widgets show: name, avatar and profile slug. */
class BasicUser
{
    public function __construct(private SlugManager $slugs)
    {
    }

    public function of(User $user): array
    {
        return [
            'username' => (string) $user->username,
            'displayName' => (string) $user->display_name,
            'avatarUrl' => $user->avatar_url,
            'avatarSrcset' => $user->avatar_srcset,
            'slug' => $this->slugs->forResource(User::class)->toSlug($user),
        ];
    }
}
