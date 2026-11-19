<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar;

use Flarum\Extension\ExtensionManager;

/**
 * The markup a post is written in depends on which formatter extension is on
 * right now, so it is asked at request time; everything Calendar registers for
 * another extension sits in Extend\Conditional in extend.php instead. Both ids
 * are listed in composer.json's optional-dependencies.
 */
class Integrations
{
    public function __construct(private ExtensionManager $extensions)
    {
    }

    /** An image in the post formatter's markup: Markdown, else BBCode; null when neither is enabled. */
    public function image(string $url): ?string
    {
        if ($this->enabled('flarum-markdown')) {
            return '![]('.$url.')';
        }

        return $this->enabled('flarum-bbcode') ? '[img]'.$url.'[/img]' : null;
    }

    private function enabled(string $id): bool
    {
        return $this->extensions->isEnabled($id);
    }
}
