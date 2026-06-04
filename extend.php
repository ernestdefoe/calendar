<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

use Flarum\Extend;
use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Api\Resource\ForumResource;
use Flarum\Extension\ExtensionManager;
use ErnestDefoe\Calendar\Api\Controller;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less')
        ->route('/calendar', 'calendar'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    (new Extend\Locales(__DIR__ . '/resources/locale')),

    // ---- Read/write JSON API (custom controllers) ----
    (new Extend\Routes('api'))
        ->get('/calendar/events', 'calendar.events.list', Controller\ListEventsController::class)
        ->get('/calendar/events/{id}', 'calendar.events.show', Controller\ShowEventController::class)
        ->post('/calendar/events', 'calendar.events.create', Controller\CreateEventController::class)
        ->patch('/calendar/events/{id}', 'calendar.events.update', Controller\UpdateEventController::class)
        ->delete('/calendar/events/{id}', 'calendar.events.delete', Controller\DeleteEventController::class)
        ->post('/calendar/events/{id}/rsvp', 'calendar.events.rsvp', Controller\RsvpController::class)
        ->get('/calendar/categories', 'calendar.categories.list', Controller\ListCategoriesController::class)
        ->post('/calendar/categories', 'calendar.categories.create', Controller\SaveCategoryController::class)
        ->patch('/calendar/categories/{id}', 'calendar.categories.update', Controller\SaveCategoryController::class)
        ->delete('/calendar/categories/{id}', 'calendar.categories.delete', Controller\DeleteCategoryController::class)
        // ---- Engagement: activity heatmap / streaks / forum pulse ----
        ->get('/calendar/activity/{id}', 'calendar.activity.user', Controller\UserActivityController::class)
        ->get('/calendar/pulse', 'calendar.activity.pulse', Controller\PulseController::class),

    // ---- iCal export (served as text/calendar for direct download / subscription) ----
    (new Extend\Routes('forum'))
        ->get('/calendar/feed.ics', 'calendar.ical.feed', Controller\IcalFeedController::class)
        ->get('/calendar/events/{id}/ical', 'calendar.ical.event', Controller\IcalEventController::class),

    // ---- Expose settings + the create permission to the forum payload ----
    (new Extend\Settings())
        ->serializeToForum('ernestdefoe-calendar.defaultView', 'ernestdefoe-calendar.default_view', fn ($v) => $v ?: 'month')
        ->serializeToForum('ernestdefoe-calendar.weekStartsOn', 'ernestdefoe-calendar.week_starts_on', fn ($v) => (int) ($v ?? 0))
        ->serializeToForum('ernestdefoe-calendar.showIndexWidget', 'ernestdefoe-calendar.show_index_widget', fn ($v) => (bool) $v)
        ->serializeToForum('ernestdefoe-calendar.indexWidgetCount', 'ernestdefoe-calendar.index_widget_count', fn ($v) => (int) ($v ?: 5))
        ->serializeToForum('ernestdefoe-calendar.linkDiscussion', 'ernestdefoe-calendar.link_discussion', fn ($v) => (bool) $v)
        ->serializeToForum('ernestdefoe-calendar.showPulseWidget', 'ernestdefoe-calendar.show_pulse_widget', fn ($v) => $v === null ? true : filter_var($v, FILTER_VALIDATE_BOOLEAN)),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canCreateEvent')
                ->get(fn ($model, Context $context) => $context->getActor()->can('calendar.create')),
            Schema\Boolean::make('canManageCalendar')
                ->get(fn ($model, Context $context) => $context->getActor()->can('calendar.manage')),
            // True when FoF Upload is installed + enabled AND the actor may upload,
            // so the event form can offer a real file picker for cover images
            // (it always falls back to a plain URL field otherwise).
            Schema\Boolean::make('calendarCoverUploads')
                ->get(fn ($model, Context $context) => resolve(ExtensionManager::class)->isEnabled('fof-upload')
                    && $context->getActor()->hasPermission('fof-upload.upload')),
        ]),

    // ---- Live countdowns: a [countdown] BBCode usable in any post ----
    // The formatter emits a placeholder span; countdowns.ts turns it into a live
    // ticking timer client-side. Server output is cached HTML, so the clock has
    // to run on the client. `[countdown=2026-12-31T23:59:59]New Year[/countdown]`.
    (new Extend\Formatter())
        ->configure(function ($config) {
            $config->BBCodes->addCustom(
                '[COUNTDOWN={TEXT1}]{TEXT2}[/COUNTDOWN]',
                '<span class="CalCountdown" data-deadline="{TEXT1}">{TEXT2}</span>'
            );
        }),
];
