<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Widgets;

use ErnestDefoe\Calendar\Activity\ActivityRepository;
use ErnestDefoe\Calendar\BasicUser;
use ErnestDefoe\Calendar\SettingIds;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;

/** The forum pulse: comments per day and the most active members. */
class Pulse
{
    /** Seconds a computed pulse is served from the cache. */
    public const TTL = 600;

    public function __construct(
        private ActivityRepository $activity,
        private Repository $cache,
        private SettingsRepositoryInterface $settings,
        private BasicUser $basicUser,
    ) {
    }

    public function for(User $viewer, int $days, int $leaderDays, int $limit): array
    {
        /*
         * 🚨 Cached. The pulse widget sits on the index sidebar by default, so
         * this ran two aggregate scans over up to a year of the posts table for
         * every visitor on every index view. It counts only posts the viewer can
         * see, and that depends on their groups, so the groups are part of the
         * key; so are the exclusions, so changing them in the admin takes effect
         * on the next request instead of after the TTL.
         */
        $groups = (string) $this->settings->get('ernestdefoe-calendar.pulse_exclude_groups');
        $users = (string) $this->settings->get('ernestdefoe-calendar.pulse_exclude_users');
        $viewerGroups = $viewer->groups->pluck('id')->sort()->implode(',');

        return $this->cache->remember(
            "ernestdefoe-calendar.pulse.$days.$leaderDays.$limit.".md5($groups.'|'.$users.'|'.$viewerGroups),
            self::TTL,
            fn () => $this->compute($viewer, $days, $leaderDays, $limit, $this->exclusions($groups, $users))
        );
    }

    /**
     * The authors the admin left out of the pulse: groups by id, users by
     * username, resolved to ids inside the cached compute, so the lookup runs
     * once per TTL rather than on every request.
     */
    private function exclusions(string $groups, string $users): array
    {
        // "@newsbot, rss-feed": a leading @ is how members write a username.
        $names = array_values(array_filter(array_map(fn ($n) => ltrim(trim($n), '@'), explode(',', $users))));
        $userIds = $names ? User::query()->whereIn('username', $names)->pluck('id')->map(fn ($id) => (int) $id)->all() : [];

        return ['groups' => SettingIds::from($groups), 'users' => $userIds];
    }

    private function compute(User $viewer, int $days, int $leaderDays, int $limit, array $exclude): array
    {
        $daily = $this->activity->forumDaily($viewer, $days, $exclude);

        $leaders = $this->activity->leaders($viewer, $leaderDays, $limit, $exclude);
        $users = User::query()->whereIn('id', array_column($leaders, 'userId'))->get()->keyBy('id');

        $board = [];
        foreach ($leaders as $row) {
            if ($user = $users->get($row['userId'])) {
                $board[] = ['userId' => (int) $user->id] + $this->basicUser->of($user) + ['count' => $row['count']];
            }
        }

        return [
            'days' => (object) $daily,
            'total' => array_sum($daily),
            'max' => $daily ? max($daily) : 0,
            'leaders' => $board,
            'leaderDays' => $leaderDays,
        ];
    }
}
