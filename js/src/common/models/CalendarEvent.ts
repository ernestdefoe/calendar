import Model from 'flarum/common/Model';
import type User from 'flarum/common/models/User';
import type CalendarCategory from './CalendarCategory';
import type { CalEvent } from '../api';

export interface EventOccurrence {
  occurrence: string | null;
  start: string;
  end: string | null;
  googleUrl: string;
}

/** A calendar event series (`calendar-events`), as the store keeps it. api.ts turns it into the plain CalEvent the views use. */
export default class CalendarEvent extends Model {
  title = Model.attribute<string>('title');
  slug = Model.attribute<string>('slug');
  description = Model.attribute<string | null>('description');
  start = Model.attribute<string>('start');
  end = Model.attribute<string | null>('end');
  allDay = Model.attribute<boolean>('allDay');
  timezone = Model.attribute<string>('timezone');
  location = Model.attribute<string | null>('location');
  url = Model.attribute<string | null>('url');
  coverUrl = Model.attribute<string | null>('coverUrl');
  rrule = Model.attribute<string | null>('rrule');
  categoryId = Model.attribute<number | null>('categoryId');
  recurring = Model.attribute<boolean>('recurring');
  isPublished = Model.attribute<boolean>('isPublished');
  discussionId = Model.attribute<number | null>('discussionId');
  canEdit = Model.attribute<boolean>('canEdit');
  icalUrl = Model.attribute<string>('icalUrl');
  googleUrl = Model.attribute<string>('googleUrl');
  /** Only on list responses: each occurrence inside the requested window. */
  occurrences = Model.attribute<EventOccurrence[] | undefined>('occurrences');
  rsvp = Model.attribute<CalEvent['rsvp']>('rsvp');

  user = Model.hasOne<User | null>('user');
  category = Model.hasOne<CalendarCategory | null>('category');
}
