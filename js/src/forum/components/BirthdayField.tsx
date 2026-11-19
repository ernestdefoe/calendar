import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import type User from 'flarum/common/models/User';
import { loc, num } from '../../common/dates';

const t = (k: string, p?: any): any => app.translator.trans('ernestdefoe-calendar.forum.' + k, p);

const pad = (n: number) => String(n).padStart(2, '0');

/**
 * Self-service birthday picker for the account settings page. Stores only month +
 * day (MM-DD) — never a year — so celebrating a birthday can't reveal someone's
 * age. Saving an empty selection clears it (fully opt-in / opt-out).
 */
const BirthdayField = {
  oninit(this: any) {
    this.mm = '';
    this.dd = '';
    this.saved = '';
    this.loaded = false;
    this.saving = false;

    // The birthday is only sent when a member loads their OWN user record
    // (GET /api/users/{id}), not with every page, so fetch it here.
    const user = app.session.user;
    if (!user) return;

    app.store
      .find<User>('users', user.id()!)
      .then((loaded) => {
        this.apply(String(loaded.attribute('calendarBirthday') || ''));
        this.loaded = true;
      })
      // A failed load leaves the picker disabled rather than offering to
      // overwrite a birthday we could not read; core shows the request error.
      .catch(() => {})
      .finally(() => m.redraw());
  },

  /** Show a stored MM-DD value and remember it as the saved one. */
  apply(this: any, value: string) {
    const [mm, dd] = value.split('-');
    this.mm = mm || '';
    this.dd = dd || '';
    this.saved = value;
  },

  view(this: any) {
    const months = Array.from({ length: 12 }, (_, i) => ({
      v: pad(i + 1),
      label: new Date(2000, i, 1).toLocaleDateString(loc(), { month: 'long' }),
    }));
    const days = Array.from({ length: 31 }, (_, i) => pad(i + 1));
    const current = this.mm && this.dd ? this.mm + '-' + this.dd : '';
    const dirty = this.loaded && current !== this.saved;
    const disabled = !this.loaded || this.saving;

    return m('.CalBirthday', [
      m('p.helpText', t('birthday_help')),
      m('.CalBirthday-row', [
        m('select.FormControl.CalBirthday-month', { value: this.mm, disabled, onchange: (e: any) => (this.mm = e.target.value) }, [
          m('option', { value: '' }, t('birthday_month')),
          ...months.map((mo) => m('option', { value: mo.v }, mo.label)),
        ]),
        m('select.FormControl.CalBirthday-day', { value: this.dd, disabled, onchange: (e: any) => (this.dd = e.target.value) }, [
          m('option', { value: '' }, t('birthday_day')),
          ...days.map((d) => m('option', { value: d }, num(+d))),
        ]),
        Button.component(
          { className: 'Button', loading: this.saving, disabled: !dirty || !!this.mm !== !!this.dd, onclick: () => this.save() },
          t('birthday_save')
        ),
      ]),
    ]);
  },

  save(this: any) {
    const user = app.session.user;
    if (!user) return;
    const value = this.mm && this.dd ? this.mm + '-' + this.dd : null;
    this.saving = true;
    m.redraw();
    user
      .save({ calendarBirthday: value })
      .then(() => {
        this.saving = false;
        this.saved = value || '';
        app.alerts.show({ type: 'success' }, t('birthday_saved'));
        m.redraw();
      })
      .catch(() => {
        this.saving = false;
        m.redraw();
      });
  },
};

export default BirthdayField;
