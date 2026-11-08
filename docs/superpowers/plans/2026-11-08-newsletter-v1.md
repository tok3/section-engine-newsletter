# Section Engine Newsletter v1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax. Work test-first: failing test → implementation → green → commit. Read `AGENTS.md` first.

**Goal:** Build `tok3/section-engine-newsletter` v1: embeddable signup form via `{{ newsletter:list-key tag=… }}`, double opt-in with full consent proof, list/subscriber management in Filament, retention, documentation. No sending.

**Spec:** `docs/superpowers/specs/2026-11-08-newsletter-signup-design.md` (authoritative for behaviour; this plan fixes the remaining technical decisions).

**Tech Stack:** PHP 8.3+, Laravel 11/12/13, Filament 5, `tok3/section-engine ^1.1.0`, Orchestra Testbench + Workbench (same setup as `~/Sites/section-engine-editorial`), PHPUnit, Pint, Larastan.

**Reference package for structure, tooling and test setup:** `~/Sites/section-engine-editorial` (read only – do not modify).

**Core API to use (read core `vendor/tok3/section-engine/docs/live-tag-providers.md`):**
- `Tok3\SectionEngine\Contracts\LiveTagProvider` (`prefix()`, `label()`, `render(LiveTag, LiveTokenRenderContext)`, `insertOptions()`)
- `Tok3\SectionEngine\Support\LiveTagRegistry::register()`
- `Tok3\SectionEngine\Support\LiveTokenRenderContext` (`locale`, `editorPreview`, `page`, `url`)
- `Tok3\SectionEngine\Support\LiveTagUsageFinder::usagesFor('newsletter', $listKey)` / `summaryFor()`
- `Tok3\SectionEngine\Support\SectionEngineAccess` for Filament-side authorization, same as core resources

## Global Constraints

- Only this repository. No changes to `~/Sites/section-engine*` or `~/Sites/leibnizer*` except the explicitly local, **uncommitted** integration check in Task 11.
- No sending, no third-party services.
- No push, no tag.
- Stop and report on any contradiction between plan, spec and core API.

## Fixed Technical Decisions (do not re-decide)

1. **Tables** (prefix like core): `section_engine_newsletter_lists`, `section_engine_newsletter_subscribers`, `section_engine_newsletter_subscriptions`, `section_engine_newsletter_events`. Columns exactly as the spec "Data model"; bigint auto-increment ids; foreign keys: subscriptions → subscribers/lists `cascadeOnDelete`, events → subscriptions `cascadeOnDelete`, `source_page_id` without FK (`nullable`, page model is configurable). Unique (`subscriber_id`, `list_id`). Index on `status`, `tag`, `source_page_id`.
2. **Status** is a backed enum `SubscriptionStatus` (`pending`, `confirmed`, `unsubscribed`) with `label()` and `color()`; this is the only place colours are defined. Event types as enum `SubscriptionEventType`.
3. **Email normalisation:** `mb_strtolower(trim())`, max 254, validated `email:rfc` (DNS check only if `config validate_dns` true, default false).
4. **Signed form context:** hidden field `context` = `Crypt::encryptString(json)` with `list`, `tag`, `page_id`, `url`, `locale`, `form_id` (random 16 hex per render), `rendered_at` (unix). Rejected if not decryptable or older than `context_ttl_hours` (default 168). Honeypot field name `website` (visually hidden, `tabindex="-1"`, `autocomplete="off"`). Minimum fill time `min_fill_seconds` (default 3) against `rendered_at`.
5. **Tag normalisation:** lowercase, allowed `[a-z0-9_-]`, max 64; anything else → tag dropped (null), signup still accepted.
6. **Routes** (prefix `config route_prefix`, default `newsletter`, middleware `web`), named `section-engine-newsletter.*`:
   - `POST /newsletter/subscribe` → `subscribe`
   - `GET|POST /newsletter/confirm/{subscription}/{token}` → `confirm.show` / `confirm`
   - `GET|POST /newsletter/unsubscribe/{subscription}` (signed, no expiry) → `unsubscribe.show` / `unsubscribe`
   - `POST /newsletter/unsubscribe/{subscription}/one-click` (signed, CSRF-exempt, RFC 8058) → `unsubscribe.one-click`
   Routes must be registered so they are reachable alongside the core page fallback route (add a feature test that hits them with core installed).
