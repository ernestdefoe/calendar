import app from 'flarum/forum/app';
import UpcomingEvents from './components/UpcomingEvents';
import PulseWidget from './components/PulseWidget';
import OnThisDayWidget from './components/OnThisDayWidget';
import CelebrationsWidget from './components/CelebrationsWidget';

/** A full key: Bespoke resolves a dotted schema label from our locale. */
const K = 'ernestdefoe-calendar.forum.';
const text = (k: string): string => app.translator.trans(K + k, {}, true) as string;

/**
 * Register the calendar's placeable widgets ("Upcoming events" + "Forum pulse")
 * with sibling extensions when present. Each integration is feature-detected and
 * guarded, so this is a no-op on a stock forum (where index.ts mounts them).
 */
export function registerIntegrations(): void {
  registerBespoke();
  registerPageBuilder();
}

/** Every zone a calendar widget can be placed in. */
const ZONES = ['hero', 'above-list', 'sidebar', 'below-list', 'footer'];

/** One Bespoke widget definition, placeable in every zone. */
function widget(type: string, label: string, icon: string, schema: any[], view: (settings: any) => any) {
  return { type, label, icon, zones: [...ZONES], schema, component: { view: (v: any) => view(v.attrs.settings) } };
}

/**
 * The calendar's Bespoke widget definitions. Built on registration, not at
 * load, because the schema defaults are read from the translator. Each label
 * is resolved by Bespoke as forum.<label>; the keys come from our locale.
 */
function bespokeWidgets(): any[] {
  const title = (key: string) => ({ key: 'title', type: 'text', label: K + 'wf_title', default: text(key) });

  return [
    widget(
      'events',
      'widget_events',
      '📅',
      [
        title('widget_title'),
        { key: 'count', type: 'number', label: K + 'wf_how_many', default: 5 },
        { key: 'category', type: 'text', label: K + 'wf_category', default: '' },
      ],
      (s) => m(UpcomingEvents, { title: s.title, count: s.count, category: s.category })
    ),
    widget(
      'pulse',
      'widget_pulse',
      '📈',
      [
        title('pulse_title'),
        { key: 'count', type: 'number', label: K + 'wf_leaderboard_size', default: 5 },
        { key: 'weeks', type: 'number', label: K + 'wf_weeks', default: 14 },
      ],
      (s) => m(PulseWidget, { title: s.title, count: s.count, weeks: s.weeks })
    ),
    widget(
      'memories',
      'widget_memories',
      '🕰️',
      [title('memories_title'), { key: 'count', type: 'number', label: K + 'wf_how_many', default: 6 }],
      (s) => m(OnThisDayWidget, { title: s.title, count: s.count })
    ),
    widget('celebrations', 'widget_celebrations', '🎉', [title('widget_celebrations')], (s) => m(CelebrationsWidget, { title: s.title })),
  ];
}

/** ernestdefoe/bespoke — register as a placeable theme widget. */
function registerBespoke(): void {
  try {
    const bespoke = (app as any).bespoke;
    if (!bespoke?.widgets?.add) return;

    bespokeWidgets().forEach((widget) => bespoke.widgets.add(widget));
  } catch {
    // Bespoke is optional and its widget API is its own: if a version of it
    // changed that API, the calendar's widgets just aren't offered there. The
    // rest of the forum is unaffected and there is nothing for a member to do.
  }
}

/**
 * ernestdefoe/page-builder — register the block's RENDER component so a placed
 * "events" block renders (it self-fetches, ignoring PB's server data). Appearing
 * in PB's editor palette additionally needs a server-manifest entry on PB's side
 * (a small coordinated addition there).
 */
function registerPageBuilder(): void {
  try {
    const pb = (app as any).pageBuilder;
    if (!pb?.registerBlock) return;

    pb.registerBlock('events', {
      view: (v: any) => {
        const s = v.attrs.settings || {};
        return m(UpcomingEvents, { title: s.title, count: s.count || 5, category: s.category });
      },
    });

    pb.registerBlock('pulse', {
      view: (v: any) => {
        const s = v.attrs.settings || {};
        return m(PulseWidget, { title: s.title, count: s.count || 5, weeks: s.weeks });
      },
    });

    pb.registerBlock('memories', {
      view: (v: any) => {
        const s = v.attrs.settings || {};
        return m(OnThisDayWidget, { title: s.title, count: s.count || 6 });
      },
    });

    pb.registerBlock('celebrations', {
      view: (v: any) => {
        const s = v.attrs.settings || {};
        return m(CelebrationsWidget, { title: s.title });
      },
    });
  } catch {
    // Page Builder is optional and its block API is its own: if a version of it
    // changed that API, the calendar's blocks just don't render there. The rest
    // of the forum is unaffected and there is nothing for a member to do.
  }
}
