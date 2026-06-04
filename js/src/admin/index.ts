import app from 'flarum/admin/app';
import CategoryManager from './components/CategoryManager';

declare const m: any;

/**
 * Admin entry. Scalar settings + permissions are registered declaratively in
 * ./extend (the Flarum 2 way); the category manager is a custom component, so it
 * is appended imperatively to the same settings page.
 */
export { default as extend } from './extend';

app.initializers.add('ernestdefoe/calendar', () => {
  // `extensionData` exists at runtime on the admin app but isn't in the published
  // type definitions, hence the cast.
  (app as any).extensionData
    .for('ernestdefoe-calendar')
    .registerSetting(() => m(CategoryManager));
});