7. **Rate limits** (named limiter `section-engine-newsletter`): per IP `rate_limits.per_ip` (default 10/min), per email+list `rate_limits.per_email` (default 3/hour). On limit: same generic response as success (no enumeration), nothing stored, nothing sent.
8. **Token:** `Str::random(64)`, stored as `hash('sha256')`, expiry `confirmation_ttl_hours` (default 48). Resend cooldown `resend_cooldown_minutes` (default 10).
9. **Responses:** one generic success message for every case (translated). Without JS: redirect back to `url#newsletter-{form_id}` with flash keyed by `form_id`. With `Accept: application/json`: JSON `{status, message}`; validation errors as 422 JSON. The form view reads its own flash by `form_id`.
10. **Frontend JS:** tiny vanilla inline script (no Alpine, no build), included once per page (static flag), progressive enhancement only.
11. **Consent text:** per list and locale (JSON), fallback to `config default_locale`; the exact rendered consent text plus checkbox label is snapshotted with `consent_hash = sha256(snapshot)`. `require_checkbox` default `true`.
12. **Mail:** Mailable `ConfirmSubscriptionMail`, sent via `Mail::mailer(config('mailer'))` (`null` = default); queued if `config('queue')` is a non-empty connection name, otherwise sent synchronously. Locale = signup locale. Subject/intro from list overrides or translations.
13. **Editor preview** (`$context->editorPreview`): render the form with all controls `disabled`, plus a small hint "Newsletter: {list name}{, Tag: …}"; never write anything.
14. **Unknown/inactive list:** frontend `''`; editor preview or `section-engine.design_mode`: `<mark data-section-engine-newsletter-missing="{key}">` with escaped placeholder.
15. **insertOptions:** one entry per active list: label `{list name}`, snippet `{{ newsletter:{key} }}`; provider `label()` = `Newsletter`.
16. **Filament:** `SectionEngineNewsletterPlugin::make()` registered in a panel; resources only accessible when `SectionEngineAccess::allows()`. Consent proof export = printable HTML view (no PDF dependency in v1).
17. **Prune:** command `section-engine-newsletter:prune`, scheduled daily by the package (`callAfterResolving(Schedule::class)`), config `prune.pending_days` (30), `prune.unsubscribed_proof_years` (3).
18. **Translations:** `de` and `en`, all user-facing strings via `__('section-engine-newsletter::…')`.

## Tasks

### Task 0 – Scaffold
- [x] `git init`, branch `main`, remote `origin` = `git@github.com:tok3/section-engine-newsletter.git` (do not push).
- [x] `composer.json` modelled on Editorial: name, proprietary license, require `tok3/section-engine: ^1.1.0`, Filament 5, Laravel 11–13, `spatie/laravel-package-tools`; vcs repository for `tok3/section-engine`; dev: testbench, phpunit, pint, larastan; scripts `test`, `format`, `format:check`, `analyse`.
- [x] Service provider `SectionEngineNewsletterServiceProvider` (package tools: config, views, translations, migrations, routes, command), registers the live tag provider in `boot()`.
- [x] Testbench/Workbench with a Filament admin panel that registers `SectionEnginePlugin` and `SectionEngineNewsletterPlugin`; base `TestCase`.
- [x] `.gitignore`, `phpunit.xml.dist`, `pint.json`, `phpstan.neon.dist`, `README.md` stub, `CHANGELOG.md` (`Unreleased`), copy of `AGENTS.md` stays.
- [x] Smoke test: package boots with core; registry has prefix `newsletter`.
- [x] Commit `chore: scaffold section engine newsletter package`

### Task 1 – Schema and models
- [x] Migrations, models (`NewsletterList` soft deletes, `Subscriber`, `Subscription`, `SubscriptionEvent` append-only: updating/deleting an event throws), enums, factories.
- [x] Tests: uniqueness, cascades, append-only events, email normalisation mutator.
- [x] Commit `feat: add newsletter schema and models`

### Task 2 – Live tag provider and form
- [x] `NewsletterLiveTagProvider` + Blade views `form/default.blade.php`, `form/compact.blade.php` (Bootstrap 5 classes, publishable), signed context, honeypot, accessible markup, JS include once.
- [x] Tests: valid list renders form with decryptable context containing page id/url/locale/tag; tag normalisation; variants; unknown/inactive list (frontend empty, design mode/editor marked); editor preview disabled + hint; insertOptions; output escaping; accessibility attributes present (label `for`, `aria-describedby`, `aria-live` region); renders through core `PageRenderer` with page context.
- [x] Commit `feat: render newsletter signup forms via live tags`

