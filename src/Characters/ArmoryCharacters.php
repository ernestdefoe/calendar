<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Characters;

use ErnestDefoe\Armory\ArmoryCharacter;

/**
 * Each attendee's main WoW character from ernestdefoe/armory, so a raid
 * signup doubles as a composition sheet. Bound only while Armory is enabled.
 */
class ArmoryCharacters implements CharacterSource
{
    public function forUsers(array $userIds): array
    {
        if (! $userIds) {
            return [];
        }

        $rows = ArmoryCharacter::query()
            ->whereIn('user_id', $userIds)
            ->where('is_visible', true)
            ->orderByDesc('is_main')
            ->orderByDesc('item_level')
            ->get();

        $best = [];
        foreach ($rows as $c) {
            // Rows come best-first, so each user's first one wins.
            $best[$c->user_id] ??= [
                'name' => (string) $c->name,
                'class' => $c->class ?: null,
                'spec' => $c->spec ?: null,
                'itemLevel' => $c->item_level ?: null,
                'level' => $c->level ? (int) $c->level : null,
            ];
        }

        return $best;
    }
}
