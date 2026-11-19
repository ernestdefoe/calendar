import Extend from 'flarum/common/extenders';
import CalendarEvent from './models/CalendarEvent';
import CalendarCategory from './models/CalendarCategory';

/** Extenders shared by the forum and admin frontends. */
export default [new Extend.Store().add('calendar-events', CalendarEvent).add('calendar-categories', CalendarCategory)];
