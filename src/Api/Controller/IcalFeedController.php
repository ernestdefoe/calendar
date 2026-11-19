<?php

namespace ErnestDefoe\Calendar\Api\Controller;

use Carbon\Carbon;
use ErnestDefoe\Calendar\Event;
use ErnestDefoe\Calendar\LocationSetting;
use ErnestDefoe\Calendar\Ical\IcalGenerator;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /calendar/feed.ics — the whole calendar as a subscribable iCal feed.
 *
 * Recurring events are emitted once with their RRULE (the subscriber's client
 * expands them). Everything not over before last month is included, so a fresh
 * subscription shows recent + upcoming items.
 */
class IcalFeedController implements RequestHandlerInterface
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected LocationSetting $location,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // A forum guests can't view keeps its calendar from them too.
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('viewForum');

        // The 2000-row ceiling bounds peak memory for forums with long event
        // histories; IcalGenerator reads only the events' own columns.
        $events = Event::query()
            ->whereVisibleTo($actor)
            ->where('is_published', true)
            // Anything not over before last month: series still repeating included.
            ->where(fn ($q) => $q->whereNull('finishes_at')->orWhere('finishes_at', '>=', Carbon::now()->subMonth()))
            ->orderBy('start_at')
            ->limit(2000)
            ->get();

        $name = (string) $this->settings->get('forum_title');
        $host = $request->getUri()->getHost() ?: 'localhost';

        $ics = (new IcalGenerator($host, $this->location->enabled()))->calendar($events, $name.' — Calendar');

        $response = new Response();
        $response->getBody()->write($ics);

        return $response
            ->withHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->withHeader('Content-Disposition', 'inline; filename="calendar.ics"');
    }
}
