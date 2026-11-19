<?php

/*
 * This file is part of ernestdefoe/calendar.
 *
 * Calendar & Events for Flarum 2.
 */

namespace ErnestDefoe\Calendar\Api\Resource;

use ErnestDefoe\Calendar\Activity\ActivityRepository;
use ErnestDefoe\Calendar\Widgets\Celebrations;
use ErnestDefoe\Calendar\Widgets\Memories;
use ErnestDefoe\Calendar\Widgets\Pulse;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractResource;
use Laminas\Diactoros\Response\JsonResponse;

/**
 * The sidebar and profile widgets: `/api/calendar-widgets/{pulse,memories,
 * celebrations,activity}`. Each is a rollup (counts per day, a short list),
 * not a set of resources, so each endpoint answers `{"data": …}` in the
 * widget's own shape, and takes its options as `filter[…]`. All of them need
 * permission to view the forum.
 */
class CalendarWidgetResource extends AbstractResource
{
    public function __construct(
        private Pulse $pulse,
        private Memories $memories,
        private Celebrations $celebrations,
        private ActivityRepository $activity,
    ) {
    }

    public function type(): string
    {
        return 'calendar-widgets';
    }

    public function endpoints(): array
    {
        return [
            $this->widget('pulse', fn (Context $context) => $this->pulse->for(
                $context->getActor(),
                self::clamp(self::option($context, 'days'), 14, 365, 120),
                self::clamp(self::option($context, 'leaderDays'), 1, 365, 30),
                self::clamp(self::option($context, 'limit'), 1, 25, 10),
            )),
            $this->widget('memories', fn (Context $context) => $this->memories->for(
                $context->getActor(),
                self::clamp(self::option($context, 'limit'), 1, 20, 6),
            )),
            $this->widget('celebrations', fn () => $this->celebrations->today()),
            $this->widget('activity', fn (Context $context) => $this->userActivity($context)),
        ];
    }

    private function widget(string $name, \Closure $data): Endpoint\Endpoint
    {
        return Endpoint\Endpoint::make($name)
            ->route('GET', '/'.$name)
            ->action(function (Context $context) use ($data) {
                // A forum guests can't view keeps its calendar from them too.
                $context->getActor()->assertCan('viewForum');

                return $data($context);
            })
            ->response(fn (Context $context, array $data) => new JsonResponse(['data' => $data]));
    }

    /** One member's comments per day over the last year, with their streaks. */
    private function userActivity(Context $context): array
    {
        $userId = (int) self::option($context, 'user');
        $daily = $this->activity->userDaily($context->getActor(), $userId, 365);

        return [
            'userId' => $userId,
            'days' => (object) $daily,   // { 'Y-m-d': count }
            'streak' => $this->activity->streak($daily),
            'total' => array_sum($daily),
            'activeDays' => count($daily),
            'max' => $daily ? max($daily) : 0, // peak day, for color scaling
        ];
    }

    private static function option(Context $context, string $name): mixed
    {
        $filters = $context->queryParam('filter');

        return is_array($filters) ? ($filters[$name] ?? null) : null;
    }

    private static function clamp(mixed $value, int $min, int $max, int $default): int
    {
        return max($min, min($max, (int) ($value ?? $default)));
    }
}
