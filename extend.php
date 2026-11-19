<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

use ErnestDefoe\Calendar\Access;
use ErnestDefoe\Calendar\Api;
use ErnestDefoe\Calendar\Api\Resource;
use ErnestDefoe\Calendar\Event;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Extend;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        // The calendar page and the event modals are their own chunks, loaded
        // only when opened; this publishes them.
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less')
        ->route('/calendar', 'calendar'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    // ---- JSON:API: events, categories and the widgets ----
    new Extend\ApiResource(Resource\EventResource::class),
    new Extend\ApiResource(Resource\EventCategoryResource::class),
    new Extend\ApiResource(Resource\CalendarWidgetResource::class),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(Api\ForumFields::class),

    // The opt-in birthday (MM-DD only), self-editable.
    (new Extend\ApiResource(UserResource::class))
        ->fields(Api\UserFields::class),

    (new Extend\ModelVisibility(Event::class))
        ->scope(Access\ScopeEventVisibility::class),

    (new Extend\Policy())
        ->modelPolicy(Event::class, Access\EventPolicy::class),

    // ---- iCal export ----
    // Forum routes, not API resources: they answer text/calendar for calendar
    // apps to download and subscribe to, and those subscriptions keep the URL.
    (new Extend\Routes('forum'))
        ->get('/calendar/feed.ics', 'calendar.ical.feed', Api\Controller\IcalFeedController::class)
        ->get('/calendar/events/{id}/ical', 'calendar.ical.event', Api\Controller\IcalEventController::class),

    // ---- Settings the forum frontend reads ----
    (new Extend\Settings())
        ->default('ernestdefoe-calendar.date_order', 'auto')
        ->default('ernestdefoe-calendar.location_mode', 'google')
        ->default('ernestdefoe-calendar.default_view', 'month')
        ->default('ernestdefoe-calendar.week_starts_on', 0)
        ->default('ernestdefoe-calendar.show_index_widget', true)
        ->default('ernestdefoe-calendar.index_widget_count', 5)
        ->default('ernestdefoe-calendar.show_pulse_widget', true)
        ->default('ernestdefoe-calendar.show_memories_widget', true)
        ->default('ernestdefoe-calendar.show_celebrations_widget', true)
        ->serializeToForum('ernestdefoe-calendar.dateOrder', 'ernestdefoe-calendar.date_order', fn ($v) => in_array($v, ['dmy', 'mdy'], true) ? $v : 'auto')
        ->serializeToForum('ernestdefoe-calendar.locationMode', 'ernestdefoe-calendar.location_mode', fn ($v) => in_array($v, ['apple', 'osm', 'text', 'off'], true) ? $v : 'google')
        // Whether one account starts every event thread: the "Start a discussion"
        // button then depends on that account, not on the viewer.
        ->serializeToForum('ernestdefoe-calendar.postsAs', 'ernestdefoe-calendar.event_discussion_author', fn ($v) => trim((string) $v) !== '')
        ->serializeToForum('ernestdefoe-calendar.hideNavLink', 'ernestdefoe-calendar.hide_nav_link', 'boolval')
        ->serializeToForum('ernestdefoe-calendar.defaultView', 'ernestdefoe-calendar.default_view', 'strval')
        ->serializeToForum('ernestdefoe-calendar.weekStartsOn', 'ernestdefoe-calendar.week_starts_on', 'intval')
        ->serializeToForum('ernestdefoe-calendar.showIndexWidget', 'ernestdefoe-calendar.show_index_widget', 'boolval')
        ->serializeToForum('ernestdefoe-calendar.indexWidgetCount', 'ernestdefoe-calendar.index_widget_count', 'intval')
        ->serializeToForum('ernestdefoe-calendar.showPulseWidget', 'ernestdefoe-calendar.show_pulse_widget', 'boolval')
        ->serializeToForum('ernestdefoe-calendar.showMemoriesWidget', 'ernestdefoe-calendar.show_memories_widget', 'boolval')
        ->serializeToForum('ernestdefoe-calendar.showCelebrationsWidget', 'ernestdefoe-calendar.show_celebrations_widget', 'boolval'),

    // "Post event discussions as" is checked when the settings are saved.
    // Each event's comment thread follows the event.
    (new Extend\Event())
        ->listen(\Flarum\Settings\Event\Saving::class, \ErnestDefoe\Calendar\Listener\ValidateDiscussionAuthor::class)
        ->subscribe(\ErnestDefoe\Calendar\Listener\EventThreads::class),

    // ---- Attendees' game characters: none, or Armory's while it is enabled ----
    (new Extend\ServiceProvider())
        ->register(\ErnestDefoe\Calendar\Characters\CharactersServiceProvider::class),
    (new Extend\Conditional())
        ->whenExtensionEnabled('ernestdefoe-armory', fn () => [
            (new Extend\ServiceProvider())
                ->register(\ErnestDefoe\Calendar\Characters\ArmoryServiceProvider::class),
        ]),

    // ---- FoF Badges: two metrics (events attended, events organized), and
    // RSVPs and saved events checked against their triggers ----
    (new Extend\Conditional())
        ->whenExtensionEnabled('fof-badges', fn () => [
            (new Extend\ServiceProvider())
                ->register(\ErnestDefoe\Calendar\Badges\BadgesServiceProvider::class),
            (new Extend\Event())
                ->listen(\ErnestDefoe\Calendar\Events\RsvpChanged::class, \ErnestDefoe\Calendar\Badges\EvaluateCalendarBadges::class)
                ->listen(\ErnestDefoe\Calendar\Events\EventSaved::class, \ErnestDefoe\Calendar\Badges\EvaluateCalendarBadges::class),
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

    // ---- Page Builder blocks (palette) ----
    // The JS render components are registered in integrations.ts. To appear in
    // Page Builder's editor palette a block also needs a server manifest entry,
    // added only while Page Builder is enabled.
    (new Extend\Conditional())
        ->whenExtensionEnabled('ernestdefoe-page-builder', fn () => [
            new \Ernestdefoe\PageBuilder\Extend\PageBuilderBlock(\ErnestDefoe\Calendar\PageBuilder\EventsBlock::class),
            new \Ernestdefoe\PageBuilder\Extend\PageBuilderBlock(\ErnestDefoe\Calendar\PageBuilder\PulseBlock::class),
            new \Ernestdefoe\PageBuilder\Extend\PageBuilderBlock(\ErnestDefoe\Calendar\PageBuilder\MemoriesBlock::class),
            new \Ernestdefoe\PageBuilder\Extend\PageBuilderBlock(\ErnestDefoe\Calendar\PageBuilder\CelebrationsBlock::class),
        ]),
];
