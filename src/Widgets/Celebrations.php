<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Widgets;

use Carbon\Carbon;
use ErnestDefoe\Calendar\BasicUser;
use ErnestDefoe\Calendar\Birthday;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;

/** Today's birthdays and join anniversaries. */
class Celebrations
{
    /** Seconds today's list is served from the cache. */
    public const TTL = 600;

    public function __construct(private Repository $cache, private BasicUser $basicUser)
    {
    }

    public function today(): array
    {
        $now = Carbon::now();

        /*
         * 🚨 Cached per day. The widget sits on the index sidebar by default,
         * and the anniversary half matches on MONTH() and DAY() of joined_at,
         * which no index can serve: a scan of the users table for every visitor
         * on every index view, for a list that is the same for everyone all day.
         * A birthday set a moment ago shows within the TTL.
         */
        return $this->cache->remember(
            'ernestdefoe-calendar.celebrations.'.$now->toDateString(),
            self::TTL,
            fn () => $this->compute($now)
        );
    }

    private function compute(Carbon $now): array
    {
        $birthdays = User::query()
            ->whereIn('id', Birthday::query()->select('user_id')->where('birthday', $now->format('m-d')))
            ->orderBy('username')
            ->limit(40)
            ->get()
            ->map(fn (User $u) => $this->base($u) + ['type' => 'birthday']);

        $anniversaries = User::query()
            ->whereNotNull('joined_at')
            ->whereMonth('joined_at', $now->month)
            ->whereDay('joined_at', $now->day)
            ->whereYear('joined_at', '<', $now->year)
            ->orderBy('username')
            ->limit(40)
            ->get()
            ->map(fn (User $u) => $this->base($u) + [
                'type' => 'anniversary',
                'years' => $now->year - $u->joined_at->year,
            ]);

        return $birthdays->concat($anniversaries)->values()->all();
    }

    private function base(User $u): array
    {
        return ['userId' => (int) $u->id] + $this->basicUser->of($u);
    }
}
