<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Tests\integration;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Helpers for the calendar's JSON:API resources. calendarRequest() turns a
 * query string in the path into query parameters and fills in the resource
 * type (and id) that JSON:API wants in a write. flatten() reads a response the
 * way the frontend's api.ts does: attributes at the top level, the author and
 * category from `included`, and one row per occurrence in a list.
 */
trait CalendarApi
{
    protected function calendarRequest(string $method, string $path, array $options = []): ServerRequestInterface
    {
        [$path, $query] = array_pad(explode('?', $path, 2), 2, '');

        if (isset($options['json']['data']) && preg_match('#^/api/(calendar-events|calendar-categories)(?:/(\d+))?$#', $path, $m)) {
            $options['json']['data'] += ['type' => $m[1]] + (isset($m[2]) ? ['id' => $m[2]] : []);
        }

        $request = $this->request($method, $path, $options);
        parse_str($query, $params);

        return $params ? $request->withQueryParams($params + $request->getQueryParams()) : $request;
    }

    protected function flatten(?array $document): ?array
    {
        if (! isset($document['data']) || ! is_array($document['data'])) {
            return $document;
        }

        $included = $document['included'] ?? [];
        $data = $document['data'];

        if (array_is_list($data) && str_starts_with((string) ($data[0]['type'] ?? ''), 'calendar-')) {
            $rows = [];
            foreach ($data as $resource) {
                $row = $this->flattenResource($resource, $included);
                foreach ($resource['attributes']['occurrences'] ?? [null] as $occurrence) {
                    $rows[] = $occurrence ? array_merge($row, $occurrence) : $row;
                }
            }
            usort($rows, fn ($a, $b) => strcmp((string) ($a['start'] ?? ''), (string) ($b['start'] ?? '')));
            $document['data'] = $rows;
        } elseif (str_starts_with((string) ($data['type'] ?? ''), 'calendar-')) {
            $document['data'] = $this->flattenResource($data, $included);
        }

        return $document;
    }

    private function flattenResource(array $resource, array $included): array
    {
        $row = ['id' => (int) $resource['id']] + $resource['attributes'];
        unset($row['occurrences']);

        foreach (['user' => 'author', 'category' => 'category'] as $relationship => $key) {
            if (! array_key_exists($relationship, $resource['relationships'] ?? [])) {
                continue;
            }
            $ref = $resource['relationships'][$relationship]['data'];
            $related = $ref ? current(array_filter($included, fn ($i) => $i['type'] === $ref['type'] && $i['id'] === $ref['id'])) : null;
            $row[$key] = $related ? ['id' => (int) $related['id']] + $related['attributes'] : null;
        }

        return $row;
    }
}
