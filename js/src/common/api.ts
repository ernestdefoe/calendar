import app from 'flarum/common/app';
import { invalidate } from './widgetData';
import type { BasicUser } from './users';
import type Model from 'flarum/common/Model';
import type { ApiPayloadSingle } from 'flarum/common/Store';
import type CalendarEvent from './models/CalendarEvent';
import type CalendarCategory from './models/CalendarCategory';

/** An event as the views use it: one occurrence of a calendar-events resource (EventResource). */
export interface CalEvent {
  id: number;
  title: string;
  slug: string;
  description?: string | null;
  start: string;
  end?: string | null;
  allDay: boolean;
  timezone: string;
  location?: string | null;
  url?: string | null;
  coverUrl?: string | null;
  rrule?: string | null;
  recurring: boolean;
  occurrence?: string | null;
  category?: CalCategory | null;
  author?: { id: number; username: string; displayName: string; avatarUrl: string } | null;
  discussionId?: number | null;
  rsvp: { going: number; interested: number; mine: string | null; attendees?: RsvpAttendees };
  canEdit: boolean;
  icalUrl: string;
  googleUrl: string;
}

export interface RsvpAttendee extends BasicUser {
  id: number;
  /** Main WoW character from ernestdefoe/armory, when installed. */
  character?: { name: string; class: string | null; spec: string | null; itemLevel: number | null; level: number | null } | null;
}

export interface RsvpAttendees {
  going: RsvpAttendee[];
  interested: RsvpAttendee[];
}

export interface CalCategory {
  id: number;
  name: string;
  slug: string;
  color: string;
  /** RSVP buttons on (the default) or off for this category's events. */
  rsvp?: boolean;
  goingLabel?: string | null;
  interestedLabel?: string | null;
}

export interface UserActivity {
  userId: number;
  days: Record<string, number>;
  streak: { current: number; longest: number };
  total: number;
  activeDays: number;
  max: number;
}

export interface PulseLeader extends BasicUser {
  userId: number;
  count: number;
}

export interface ForumPulse {
  days: Record<string, number>;
  total: number;
  max: number;
  leaders: PulseLeader[];
  leaderDays: number;
}

export interface Memory {
  id: number;
  title: string;
  slug: string;
  createdAt: string;
  yearsAgo: number;
  commentCount: number;
  participantCount: number;
}

export interface Celebrant extends BasicUser {
  userId: number;
  type: 'birthday' | 'anniversary';
  years?: number;
}

const api = (path: string) => app.forum.attribute('apiUrl') + path;
export const base = () => app.forum.attribute<string>('baseUrl');

/** A store model's attributes, copied, so the views get the same plain object the resource sent. */
const attrsOf = (model: Model): Record<string, any> => ({ ...(model.data.attributes || {}) });

const toCategory = (model: CalendarCategory): CalCategory => ({ ...(attrsOf(model) as Omit<CalCategory, 'id'>), id: Number(model.id()) });

/** Turn one calendar-events model into the CalEvent the views use. */
function toEvent(model: CalendarEvent): CalEvent {
  const { occurrences, ...attrs } = attrsOf(model);
  const user = model.user();
  const category = model.category();

  return {
    ...(attrs as Omit<CalEvent, 'id' | 'category' | 'author' | 'occurrence'>),
    id: Number(model.id()),
    occurrence: null,
    category: category ? toCategory(category) : null,
    author: user
      ? {
          id: Number(user.id()),
          username: user.attribute<string>('username'),
          displayName: user.attribute<string>('displayName'),
          avatarUrl: user.attribute<string>('avatarUrl'),
        }
      : null,
  };
}

/** A listed series becomes one CalEvent per occurrence inside the window, sorted by start. */
function toOccurrences(models: CalendarEvent[]): CalEvent[] {
  const events: CalEvent[] = [];

  models.forEach((model) => {
    const event = toEvent(model);
    (model.occurrences() || []).forEach((o) => {
      events.push({ ...event, occurrence: o.occurrence, start: o.start, end: o.end, googleUrl: o.googleUrl });
    });
  });

  return events.sort((a, b) => a.start.localeCompare(b.start));
}

