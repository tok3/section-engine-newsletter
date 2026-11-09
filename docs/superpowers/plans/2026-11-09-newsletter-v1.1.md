# Section Engine Newsletter 1.1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Read `AGENTS.md`. Test-first, one commit per task.

**Goal:** Make the newsletter look and feel finished out of the box: themed confirm/unsubscribe/result pages, return to the source page after confirmation with feedback at the form, a proper default mail, and a neutral, accessible default form style that works on any site.

**Precondition:** Section Engine Core `v1.3.0` with `Tok3\SectionEngine\Support\ThemePageResponse` (core `docs/theme-pages.md`) is tagged and pushed. Require `tok3/section-engine: ^1.3.0`.

**Decisions by Tobias (2026-11-09):** Confirmation stays **button-based** (POST) – link opening alone never confirms (mail scanners). Unsubscribe stays button-based. Pages must integrate into the existing site theme.

## Global Constraints

- Only this repository. Use only documented core APIs (`live-tag-providers.md`, `theme-pages.md`).
- Backward compatible for existing installs: config keys, routes, tables unchanged; new config keys have defaults.
- No push, no tag. Stop and report on contradictions.

## Fixed Decisions

1. **Themed pages:** confirm (button), expired-confirmation (with resend form), unsubscribe (button), and all result/error pages are rendered via `ThemePageResponse::make()` with `robots = noindex, nofollow`. Correct status codes: invalid token 404, expired 410. Page bodies are small package views (`pages/*.blade.php`, publishable) using the neutral styles (Decision 4).
2. **After confirmation (POST):** redirect to the subscription's `source_url` **only if** its scheme+host match `config('app.url')` (or it is a relative path); append `#newsletter-{list-key}`. Flash `section-engine-newsletter.confirmed = {list key}`. Every newsletter form for that list on the target page renders a success notice **instead of** the form (`role="status"`, focus moved to it, translated text). If `source_url` is missing or foreign: themed result page. Open redirects are impossible (test).
3. **After unsubscribe (POST and one-click):** themed result page with confirmation text and (if `source_url` is same-host) a link back to the site.
4. **Neutral default form styling:**
   - Replace Bootstrap classes with own BEM-style classes prefixed `sen-` (`sen-newsletter`, `sen-newsletter__field`, `__label`, `__input`, `__checkbox`, `__consent`, `__button`, `__message`, `__error`, `--compact`).
   - Ship a small stylesheet as inline `<style data-sen-newsletter-styles>` once per page (same once-per-request mechanism as the script). Config `styles` (default `true`).
   - All selectors wrapped in `:where()` (zero specificity) so any theme rule wins.
   - CSS custom properties on `.sen-newsletter` with defaults: `--sen-color: currentColor`, `--sen-border: 1px solid color-mix(in srgb, currentColor 55%, transparent)` (fallback `1px solid currentColor`), `--sen-radius: .375rem`, `--sen-gap: .75rem`, `--sen-padding: .625rem .75rem`, `--sen-button-bg: currentColor`, `--sen-button-color: Canvas`, `--sen-focus: 2px solid currentColor`, `--sen-max-width: 32rem`.
   - Inherit font family/size/colour from the page; visible input border (WCAG 1.4.11); visible `:focus-visible` outline with offset; inputs and button min height 44px; button clearly recognisable as a button; error text and success/status message styled; `prefers-color-scheme` not forced.
   - `compact` variant: email + button in one row (wrap on narrow widths), consent below.
5. **Default mail:** Markdown mailable (`x-mail::message`, `x-mail::button`) so the app's Laravel mail theme applies. Config `mail.brand_name` (default `config('app.name')`), `mail.logo_url` (nullable), `mail.footer` (nullable, per-locale array allowed). Content: greeting, list name, intro (list override or translation), confirm button, plain URL fallback, ignore notice, footer. Publishable view `mail/confirm.blade.php`. Plain-text part generated.
6. **Translations** de/en for all new strings.

## Tasks

### Task 1 – Require core 1.3 and themed pages
- [x] Bump `tok3/section-engine` to `^1.3.0`, update lock.
- [x] Render confirm/expired/unsubscribe/result/error pages via `ThemePageResponse` (Decisions 1, 3).
- [x] Tests: each page rendered inside the theme view (test theme asserts layout marker), noindex, status codes, GET still never changes state, existing behaviour tests stay green.
- [x] Commit `feat: render newsletter pages inside the site theme`

