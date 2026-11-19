<?php

use ErnestDefoe\Calendar\SettingIds;
use Illuminate\Database\Schema\Builder;

/*
 * The group and tag pickers save a JSON list. Rewrite a single id or a
 * comma-separated value saved some other way as that list, so every reader
 * and the admin checkboxes see the same thing.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        $keys = [
            'ernestdefoe-calendar.pulse_exclude_groups',
            'ernestdefoe-calendar.event_discussion_tags',
            'ernestdefoe-calendar.onthisday_exclude_tags',
        ];

        foreach ($db->table('settings')->whereIn('key', $keys)->get() as $row) {
            if (! is_array(json_decode((string) $row->value, true))) {
                $db->table('settings')->where('key', $row->key)->update(['value' => json_encode(SettingIds::from($row->value))]);
            }
        }
    },
    // Back to the comma-separated form older versions wrote.
    'down' => function (Builder $schema) {
        $db = $schema->getConnection();
        $keys = [
            'ernestdefoe-calendar.pulse_exclude_groups',
            'ernestdefoe-calendar.event_discussion_tags',
            'ernestdefoe-calendar.onthisday_exclude_tags',
        ];

        foreach ($db->table('settings')->whereIn('key', $keys)->get() as $row) {
            $ids = json_decode((string) $row->value, true);
            if (is_array($ids)) {
                $db->table('settings')->where('key', $row->key)->update(['value' => implode(',', $ids)]);
            }
        }
    },
];
