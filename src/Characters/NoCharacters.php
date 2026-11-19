<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Characters;

/** No character extension: attendees are plain avatars and names. */
class NoCharacters implements CharacterSource
{
    public function forUsers(array $userIds): array
    {
        return [];
    }
}
