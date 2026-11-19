# Calendar Floxum wave 3: paused 2026-11-19 (Ernest: "Yes, pause rewrites")

Resume after the weekly usage reset (Nov 26). Branch `wip/floxum-wave`, local only, never pushed. `main` is still at v2.8.1 (2fdfc6f).
Brief: ~/github/command-center/floxum/fix-wave-brief.md. Floxum's review of v2.8.1: 91 (one medium "custom PSR-15 controllers", one low "optional deps / class_exists").

## Done (7 commits on top of v2.8.1)
- API rebuilt on Flarum 2 resources: `calendar-events` (Index/Show/Create/Update/Delete + custom `rsvp`, `discussion`), `calendar-categories`, `calendar-widgets` (pulse, memories, celebrations, activity). Old `/api/calendar/…` routes are gone; options are `filter[…]` (JSON:API refuses all-lowercase params). Only the two iCal forum routes stay custom (text/calendar).
- EventPolicy + ScopeEventVisibility; `isPublished` writable (drafts); `finishes_at` column (migration 2026_11_19_000002, backfilled) so the window skips what is over; RecurrenceExpander fast-forwards open series.
- Birthdays moved off `users` into `calendar_birthdays` (migrations 2026_06_04_000005 and 2026_06_09 kept as filenames with new contents, because rollback needs them; 2026_11_19_000000/1 move existing data; full rollback leaves `users` clean). Birthday serialized only on the member's own Show/Update.
- Tags, Armory characters, FoF Upload flag, FoF Badges, Page Builder: all registered through `Extend\Conditional`; only `Integrations::image()` still asks isEnabled (Markdown vs BBCode).
- Pulse/activity count only public posts the viewer can see; pulse cache keyed by permissionGroupIds.
- Frontend: Extend.Routes, extend.ts files, store models (CalendarEvent, CalendarCategory), shared api.ts for forum + admin, no console.*, commented catches, countdown ticker only while needed, no JS setting defaults.
- Page Builder block labels translated (CalendarBlock base, `admin.page_builder.*` keys).
- Readers of the old API updated: showcase seed `seeds/24-calendar.php` (showcase repo 510eeb3, pushed, on the box) works with BOTH old and new API; README API section.
- Tests: 96 pass on SQLite, PostgreSQL 16 and MySQL (drivers confirmed). New: WidgetVisibilityTest, BirthdayMoveMigrationTest, drafts, window/finishes_at, unknown category, birthday visibility.
- UI checked on dev (calendar, event modal + RSVP, form with category, admin categories, birthday picker): no API/console errors.

## Left
1. Third pre-floxum pass on HEAD (was stopped before it reported). Last completed pass (second, at 5dfb692): 91 predicted, band 85–97, 10 low findings; 9 fixed in ba58e26. Not fixed:
   - FoF internals patched in js/src/common/fofWidgets.ts (`leaveUnplaced`, WidgetEditor config.disabled) and js/src/admin/badges.ts (TriggerBuilder view/emitChange). Needs public hooks in fof/forum-widgets-core and fof/badges (FoF PRs).
   - Watch: `DATE()` in raw SQL, src/Activity/ActivityRepository.php (Floxum charged Trust Levels medium for this).
2. MySQL suite once more on a clean run (one error appeared on the first run right after switching the test config back from PostgreSQL; two reruns were clean).
3. Phone (390px) check of the event modal; the iOS/Android pass for UI changes.
4. Send the Command Center: range v2.8.1..HEAD, pre-floxum score, test results, and the API change for outside scripts (old `/api/calendar/…` routes removed). Wait for approval.
5. After approval: merge to main, release (a `feat:` commit is in the range, so the drafter makes 2.9.0; edonline changelog only, Discuss paused), deploy dev + showcase (`bin/deploy`; seed already handles 2.9) + ernestdefoe.online (Calendar enabled there 2026-11-19: `rm storage/locale/catalogue.*`, `millwright:rebuild-assets`, php-fpm, then Horizon; check widget keys in public/assets/forum-en.js). Update KB `ext-calendar-reference` (id 188) API section.

## Dev state
dev.ernestdefoe.online runs THIS branch's code (copied into vendor/ernestdefoe/calendar by hand at ba58e26) and its migrations ran there (calendar_birthdays, finishes_at, users.cal_birthday dropped). Do not put 2.8.1 back on dev without reverting those migrations: 2.8.1's celebrations widget reads users.cal_birthday.
