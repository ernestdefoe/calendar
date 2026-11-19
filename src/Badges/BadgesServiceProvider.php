<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Badges;

use Flarum\Foundation\AbstractServiceProvider;
use FoF\Badges\Trigger\MetricManager;

/**
 * Adds Calendar's two metrics to FoF Badges' trigger builder.
 *
 * Registered only while fof/badges is enabled (Extend\Conditional in
 * extend.php), so its classes are always there when this runs.
 */
class BadgesServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->resolving(MetricManager::class, function (MetricManager $metrics, $container) {
            $metrics->register($container->make(EventsAttendedMetric::class));
            $metrics->register($container->make(EventsOrganizedMetric::class));
        });
    }
}
