<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Api\Resource;

use Tobyz\JsonApiServer\Context;

/**
 * Options arrive as `filter[name]`: JSON:API refuses an all-lowercase query
 * parameter of its own (`?from=`), so `filter` is the family for them.
 */
trait ReadsFilters
{
    protected static function filterValue(Context $context, string $name): mixed
    {
        $filters = $context->queryParam('filter');

        return is_array($filters) ? ($filters[$name] ?? null) : null;
    }
}
