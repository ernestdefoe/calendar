<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\PageBuilder;

use Ernestdefoe\PageBuilder\Block\AbstractBlock;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A Page Builder block for one of Calendar's widgets. This is the server half:
 * the palette entry (type, name, icon, settings). The client half, registered
 * in js/src/forum/integrations.ts, fetches its own data, so resolve() stays
 * AbstractBlock's empty one. Page Builder builds blocks through the container
 * and only while it is enabled (Extend\Conditional in extend.php), so its
 * AbstractBlock is always there.
 */
abstract class CalendarBlock extends AbstractBlock
{
    public function __construct(protected TranslatorInterface $translator)
    {
    }

    public function name(): string
    {
        return $this->t($this->type().'.name');
    }

    public function category(): string
    {
        return 'forum';
    }

    protected function t(string $key): string
    {
        return $this->translator->trans('ernestdefoe-calendar.admin.page_builder.'.$key);
    }

    /** The heading every block shows, editable per block. */
    protected function titleField(): array
    {
        return ['key' => 'title', 'type' => 'text', 'label' => $this->t('title_label'), 'default' => $this->t($this->type().'.title')];
    }

    protected function countField(int $default, int $max = 20, string $label = 'count_label'): array
    {
        return ['key' => 'count', 'type' => 'range', 'label' => $this->t($label), 'default' => $default, 'min' => 1, 'max' => $max];
    }
}
