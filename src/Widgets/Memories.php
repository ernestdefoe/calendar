<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Widgets;

use Carbon\Carbon;
use ErnestDefoe\Calendar\SettingIds;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;

/** "On this day": discussions started on today's date in earlier years. */
class Memories
{
    public function __construct(
        private Repository $cache,
        private SettingsRepositoryInterface $settings,
        private ExtensionManager $extensions,
    ) {
    }

    public function for(User $viewer, int $limit): array
    {
        $now = Carbon::now();

        /*
         * 🚨 The date match is cached, the rest is not. MONTH()/DAY() on
         * created_at cannot use an index, so matching it was a scan of the
         * discussions table for every visitor on every index view (the widget
         * is on by default). Which discussions were started on this date in
         * earlier years cannot change during the day, so that id list is
         * computed once a day; visibility, hiding and the comment-count order
         * are still applied live, per visitor, below.
         */
        $ids = $this->cache->remember(
            'ernestdefoe-calendar.onthisday.'.$now->toDateString(),
            3600,
            fn () => Discussion::query()
                ->whereMonth('created_at', $now->month)
                ->whereDay('created_at', $now->day)
                ->whereYear('created_at', '<', $now->year)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
        );

        if (! $ids) {
            return [];
        }

        return $this->visible($viewer, $ids, $limit)->map(fn (Discussion $d) => [
            'id' => (int) $d->id,
            'title' => $d->title,
            'slug' => $d->slug,
            'createdAt' => $d->created_at?->toIso8601String(),
            'yearsAgo' => $d->created_at ? $now->year - $d->created_at->year : null,
            'commentCount' => (int) $d->comment_count,
            'participantCount' => (int) $d->participant_count,
        ])->values()->all();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Discussion> */
    private function visible(User $viewer, array $ids, int $limit): \Illuminate\Database\Eloquent\Collection
    {
        $query = Discussion::whereVisibleTo($viewer)->whereIn('discussions.id', $ids);

        /*
         * Tags the admin left out: typically a read-only one (announcements, an
         * archive), where a memory invites a reply nobody can post. Only with
         * flarum/tags enabled: without it the pivot table may not exist.
         */
        $excludedTags = SettingIds::from($this->settings->get('ernestdefoe-calendar.onthisday_exclude_tags'));

        if ($excludedTags && $this->extensions->isEnabled('flarum-tags')) {
            $query->whereDoesntHave('tags', fn ($q) => $q->whereIn('tags.id', $excludedTags));
        }

        return $query
            ->whereNull('hidden_at')
            ->where('is_private', false)
            ->orderByDesc('comment_count')
            // Ties newest first: what the unordered scan happened to return,
            // made deterministic now that the plan goes by id.
            ->orderByDesc('discussions.id')
            ->limit($limit)
            ->get();
    }
}
