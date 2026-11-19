<?php

namespace ErnestDefoe\Calendar\Activity;

use Carbon\Carbon;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aggregates forum activity into the day-bucketed shapes the heatmap, streaks,
 * pulse and leaderboard need. The signal is comment posts (which covers both
 * replies AND new discussions, since a discussion's opening post is a comment) —
 * the one universal "this member showed up today" measure every forum has.
 *
 * Days are bucketed by the DB's DATE() (UTC on a standard install). Queries are
 * grouped/aggregated in SQL so even a busy forum only ships small rollups.
 */
class ActivityRepository
{
    /** Daily comment counts for one user over the last $days days, keyed 'Y-m-d', counting only posts $viewer can see. */
    public function userDaily(User $viewer, int $userId, int $days = 365): array
    {
        return $this->daily($viewer, $days, fn ($q) => $q->where('posts.user_id', $userId));
    }

    /**
     * Daily comment counts across the whole forum over the last $days days,
     * leaving out the authors in $exclude (see excluding()).
     */
    public function forumDaily(User $viewer, int $days = 120, array $exclude = []): array
    {
        return $this->daily($viewer, $days, fn ($q) => $this->excluding($q->whereNotNull('posts.user_id'), $exclude));
    }

    /** Top contributors (by comment count) over the last $days days. */
    public function leaders(User $viewer, int $days = 30, int $limit = 10, array $exclude = []): array
    {
        $from = Carbon::now()->subDays($days - 1)->startOfDay();

        $query = Post::query()->whereVisibleTo($viewer);
        // Raw SQL takes the table prefix verbatim, so the column goes through the grammar.
        $userId = $query->getQuery()->getGrammar()->wrap('posts.user_id');

        return $this->excluding($query, $exclude)
            ->selectRaw("$userId as user_id, COUNT(*) as c")
            ->where('posts.type', 'comment')
            ->whereNotNull('posts.user_id')
            ->where('posts.is_private', false)
            ->whereNull('posts.hidden_at')
            ->where('posts.created_at', '>=', $from)
            ->groupBy('posts.user_id')
            ->orderByDesc('c')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn ($r) => ['userId' => (int) $r->user_id, 'count' => (int) $r->c])
            ->all();
    }

    /**
     * Current + longest streak (consecutive days with ≥1 comment) from a daily
     * map. The current streak counts back from today; if there's nothing yet
     * today it counts back from yesterday, so the streak stays "alive" until a
     * whole day passes with no activity.
     */
    public function streak(array $daily): array
    {
        $set = [];
        foreach ($daily as $d => $c) {
            if ($c > 0) {
                $set[$d] = true;
            }
        }

        // Current: walk backwards from today (or yesterday if today is empty).
        $current = 0;
        $cursor = Carbon::now()->startOfDay();
        if (empty($set[$cursor->toDateString()])) {
            $cursor = $cursor->subDay();
        }
        while (! empty($set[$cursor->toDateString()])) {
            $current++;
            $cursor = $cursor->subDay();
        }

        // Longest: measure each run from its start (a day whose predecessor is empty).
        $longest = 0;
        foreach (array_keys($set) as $d) {
            $prev = Carbon::parse($d)->subDay()->toDateString();
            if (! empty($set[$prev])) {
                continue;
            } // not the start of a run
            $len = 0;
            $c = Carbon::parse($d);
            while (! empty($set[$c->toDateString()])) {
                $len++;
                $c = $c->addDay();
            }
            $longest = max($longest, $len);
        }

        return ['current' => $current, 'longest' => max($longest, $current)];
    }

    /**
     * Leave out authors an admin has excluded from the pulse: members of the
     * `groups` ids, plus the `users` ids. Meant for automated accounts — a
     * feed bot posting daily tops the leaderboard forever and drowns out the
     * people it exists to show off. The group match is a subquery, so it is
     * one query however many members the group has.
     */
    private function excluding(Builder $query, array $exclude): Builder
    {
        if (! empty($exclude['groups'])) {
            $query->whereNotIn('posts.user_id', fn ($q) => $q->select('user_id')->from('group_user')->whereIn('group_id', $exclude['groups']));
        }

        if (! empty($exclude['users'])) {
            $query->whereNotIn('posts.user_id', $exclude['users']);
        }

        return $query;
    }

    private function daily(User $viewer, int $days, callable $scope): array
    {
        $from = Carbon::now()->subDays($days - 1)->startOfDay();

        // DATE() + GROUP BY the alias is portable: MySQL, PostgreSQL (date() is a
        // function-style cast, and GROUP BY accepts output-column names) and
        // SQLite all run it, proved on PostgreSQL 16. CAST(... AS DATE) is NOT:
        // SQLite turns '2026-11-02 10:00:00' into 2026.
        $q = Post::query()->whereVisibleTo($viewer);
        $createdAt = $q->getQuery()->getGrammar()->wrap('posts.created_at'); // raw SQL: the prefix is ours to add
        $q->selectRaw("DATE($createdAt) as d, COUNT(*) as c")
            ->where('posts.type', 'comment')
            ->where('posts.is_private', false)
            ->whereNull('posts.hidden_at')
            ->where('posts.created_at', '>=', $from)
            ->groupBy('d');

        $scope($q);

        $out = [];
        foreach ($q->toBase()->get() as $row) {
            $out[(string) $row->d] = (int) $row->c;
        }

        return $out;
    }
}
