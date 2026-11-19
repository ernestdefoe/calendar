<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar;

use Flarum\Extension\ExtensionManager;

/**
 * Which of the optional extensions Calendar works with are enabled right now.
 * These change what a request does (a tag on an event thread, the markup a
 * cover image is written in), not what Calendar registers, so they are asked
 * at request time; anything Calendar registers for another extension sits in
 * Extend\Conditional in extend.php instead. Every id is listed in
 * composer.json's optional-dependencies.
 */
class Integrations
{
    public function __construct(private ExtensionManager $extensions)
    {
    }

    public function tags(): bool
    {
        return $this->enabled('flarum-tags');
    }

    public function markdown(): bool
    {
        return $this->enabled('flarum-markdown');
    }

    public function bbcode(): bool
    {
        return $this->enabled('flarum-bbcode');
    }

    public function uploads(): bool
    {
        return $this->enabled('fof-upload');
    }

    private function enabled(string $id): bool
    {
        return $this->extensions->isEnabled($id);
    }
}
