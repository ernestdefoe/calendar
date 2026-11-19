<?php

namespace ErnestDefoe\Calendar;

use ErnestDefoe\Calendar\Characters\CharacterSource;
use Flarum\User\User;

/**
 * The attendee lists for an event's RSVP panel: who's going / interested,
 * enriched with each member's main WoW character (class, spec, item level)
 * when the ernestdefoe/armory extension is installed — so a raid signup
 * doubles as a composition sheet. Armory is a soft dependency: without it
 * enabled the lists are plain avatars + names.
 */
class Attendees
{
    public const LIMIT = 60;

    public function __construct(
        private CharacterSource $characters,
        private BasicUser $basicUser,
    ) {
    }

    public function build(int $eventId): array
    {
        $rsvps = EventRsvp::query()
            ->where('event_id', $eventId)
            ->orderBy('created_at')
            ->limit(self::LIMIT)
            ->get(['user_id', 'status']);

        $userIds = $rsvps->pluck('user_id')->all();

        // 🚨 The users have to come back as MODELS, not as joined columns. Both
        // fields we want are driver-computed accessors: users.avatar_url stores a
        // bare filename ("MkvOdb….webp") that only User::getAvatarUrlAttribute()
        // turns into a URL — and which the avatar driver also fills in for members
        // who never uploaded one — while display_name is not a column at all.
        // Reading the raw columns shipped filenames to the browser as <img src>,
        // so every attendee rendered as a broken image.
        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        $characters = $this->characters->forUsers($userIds);

        $out = ['going' => [], 'interested' => []];
        foreach ($rsvps as $row) {
            $user = $users->get($row->user_id);

            if (! $user) {
                continue; // the member was deleted between the RSVP and now
            }

            $bucket = $row->status === 'interested' ? 'interested' : 'going';
            $out[$bucket][] = [
                'id' => (int) $user->id,
                ...$this->basicUser->of($user),
                'character' => $characters[$user->id] ?? null,
            ];
        }

        return $out;
    }
}
