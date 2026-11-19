<?php

namespace ErnestDefoe\Calendar\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\Calendar\Tests\integration\CalendarApi;
use Flarum\Discussion\Discussion;
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;
use PHPUnit\Framework\Attributes\Test;

/**
 * The pulse and a member's activity count only comments the viewer could
 * read: a post in a restricted tag adds nothing for someone outside it.
 */
class WidgetVisibilityTest extends TestCase
{
    use CalendarApi;
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'ernestdefoe-calendar');

        $today = Carbon::now()->subHour();
        $post = fn (int $id, int $discussion) => [
            'id' => $id, 'discussion_id' => $discussion, 'number' => 1, 'user_id' => 2,
            'type' => 'comment', 'content' => '<t><p>Post</p></t>', 'created_at' => $today,
        ];

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'staff', 'email' => 'staff@machine.local', 'is_email_confirmed' => 1],
            ],
            Group::class => [['id' => 5, 'name_singular' => 'Staff', 'name_plural' => 'Staff']],
            'group_user' => [['user_id' => 3, 'group_id' => 5]],
            Tag::class => [
                ['id' => 1, 'name' => 'General', 'slug' => 'general', 'position' => 0],
                ['id' => 2, 'name' => 'Staff room', 'slug' => 'staff', 'position' => 1, 'is_restricted' => true],
            ],
            'group_permission' => [['permission' => 'tag2.viewForum', 'group_id' => 5]],
            Discussion::class => [
                ['id' => 1, 'title' => 'Open', 'created_at' => $today, 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1],
                ['id' => 2, 'title' => 'Staff only', 'created_at' => $today, 'user_id' => 2, 'first_post_id' => 2, 'comment_count' => 1],
            ],
            Post::class => [$post(1, 1), $post(2, 2)],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 2, 'tag_id' => 2],
            ],
        ]);
    }

    private function widget(string $path, ?int $actor): array
    {
        $this->app()->getContainer()->make(Repository::class)->flush();
        $response = $this->send($this->calendarRequest('GET', $path, $actor ? ['authenticatedAs' => $actor] : []));
        $this->assertSame(200, $response->getStatusCode());

        return json_decode((string) $response->getBody(), true)['data'];
    }

    #[Test]
    public function the_pulse_counts_only_posts_the_viewer_can_see()
    {
        $this->assertSame(1, $this->widget('/api/calendar-widgets/pulse', null)['total'], 'A guest sees the open post only');
        $this->assertSame(2, $this->widget('/api/calendar-widgets/pulse', 3)['total'], 'Staff see both');
        $this->assertSame(1, $this->widget('/api/calendar-widgets/pulse', null)['total'], 'Staff\'s count is never served to a guest');
    }

    #[Test]
    public function a_members_activity_counts_only_posts_the_viewer_can_see()
    {
        $this->assertSame(1, $this->widget('/api/calendar-widgets/activity?filter[user]=2', null)['total']);
        $this->assertSame(2, $this->widget('/api/calendar-widgets/activity?filter[user]=2', 3)['total']);
    }
}