### Task 2 – Return to the source page after confirmation
- [x] Decision 2.
- [x] Tests: same-host redirect with anchor; relative path; foreign host → themed result page; missing source → themed result page; success notice replaces the form only for the matching list; multiple forms on one page; notice has `role="status"` and receives focus (jsdom); no open redirect via manipulated `source_url`.
- [x] Commit `feat: return to the source page after confirming`

### Task 3 – Neutral, accessible default form styling
- [x] Decision 4; update `form/*.blade.php`.
- [x] Tests: styles emitted once per page and not at all with `styles=false`; all selectors zero-specificity (`:where(`); markup has labels, `aria-describedby`, status region; jsdom test: computed input has a non-`none` border style and button min-height ≥ 44px with the shipped CSS.
- [x] Commit `feat: neutral accessible default newsletter styling`

### Task 4 – Default mail
- [x] Decision 5.
- [x] Tests: markdown render contains brand, list name, button URL, plain URL fallback, footer; logo only when configured; locale de/en; text part present.
- [x] Commit `feat: polished default confirmation mail`

### Task 5 – Documentation and release prep
- [x] `docs/embedding.md`: styling section (CSS variables table, `styles=false`, publishing views, example override for a Tailwind theme), what visitors see after confirming.
- [x] `docs/installation.md`: core ^1.3.0, mail branding config, Laravel mail theme pointer, reminder `MAIL_MAILER` (not `MAIL_DRIVER`) on Laravel 11+.
- [x] `CHANGELOG.md` → `## 1.1.0 - <date>`. Commit `chore: release 1.1.0` (no tag).
- [x] Full matrix; handoff note at the end of this file. No push, no tag.

## Review Focus (Claude review after the build)

- No open redirect; GET never changes state.
- Form usable and visible on an unstyled page, a Bootstrap page and the Leibnizer Tailwind theme; theme overrides always win.
- Pages render inside the site theme with correct status codes and noindex.
- Mail renders cleanly with and without logo.

## Übergabe — 2026-11-09

Tasks 1–5 sind test-first und in der vorgesehenen Reihenfolge umgesetzt. Die vier Feature-Commits sind `c62fa6d`, `af285bf`, `adadb50` und `67a6dc2`; Task 5 schließt mit `chore: release 1.1.0` ab. Verwendet wurden ausschließlich die dokumentierten Core-Schnittstellen aus `live-tag-providers.md` und `theme-pages.md`; Core `v1.3.0` ist in `composer.lock` fixiert, die Paketanforderung lautet `^1.3.0`.

Abschließende Prüfmatrix vor der Übergabe:

- `composer test`: 112 Tests, 868 Assertions, grün.
- `npm test`: 7 Tests, 7 bestanden.
- `composer format:check`: bestanden.
- `composer analyse`: 37 Dateien, keine Fehler.

Besonders abgedeckt sind GET/POST-Trennung, 404/410 und `noindex, nofollow`, manipulierte und fremde `source_url` ohne Redirect, listenbezogene Erfolgsmeldungen samt Fokus, einmalige/abschaltbare Null-Spezifitäts-Styles sowie echte HTML- und Plain-Text-Mailparts in Deutsch und Englisch mit und ohne Logo. Keine Datenbank-, Routen- oder bestehende Config-Schnittstelle wurde entfernt oder umbenannt. Es wurde weder gepusht noch getaggt.

Die Prüfung gegen „Review Focus“ ist bewusst offen und wird anschließend von Claude durchgeführt.

## Claude review — 2026-11-09

**Result:** Tasks 1–5 implemented as planned (themed pages, safe return to source page, mail, docs; `SafeSourceUrl` rejects foreign hosts, protocol-relative URLs, credentials, control characters and backslashes). JS suite re-run by Claude: 7/7. PHP not re-run by Claude.

