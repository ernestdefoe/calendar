<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Characters;

use Flarum\Foundation\AbstractServiceProvider;

/** Attendees have no game characters, unless an extension's provider binds a source. */
class CharactersServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(CharacterSource::class, NoCharacters::class);
    }
}
