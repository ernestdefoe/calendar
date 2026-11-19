import { extend } from 'flarum/common/extend';

/**
 * fof/forum-widgets-core, shared by the forum (which renders the widgets) and
 * the admin (whose layout editor places them).
 *
 * 🚨 OPT-IN. FoF shows any registered widget that no saved layout mentions, in
 * its default section, so a widget registered only on the forum side appeared
 * in a right-hand column on every forum with FoF installed, even one whose
 * layout had no widgets at all, and FoF's editor could not take it out (the
 * same bug as Cadence's, Discuss d/39992/14). Now a widget shows only once the
 * admin has dragged it into a section and saved; until then it waits in the
 * editor's list of available widgets.
 */

/** A module of fof/forum-widgets-core, at runtime — never a build-time import. */
export function fof(path: string): any {
  const mod = (globalThis as any).flarum?.reg?.get?.('fof-forum-widgets-core', path);
  return mod?.default ?? mod;
}

export function hasFofWidgets(): boolean {
  const flarum = (globalThis as any).flarum;
  return !!flarum?.extensions && 'fof-forum-widgets-core' in flarum.extensions;
}

/** Whether the saved layout places this widget in a section. */
export function placed(app: any, extension: string, key: string): boolean {
  return (app.widgets?.config?.instances || []).some((i: any) => i?.id === `${extension}:${key}`);
}

export interface EditorWidget {
  key: string;
  icon: string;
  title: () => any;
  placement?: string;
}

/**
 * Offer the widgets to FoF's layout editor, which lists only widgets
 * registered on the admin side too. Registered through FoF's public `Widgets`
 * extender (`common/extend/Widgets`), with an empty component: the admin only
 * places them, it never renders them. Then `leaveUnplaced` keeps a widget no
 * saved layout places in the editor's list of available widgets.
 */
export function registerEditorWidgets(app: any, extension: string, widgets: EditorWidget[]): void {
  const Widgets = fof('common/extend/Widgets');
  if (!Widgets || !app.widgets) return;

  const add = new Widgets();
  widgets.forEach((w, position) =>
    add.add({
      key: w.key,
      component: { component: () => null },
      title: w.title,
      icon: w.icon,
      isDisabled: false,
      isUnique: true,
      placement: w.placement || 'end',
      position,
    })
  );
  add.extend(app, extension);

  leaveUnplaced(extension, widgets);
}

/**
 * Start the editor with our unplaced widgets in its "available" list instead
 * of in a section.
 *
 * 🚨 Relies on fof/forum-widgets-core internals, because 2.x has no public way
 * to register a widget as "not placed by default": FoF's WidgetManager adds
 * every registered widget no saved layout mentions to its registered
 * placement, and the editor shows a widget in the store list only when its id
 * is in `config.disabled`. So this extends `WidgetEditor.prototype.oninit`
 * (module `admin/components/WidgetEditor`) and, after FoF's own oninit has set
 * the private `this.config` from the `fof-forum-widgets-core.config` setting
 * and handed the same object to `app.widgets.setConfig()`, appends our
 * unplaced ids to `this.config.disabled` in place. Nothing is saved until the
 * admin saves the layout. If FoF renames any of these, the widgets simply
 * start inside a section in the editor, as FoF draws them by default.
 */
function leaveUnplaced(extension: string, widgets: EditorWidget[]): void {
  const WidgetEditor = fof('admin/components/WidgetEditor');
  if (!WidgetEditor?.prototype) return;

  extend(WidgetEditor.prototype, 'oninit', function (this: any) {
    const config = this.config;
    if (!config || typeof config !== 'object') return;

    const disabled: string[] = Array.isArray(config.disabled) ? config.disabled : [];
    const instances: any[] = Array.isArray(config.instances) ? config.instances : [];
    const unplaced = widgets.map((w) => `${extension}:${w.key}`).filter((id) => !disabled.includes(id) && !instances.some((i: any) => i?.id === id));
    config.disabled = [...disabled, ...unplaced];
  });
}