### Task 3 – Subscribe endpoint
- [x] Controller + form request implementing spec "Signup flow" steps 1–3 with Decisions 3–9.
- [x] Tests: each case (new, pending resend within/after cooldown, confirmed, unsubscribed→pending) → identical response; honeypot, fill time, tampered/expired context, rate limits; required checkbox; JSON and redirect modes; events `requested`/`resubscribe_requested` with full IP and user agent; nothing stored on rejection; routes reachable with core fallback route.
- [x] Commit `feat: accept newsletter signups with double opt-in requests`

### Task 4 – Confirmation
- [x] Mail, confirm GET page (button), confirm POST, expired-token page with resend form.
- [x] Tests: mail sent/queued per config and mailer; GET never changes state; POST confirms and logs `confirmed` with IP/UA; wrong/expired token; double confirm idempotent; `confirmation_sent` event.
- [x] Commit `feat: confirm newsletter subscriptions`

### Task 5 – Unsubscribe
- [x] Signed GET page + POST, one-click endpoint.
- [x] Tests: signature required; state change only on POST/one-click; event `unsubscribed` with IP; resubscribe requires new double opt-in.
- [x] Commit `feat: unsubscribe from newsletter lists`

### Task 6 – Domain events and retention
- [ ] Dispatch `SubscriptionRequested`, `SubscriptionConfirmed`, `SubscriptionUnsubscribed`, `SubscriberDeleted`. Prune command + schedule.
- [ ] Tests: events dispatched once per transition; prune deletes old pending incl. events; anonymises IP/UA of old unsubscribed proof; leaves everything else untouched; schedule registered.
- [ ] Commit `feat: dispatch newsletter events and prune stale data`

### Task 7 – Filament: lists
- [ ] `NewsletterListResource`: CRUD, counts per status, consent texts per locale, "embedded on" via `LiveTagUsageFinder`, copy-snippet action.
- [ ] Tests: access only with `SectionEngineAccess`; counts; usage list; validation (key format `[a-z0-9_-]`, unique).
- [ ] Commit `feat: manage newsletter lists in filament`

### Task 8 – Filament: subscribers
- [ ] `SubscriberResource`: table (email, list status chips, tags, source page link, dates), filters (list, status, tag, source page, date range), detail with full event history, actions: CSV export (respects filters), consent proof view, manual unsubscribe (`admin_action` event), permanent delete (confirmation, removes all rows), "add manually" = create pending + send confirmation mail. Dashboard widget signups last 30 days per list/tag.
- [ ] Tests for every action incl. CSV content, hard delete leaves no rows, manual add never confirms directly.
- [ ] Commit `feat: manage newsletter subscribers in filament`

### Task 9 – Translations and accessibility pass
- [ ] Complete `de`/`en`. Review form against AGENTS.md accessibility rules; fix and test focus/aria behaviour in a JS test (jsdom) for the progressive enhancement.
- [ ] Commit `feat: complete newsletter translations and accessibility`

### Task 10 – Documentation
- [ ] `README.md`, `docs/installation.md`, `docs/embedding.md` (end-user guide with examples), `docs/admin.md`, `docs/privacy.md` (stored data, retention, consent proof, privacy-policy building blocks, "not legal advice"), `docs/developer.md` (events, config, extension points, Phase 2 outlook). `CHANGELOG.md` `Unreleased` entries.
- [ ] Commit `docs: document section engine newsletter`

### Task 11 – Verification and local integration check
- [ ] Full matrix: `composer test`, `composer format:check`, `composer analyse`, JS tests.
- [ ] Local integration in `~/Sites/leibnizer-dev`, **uncommitted, to be reverted afterwards**: path repository to `../section-engine-newsletter` (symlink), require `dev-main`, migrate, register plugin in the panel, `MAIL_MAILER=log`. Embed `{{ newsletter:test tag=probe }}` on a test page, sign up, confirm via link from the log, unsubscribe; check Filament lists/subscribers and event history. Then revert all `leibnizer-dev` changes and confirm `git status` there matches the state before.
- [ ] Handoff note at the end of this file: what was built, deviations with reason, open issues, exact consumer changes needed later (composer, panel registration, migrations, env). Do not mark complete if any test was skipped or weakened.

## Review Focus (for the Claude review after the build – not a self-check)

- Consent proof complete and immutable for every transition.
- No address enumeration via responses, timing differences or rate limiting.
- GET never changes state; signatures and tokens enforced.
- Only documented core APIs used; no core changes.
- Accessibility of the form; works without JS.
- Leibnizer integration fully reverted.
