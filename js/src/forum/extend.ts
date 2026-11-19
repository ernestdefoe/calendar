import Extend from 'flarum/common/extenders';
import commonExtend from '../common/extend';

/**
 * Declarative forum extenders. The calendar page is a lazy chunk: its code is
 * only fetched when someone opens /calendar.
 */
export default [...commonExtend, new Extend.Routes().add('calendar', '/calendar', () => import('./components/CalendarPage'))];
