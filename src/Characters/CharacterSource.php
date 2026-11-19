<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Characters;

/** The game character shown beside each attendee, when an extension supplies one. */
interface CharacterSource
{
    /**
     * Each user's best character, keyed by user id.
     *
     * @param int[] $userIds
     * @return array<int, array{name: string, class: ?string, spec: ?string, itemLevel: ?int, level: ?int}>
     */
    public function forUsers(array $userIds): array;
}
