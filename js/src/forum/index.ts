import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LinkButton from 'flarum/common/components/LinkButton';
import CalendarPage from './components/CalendarPage';
import UpcomingEvents from './components/UpcomingEvents';
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

  extend(IndexSidebar.prototype, 'items', function (items: any) {
    items.add(
      'calendar',
      LinkButton.component(
        { href: app.route('calendar'), icon: 'fas fa-calendar-days' },
        app.translator.trans('ernestdefoe-calendar.forum.nav')
      ),
      5
    );

    // Stock-theme upcoming-events widget in the index sidebar.
    if (app.forum.attribute('ernestdefoe-calendar.showIndexWidget')) {
      const count = app.forum.attribute('ernestdefoe-calendar.indexWidgetCount') || 5;
      items.add('calendar-upcoming', m(UpcomingEvents, { count }), -10);
    }
  });

  registerIntegrations();
});

export { default as UpcomingEvents } from './components/UpcomingEvents';
