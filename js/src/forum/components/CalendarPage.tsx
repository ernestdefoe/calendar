import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import EventFormModal from './EventFormModal';
import EventDetailModal from './EventDetailModal';
import { listEvents, feedUrl, type CalEvent, type CalCategory } from '../../common/api';
import { monthMatrix, weekdayNames, monthLabel, eventOnDay, isToday, sameDay, shortTime, formatRange } from '../../common/dates';

declare const m: any;
const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-calendar.forum.' + k, p);

export default class CalendarPage extends Page {
  mode: 'month' | 'list' = 'month';
  year!: number;
  month!: number;
  events: CalEvent[] = [];
  categories: CalCategory[] = [];
  category = '';
  loading = true;

  oninit(vnode: any) {
    super.oninit(vnode);
    const now = new Date();
    this.year = now.getFullYear();
    this.month = now.getMonth();
    this.mode = (app.forum.attribute('ernestdefoe-calendar.defaultView') as any) || 'month';
    app.setTitle(t('page_title') as any);
    this.load();
  }

  weekStart(): number { return (app.forum.attribute('ernestdefoe-calendar.weekStartsOn') as number) || 0; }

  range(): { from: Date; to: Date } {
    if (this.mode === 'month') {
      const cells = monthMatrix(this.year, this.month, this.weekStart());
      const from = cells[0];
      const to = new Date(cells[41]);
      to.setHours(23, 59, 59);
      return { from, to };
    }
    const from = new Date();
    from.setHours(0, 0, 0, 0);
    const to = new Date(from);
    to.setMonth(to.getMonth() + 12);
    return { from, to };
  }

  load() {
    this.loading = true;
    const { from, to } = this.range();
    listEvents(from, to, this.category || undefined)
      .then((res) => { this.events = res.data; this.categories = res.categories; this.loading = false; m.redraw(); })
      .catch(() => { this.loading = false; m.redraw(); });
  }

  step(delta: number) {
    this.month += delta;
    if (this.month < 0) { this.month = 11; this.year--; }
    if (this.month > 11) { this.month = 0; this.year++; }
    this.load();
  }

  goToday() {
    const now = new Date();
    this.year = now.getFullYear();
    this.month = now.getMonth();
    this.load();
  }

  openCreate(day?: Date) {
    app.modal.show(EventFormModal, { day, onsave: () => this.load() });
  }
  openEvent(ev: CalEvent) {
    app.modal.show(EventDetailModal, { event: ev, onchange: () => this.load() });
  }

  view() {
    return m('.CalendarPage', m('.container', [
      this.header(),
      this.loading ? m('.CalendarPage-loading', m('div.LoadingIndicator')) : (this.mode === 'month' ? this.monthView() : this.listView()),
    ]));
  }

  header() {
    const canCreate = app.forum.attribute('canCreateEvent');
    return m('.CalendarPage-header', [
      m('.CalendarPage-titlebar', [
        m('h1.CalendarPage-title', this.mode === 'month' ? monthLabel(this.year, this.month) : t('upcoming')),
        m('.CalendarPage-nav', this.mode === 'month' ? [
          Button.component({ className: 'Button Button--icon', icon: 'fas fa-chevron-left', onclick: () => this.step(-1) }),
          Button.component({ className: 'Button', onclick: () => this.goToday() }, t('today')),
          Button.component({ className: 'Button Button--icon', icon: 'fas fa-chevron-right', onclick: () => this.step(1) }),
        ] : null),
      ]),
      m('.CalendarPage-tools', [
        m('.CalendarPage-views', [
          tab(this, 'month', t('view_month')),
          tab(this, 'list', t('view_list')),
        ]),
        this.categories.length
          ? m('select.FormControl.CalendarPage-filter', { value: this.category, onchange: (e: any) => { this.category = e.target.value; this.load(); } }, [
              m('option', { value: '' }, t('field_category')),
              ...this.categories.map((c) => m('option', { value: c.slug }, c.name)),
            ])
          : null,
        m('a.Button.Button--icon.CalendarPage-subscribe', { href: feedUrl(), title: t('subscribe') as string }, [m('i.Button-icon.fas.fa-rss')]),
        canCreate ? Button.component({ className: 'Button Button--primary', icon: 'fas fa-plus', onclick: () => this.openCreate() }, t('add_event')) : null,
      ]),
    ]);
  }

  monthView() {
    const cells = monthMatrix(this.year, this.month, this.weekStart());
    return m('.CalendarGrid', [
      m('.CalendarGrid-weekdays', weekdayNames(this.weekStart()).map((d) => m('span', d))),
      m('.CalendarGrid-days', cells.map((day) => this.dayCell(day))),
    ]);
  }

  dayCell(day: Date) {
    const inMonth = day.getMonth() === this.month;
    const dayEvents = this.events.filter((e) => eventOnDay(e, day)).slice(0, 4);
    const canCreate = app.forum.attribute('canCreateEvent');
    return m('.CalendarGrid-cell' + (inMonth ? '' : '.is-out') + (isToday(day) ? '.is-today' : ''), {
      ondblclick: canCreate ? () => this.openCreate(day) : undefined,
    }, [
      m('span.CalendarGrid-num', day.getDate()),
      m('.CalendarGrid-events', dayEvents.map((ev) => m('button.CalendarChip', {
        type: 'button',
        style: ev.category ? { '--cal-accent': ev.category.color } : undefined,
        onclick: (e: Event) => { e.stopPropagation(); this.openEvent(ev); },
      }, [
        ev.allDay ? null : m('span.CalendarChip-time', shortTime(ev.start)),
        m('span.CalendarChip-title', ev.title),
      ]))),
    ]);
  }

  listView() {
    const now = Date.now();
    const upcoming = this.events.filter((e) => new Date(e.end || e.start).getTime() >= now);
    if (!upcoming.length) return m('.CalendarPage-empty', t('no_events'));
    return m('ul.CalendarList', upcoming.map((ev) => m('li.CalendarList-item', { onclick: () => this.openEvent(ev) }, [
      m('.CalendarList-date', { style: ev.category ? { '--cal-accent': ev.category.color } : undefined }, [
        m('span.CalendarList-mon', new Date(ev.start).toLocaleDateString(undefined, { month: 'short' })),
        m('span.CalendarList-day', new Date(ev.start).getDate()),
      ]),
      m('.CalendarList-body', [
        m('span.CalendarList-name', ev.title),
        m('span.CalendarList-meta', formatRange(ev)),
        ev.location ? m('span.CalendarList-loc', [m('i.icon.fas.fa-location-dot'), ' ', ev.location]) : null,
      ]),
    ])));
  }
}

function tab(page: CalendarPage, mode: 'month' | 'list', label: string) {
  return m('button.Button.CalendarPage-view' + (page.mode === mode ? '.is-active' : ''), {
    type: 'button',
    onclick: () => { page.mode = mode; page.load(); },
  }, label);
}
