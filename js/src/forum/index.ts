import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import PostsUserPage from 'flarum/forum/components/PostsUserPage';
import LinkButton from 'flarum/common/components/LinkButton';
import CalendarPage from './components/CalendarPage';
import UpcomingEvents from './components/UpcomingEvents';
import ActivityHeatmap from './components/ActivityHeatmap';
import PulseWidget from './components/PulseWidget';
import { registerIntegrations } from './integrations';

declare const m: any;

/**
 * Forum entry point.
 *  - Registers the /calendar route + a nav link.
 *  - On stock Flarum, mounts the "Upcoming events" widget in the index sidebar
 *    (no theme required). Bespoke / Page Builder get a proper widget/block via
 *    registerIntegrations().
 */
app.initializers.add('ernestdefoe/calendar', () => {
  app.routes.calendar = { path: '/calendar', component: CalendarPage };

  // Nav link sits with "All Discussions" in the sidebar navigation (Flarum 2
  // exposes these via navItems, not items — items is the New Discussion button +
  // the nav dropdown itself).
  extend(IndexSidebar.prototype, 'navItems', function (items: any) {
    items.add(
      'calendar',
      LinkButton.component(
        { href: app.route('calendar'), icon: 'fas fa-calendar-days' },
        app.translator.trans('ernestdefoe-calendar.forum.nav')
      ),
      5
    );
  });

  // Stock-theme widgets, each its own sidebar block below the navigation.
  extend(IndexSidebar.prototype, 'items', function (items: any) {
    if (app.forum.attribute('ernestdefoe-calendar.showIndexWidget')) {
      const count = app.forum.attribute('ernestdefoe-calendar.indexWidgetCount') || 5;
      items.add('calendar-upcoming', m(UpcomingEvents, { count }), -10);
    }
    if (app.forum.attribute('ernestdefoe-calendar.showPulseWidget')) {
      items.add('calendar-pulse', m(PulseWidget, { count: 5 }), -12);
    }
  });

  // The marquee feature: a contribution heatmap + streaks at the top of every
  // member's profile. override() lets us prepend above the activity feed.
  override(PostsUserPage.prototype, 'content', function (this: any, original: any) {
    const heatmap = this.user ? m('.PostsUserPage-heatmap', m(ActivityHeatmap, { userId: this.user.id() })) : null;
    return [heatmap, original()];
  });

  // Defer so sibling extensions (Bespoke / Page Builder) have run their own
  // initializers and created app.bespoke / app.pageBuilder before we register our
  // widgets into them — cross-extension initializer order isn't guaranteed, and a
  // macrotask runs after Flarum's synchronous boot completes.
  setTimeout(registerIntegrations, 0);
});

export { default as UpcomingEvents } from './components/UpcomingEvents';