/** The store's copy of a record we only know by id (pushed as existing, so save() PATCHes and delete() DELETEs). */
function known<M extends Model>(type: string, id: number): M {
  return app.store.getById<M>(type, String(id)) ?? app.store.pushObject<M>({ type, id: String(id), attributes: {} })!;
}

const widget = <T>(name: string, query: Record<string, string | number | undefined> = {}): Promise<T> => {
  const qs = new URLSearchParams();
  Object.entries(query).forEach(([k, v]) => {
    if (v !== undefined) qs.set('filter[' + k + ']', String(v));
  });
  const s = qs.toString();
  return app.request<any>({ method: 'GET', url: api('/calendar-widgets/' + name + (s ? '?' + s : '')) }).then((r: any) => r.data);
};

export function onThisDay(limit = 6): Promise<Memory[]> {
  return widget('memories', { limit });
}

export function celebrations(): Promise<Celebrant[]> {
  return widget('celebrations');
}

/** Absolute URL for a server path the API handed us (e.g. an .ics path). */
export const abs = (path: string) => base().replace(/\/$/, '') + '/' + path.replace(/^\//, '');
export const feedUrl = () => abs('calendar/feed.ics');

export function userActivity(userId: number): Promise<UserActivity> {
  return widget('activity', { user: userId });
}

export function forumPulse(opts: { days?: number; leaderDays?: number; limit?: number } = {}): Promise<ForumPulse> {
  return widget('pulse', opts);
}

export function listEvents(from: Date, to: Date, category?: string): Promise<{ data: CalEvent[]; categories: CalCategory[] }> {
  const filter: Record<string, string> = { from: from.toISOString(), to: to.toISOString() };
  if (category) filter.category = category;

  // The store passes the filter as request params, which Mithril encodes — so the ':' in the ISO dates is safe.
  return Promise.all([app.store.find<CalendarEvent[]>('calendar-events', { filter }), listCategories()]).then(([models, categories]) => ({
    data: toOccurrences(models),
    categories,
  }));
}

export function showEvent(id: number): Promise<CalEvent> {
  return app.store.find<CalendarEvent>('calendar-events', String(id)).then(toEvent);
}

export function saveEvent(attrs: Record<string, unknown>, id?: number): Promise<CalEvent> {
  const model = id ? known<CalendarEvent>('calendar-events', id) : app.store.createRecord<CalendarEvent>('calendar-events');

  return model.save(attrs).then((saved) => {
    invalidate('events:');
    return toEvent(saved);
  });
}

/** POST to one of an event's own endpoints (rsvp, discussion) and store the event it answers with. */
function eventAction(id: number, action: string, body?: unknown): Promise<CalendarEvent> {
  return app
    .request<ApiPayloadSingle>({ method: 'POST', url: api('/calendar-events/' + id + '/' + action), ...(body ? { body } : {}) })
    .then((payload) => {
      invalidate('events:');
      return app.store.pushPayload<CalendarEvent>(payload);
    });
}

/** Start the comment thread for an event that has none. */
export function startDiscussion(id: number): Promise<CalEvent> {
  return eventAction(id, 'discussion').then(toEvent);
}

export function deleteEvent(id: number): Promise<unknown> {
  return known<CalendarEvent>('calendar-events', id)
    .delete()
    .then((r) => {
      invalidate('events:');
      return r;
    });
}

export function rsvp(id: number, status: string): Promise<CalEvent['rsvp']> {
  return eventAction(id, 'rsvp', { data: { attributes: { status } } }).then((model) => model.rsvp());
}

export function listCategories(): Promise<CalCategory[]> {
  return app.store.find<CalendarCategory[]>('calendar-categories').then((models) => models.map(toCategory));
}

export function saveCategory(attrs: Record<string, unknown>, id?: number): Promise<CalCategory> {
  const model = id ? known<CalendarCategory>('calendar-categories', id) : app.store.createRecord<CalendarCategory>('calendar-categories');

  return model.save(attrs).then(toCategory);
}

export function deleteCategory(id: number): Promise<unknown> {
  return known<CalendarCategory>('calendar-categories', id).delete();
}