**One blocking defect, caused by the plan (Claude's error), must be fixed before release:**

### Defect: default button is invisible

Plan Decision 4 set `--sen-button-bg: currentColor` and `--sen-button-color: Canvas`. `currentColor` inside a custom property is resolved on the element where it is used. On the button, `color` is `Canvas`, so `background: var(--sen-button-bg)` and the border (`var(--sen-border)` with `currentColor`) also become `Canvas`: text, background and border share one colour, the button is invisible. jsdom does not compute this, so the tests passed.

### Task 6 – Visible neutral default button

- [x] Default button is **outlined**, never colour-inverted: `--sen-button-bg: transparent`, `--sen-button-color: currentColor` (on the button this equals the inherited page text colour). Border via `--sen-border` then follows the page text colour. Hover: subtle background via `color-mix(in srgb, currentColor 8%, transparent)` with fallback `transparent`.
- [x] A filled button stays possible purely through the variables (documented example).
- [x] Never put `currentColor` into a variable that is consumed on an element whose own `color` is overridden. Add a short comment in the stylesheet explaining this.
- [x] Docs `docs/embedding.md`: update the variable table; replace the Tailwind 3 `theme(colors.…)` example by Tailwind 4 theme variables (e.g. `var(--color-zinc-900)`) plus a plain-CSS example.
- [x] Tests: stylesheet contains the outlined defaults; no `Canvas` as default button colour; a browser-level check is not available in jsdom, so add a test that parses the stylesheet and asserts `--sen-button-bg` default is `transparent` and the button rule does not set `color` to a value different from the one used for `--sen-button-bg`.
- [x] Manual check (report result): render the form on an unstyled page, a Bootstrap page and in `~/Sites/leibnizer-dev` (local, uncommitted, path repository, revert afterwards) – input border, checkbox, button text and border visible; focus outline visible.
- [x] Amend release: keep `## 1.1.0` in CHANGELOG (not yet tagged), commit `fix: visible neutral default newsletter button`. No push, no tag.

### Task 6 addendum – mail links always from APP_URL

- [x] Confirmation (and later unsubscribe) links in mails are built from `config('app.url')`, never from the current request host. Today `route()` in `ConfirmSubscriptionMail` uses the request host when sent synchronously, but `APP_URL` when sent from a queue worker – inconsistent, and it diverges from `SafeSourceUrl`, which compares against `APP_URL`. Generate the URL with the app root (e.g. temporarily `URL::forceRootUrl(config('app.url'))` around the route call, restored afterwards, or `rtrim(config('app.url'), '/').route(..., absolute: false)`).
- [x] Tests: request on host A with `APP_URL` = host B → mail link uses host B, sync and queued; root URL of the running request is unchanged afterwards.
- [x] Docs `installation.md`: `APP_URL` must be the public address of the site; mail links and the return-to-source check depend on it.

## Übergabe Task 6 — 2026-11-09

Der neutrale Standard-Button ist als sichtbarer Outline-Button umgesetzt; ein gefüllter Zustand einschließlich Hover bleibt ausschließlich über CSS-Variablen konfigurierbar. Der neue Stylesheet-Regressionstest wurde zuerst gegen den bisherigen `currentColor`-Hintergrund rot ausgeführt und deckt Defaults, sichere Button-Regeln, Hover-Fallback und `color-mix()` ab. `CHANGELOG.md` bleibt bei `## 1.1.0 - 2026-11-09`.

Die manuelle Sichtprüfung lief in `~/Sites/leibnizer-dev` mit dem Paket als lokales Path-Repository auf einer ungestylten Seite, einer Bootstrap-Seite und im Leibnizer-Tailwind-Theme. Auf allen drei Seiten waren Eingaberand, Checkbox, Buttontext und Buttonrand sichtbar; der per Tastatur fokussierte Button zeigte einen 2-px-Outline. Danach wurden Prüfharness und Paketstände vollständig zurückgebaut: `composer.json`, `composer.lock`, `routes/web.php`, Git-Status und Binärdiff stimmen bytegenau mit dem gesicherten Ausgangszustand überein.

Prüfmatrix: `composer test` (113 Tests, 881 Assertions), `npm test` (7/7), `composer format:check` und `composer analyse` (37 Dateien, keine Fehler) sind grün. Es wurde weder gepusht noch getaggt. Das separat aufgeführte Mail-Link-Addendum war zu diesem Zeitpunkt offen und wurde anschließend in einer eigenen Übergabe abgeschlossen.

## Übergabe Task 6 Addendum — 2026-11-09

Bestätigungslinks werden nun für direkte und eingereihte Mails aus `APP_URL` plus relativem Named-Route-Pfad aufgebaut; der aktuelle Request-Host und der globale URL-Generator bleiben unverändert. Der neue Test lief zuerst für beide Versandwege rot und anschließend grün. `docs/installation.md` beschreibt `APP_URL` als verbindliche öffentliche Basis für Mail-Links und den Return-to-Source-Abgleich. Die Prüfmatrix ist grün: `composer test` (115 Tests, 891 Assertions), `npm test` (7/7), `composer format:check` und `composer analyse` (37 Dateien, keine Fehler). `CHANGELOG.md` bleibt bei 1.1.0; es wurde weder gepusht noch getaggt.
