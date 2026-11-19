import app from 'flarum/common/app';
import { invalidate } from './widgetData';
import type { BasicUser } from './users';

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

/** A JSON:API resource object, as the calendar resources send them. */
interface ApiResource {
  type: string;
  id: string;
  attributes: Record<string, any>;
  relationships?: Record<string, { data: { type: string; id: string } | null }>;
}

interface ApiDocument {
  data: ApiResource | ApiResource[];
  included?: ApiResource[];
}

const toCategory = (r: ApiResource): CalCategory => ({ id: Number(r.id), ...(r.attributes as Omit<CalCategory, 'id'>) });

/** Turn one calendar-events resource into the CalEvent the views use. */
function toEvent(r: ApiResource, included: ApiResource[] = []): CalEvent {
  const find = (rel: string) => {
    const ref = r.relationships?.[rel]?.data;
    return ref ? included.find((i) => i.type === ref.type && i.id === ref.id) : undefined;
  };
  const user = find('user');
  const category = find('category');
  const { occurrences, ...attrs } = r.attributes;

  return {
    ...(attrs as Omit<CalEvent, 'id' | 'category' | 'author' | 'occurrence'>),
    id: Number(r.id),
    occurrence: null,
    category: category ? toCategory(category) : null,
    author: user
      ? {
          id: Number(user.id),
          username: user.attributes.username,
          displayName: user.attributes.displayName,
          avatarUrl: user.attributes.avatarUrl,
        }
      : null,
  };
}

/** A listed series becomes one CalEvent per occurrence inside the window, sorted by start. */
function toOccurrences(doc: ApiDocument): CalEvent[] {
  const rows = doc.data as ApiResource[];
  const events: CalEvent[] = [];

  rows.forEach((r) => {
    const event = toEvent(r, doc.included);
    (r.attributes.occurrences || []).forEach((o: { occurrence: string | null; start: string; end: string | null; googleUrl: string }) => {
      events.push({ ...event, occurrence: o.occurrence, start: o.start, end: o.end, googleUrl: o.googleUrl });
    });
  });

  return events.sort((a, b) => a.start.localeCompare(b.start));
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
  const qs = new URLSearchParams({ 'filter[from]': from.toISOString(), 'filter[to]': to.toISOString() });
  if (category) qs.set('filter[category]', category);

  return Promise.all([app.request<ApiDocument>({ method: 'GET', url: api('/calendar-events?' + qs.toString()) }), listCategories()]).then(
    ([doc, categories]) => ({ data: toOccurrences(doc), categories })
  );
}

export function showEvent(id: number): Promise<CalEvent> {
  return app.request<ApiDocument>({ method: 'GET', url: api('/calendar-events/' + id) }).then((doc) => toEvent(doc.data as ApiResource, doc.included));
}

export function saveEvent(attrs: Record<string, unknown>, id?: number): Promise<CalEvent> {
  return app
    .request<ApiDocument>({
      method: id ? 'PATCH' : 'POST',
      url: api('/calendar-events' + (id ? '/' + id : '')),
      body: { data: { type: 'calendar-events', ...(id ? { id: String(id) } : {}), attributes: attrs } },
    })
    .then((doc) => {
      invalidate('events:');
      return toEvent(doc.data as ApiResource, doc.included);
    });
}

/** Start the comment thread for an event that has none. */
export function startDiscussion(id: number): Promise<CalEvent> {
  return app.request<ApiDocument>({ method: 'POST', url: api('/calendar-events/' + id + '/discussion') }).then((doc) => {
    invalidate('events:');
    return toEvent(doc.data as ApiResource, doc.included);
  });
}

export function deleteEvent(id: number): Promise<unknown> {
  return app.request({ method: 'DELETE', url: api('/calendar-events/' + id) }).then((r) => {
    invalidate('events:');
    return r;
  });
}

export function rsvp(id: number, status: string): Promise<CalEvent['rsvp']> {
  return app
    .request<ApiDocument>({ method: 'POST', url: api('/calendar-events/' + id + '/rsvp'), body: { data: { attributes: { status } } } })
    .then((doc) => {
      invalidate('events:');
      return (doc.data as ApiResource).attributes.rsvp;
    });
}

export function listCategories(): Promise<CalCategory[]> {
  return app.request<ApiDocument>({ method: 'GET', url: api('/calendar-categories') }).then((doc) => (doc.data as ApiResource[]).map(toCategory));
}

export function saveCategory(attrs: Record<string, unknown>, id?: number): Promise<CalCategory> {
  return app
    .request<ApiDocument>({
      method: id ? 'PATCH' : 'POST',
      url: api('/calendar-categories' + (id ? '/' + id : '')),
      body: { data: { type: 'calendar-categories', ...(id ? { id: String(id) } : {}), attributes: attrs } },
    })
    .then((doc) => toCategory(doc.data as ApiResource));
}

export function deleteCategory(id: number): Promise<unknown> {
  return app.request({ method: 'DELETE', url: api('/calendar-categories/' + id) });
}
