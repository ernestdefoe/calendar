<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Characters;

use Flarum\Foundation\AbstractServiceProvider;

/** Registered only while ernestdefoe/armory is enabled (Extend\Conditional in extend.php). */
class ArmoryServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->bind(CharacterSource::class, ArmoryCharacters::class);
    }
}
