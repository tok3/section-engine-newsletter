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

1. **Tables** (prefix like core): `section_engine_newsletter_lists`, `section_engine_newsletter_subscribers`, `section_engine_newsletter_subscriptions`, `section_engine_newsletter_events`. Columns exactly as the spec "Data model"; bigint auto-increment ids; foreign keys: subscriptions → subscribers/lists `cascadeOnDelete`, events → subscriptions `cascadeOnDelete`, `source_page_id` without FK (`nullable`, page model is configurable). **Decision 2026-11-08 (resolves BLOCKED_BY_CORE_SEAM on page deletion):** `source_page_id` is a historical reference and is never cleared or rewritten when a page is deleted (core pages are soft-deleted; a later force delete only leaves a dangling id). `source_url` is the durable source reference in the consent proof. Filament resolves the page lazily via the configured page model (`config('section-engine.models.page')`, including trashed) and shows "Seite gelöscht" plus `source_url` when it no longer exists. No observer, no lifecycle hook, no core change. Unique (`subscriber_id`, `list_id`). Index on `status`, `tag`, `source_page_id`.
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
12. **Mail:** Mailable `ConfirmSubscriptionMail`, sent via `Mail::mailer(config('mailer'))` (`null` = default); queued if `config('queue')` is a non-empty connection name. **Decision 2026-11-09 (Task 12):** without a queue, deliver after the HTTP response via a terminating callback; transport/enqueue failures are reported and never alter the response (supersedes the original synchronous default). Locale = signup locale. Subject/intro from list overrides or translations.
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
- [x] Dispatch `SubscriptionRequested`, `SubscriptionConfirmed`, `SubscriptionUnsubscribed`, `SubscriberDeleted`. Prune command + schedule.
- [x] Tests: events dispatched once per transition; prune deletes old pending incl. events; anonymises IP/UA of old unsubscribed proof; leaves everything else untouched; schedule registered.
- [x] Commit `feat: dispatch newsletter events and prune stale data`

### Task 7 – Filament: lists
- [x] `NewsletterListResource`: CRUD, counts per status, consent texts per locale, "embedded on" via `LiveTagUsageFinder`, copy-snippet action.
- [x] Tests: access only with `SectionEngineAccess`; counts; usage list; validation (key format `[a-z0-9_-]`, unique).
- [x] Commit `feat: manage newsletter lists in filament`

### Task 8 – Filament: subscribers
- [x] `SubscriberResource`: table (email, list status chips, tags, source page link, dates), filters (list, status, tag, source page, date range), detail with full event history, actions: CSV export (respects filters), consent proof view, manual unsubscribe (`admin_action` event), permanent delete (confirmation, removes all rows), "add manually" = create pending + send confirmation mail. Dashboard widget signups last 30 days per list/tag.
- [x] Tests for every action incl. CSV content, hard delete leaves no rows, manual add never confirms directly.
- [x] Commit `feat: manage newsletter subscribers in filament`

### Task 9 – Translations and accessibility pass
- [x] Complete `de`/`en`. Review form against AGENTS.md accessibility rules; fix and test focus/aria behaviour in a JS test (jsdom) for the progressive enhancement.
- [x] Commit `feat: complete newsletter translations and accessibility`

### Task 10 – Documentation
- [x] `README.md`, `docs/installation.md`, `docs/embedding.md` (end-user guide with examples), `docs/admin.md`, `docs/privacy.md` (stored data, retention, consent proof, privacy-policy building blocks, "not legal advice"), `docs/developer.md` (events, config, extension points, Phase 2 outlook). `CHANGELOG.md` `Unreleased` entries.
- [x] Commit `docs: document section engine newsletter`

### Task 11 – Verification and local integration check
- [x] Full matrix: `composer test`, `composer format:check`, `composer analyse`, JS tests.
- [x] Local integration in `~/Sites/leibnizer-dev`, **uncommitted, to be reverted afterwards**: path repository to `../section-engine-newsletter` (symlink), require `dev-main`, migrate, register plugin in the panel, `MAIL_MAILER=log`. Embed `{{ newsletter:test tag=probe }}` on a test page, sign up, confirm via link from the log, unsubscribe; check Filament lists/subscribers and event history. Then revert all `leibnizer-dev` changes and confirm `git status` there matches the state before.
- [x] Handoff note at the end of this file: what was built, deviations with reason, open issues, exact consumer changes needed later (composer, panel registration, migrations, env). Do not mark complete if any test was skipped or weakened.

## Review Focus (for the Claude review after the build – not a self-check)

- Consent proof complete and immutable for every transition.
- No address enumeration via responses, timing differences or rate limiting.
- GET never changes state; signatures and tokens enforced.
- Only documented core APIs used; no core changes.
- Accessibility of the form; works without JS.
- Leibnizer integration fully reverted.

## Übergabenotiz – Build abgeschlossen (2026-11-08)

Tasks 0–11 sind der Reihe nach umgesetzt, test-first für Produktionsänderungen und mit einem Commit pro Task. Der frühere Blocker ist durch die ausdrücklich freigegebene Decision 2026-11-08 aufgehoben. `source_page_id` bleibt historisch erhalten; Filament löst das konfigurierte Page-Modell lazy einschließlich archivierter Seiten auf. Bei fehlender Seite erscheint „Seite gelöscht“ samt dauerhafter `source_url`. Kein Observer und keine Core-/Editorial-Änderung.

### Gebaut

Composer-Paket mit Testbench/Workbench, Schema/Modellen/Factories/Enums, Newsletter-Live-Tag und zugänglichen Formularen, verschlüsseltem Kontext und Einwilligungssnapshot, generischen Antworten, Rate Limits/Spam-Schutz, Double Opt-in, Laravel-Mailer und optionaler Queue, GET/POST-Trennung, signierter Abmeldung und RFC-8058-POST. Append-only-Modellschutz, vollständige IP-/User-Agent-Ereignisse, Domain-Events und tägliche Retention. Filament-Listen und Teilnehmer mit Statuschips, allen Filtern, gefiltertem CSV-Download, druckbarem Einwilligungsnachweis, vollständiger Ereignishistorie, manueller Bestätigungsanforderung, Admin-Abmeldung, endgültiger Löschung und 30-Tage-Widget. Deutsch/Englisch, optionale Vanilla-JS-Erweiterung sowie sämtliche öffentlichen Dokumentationsdateien. Kein Kampagnenversand und keine Drittanbieter-Anbindung.

### Verifikation

- Paket: PHP 8.3.30, Laravel 13.40.0, Filament 5.11.2, Core 1.1.0, Testbench 11.3.0.
- `composer test`: **89 Tests / 582 Assertions**, erfolgreich.
- `composer format:check`: erfolgreich.
- `composer analyse`: erfolgreich, ohne Baseline oder neue Fehlerunterdrückungen.
- `npm test`: **5 jsdom-Tests**, erfolgreich, keine übersprungenen Tests.
- Composer-Archiv geprüft: interne Dokumente unter `docs/superpowers/**` und `.superpowers/**` sind ausgeschlossen.
- Lokale Verbraucher-Integration in `~/Sites/leibnizer-dev`: **1 automatisierter Integrationstest / 42 Assertions**, erfolgreich, mit Laravel 13.39.0, Filament 5.11.0 und Core 1.1.0. Path-Repository/Symlink, Require `dev-main`, veröffentlichte Migration, registriertes Panel-Plugin, vollständige Migration der isolierten SQLite-In-Memory-Datenbank, echte veröffentlichte Testseite mit `{{ newsletter:test tag=probe }}`, Signup, Bestätigung über den aus der tatsächlichen Log-Mail extrahierten Link, Abmeldung sowie Filament-Listen/Teilnehmer und Nachweis geprüft. CSRF-Schutz wurde ausdrücklich aktiviert: POST ohne Token liefert 419, POST mit Token funktioniert. Keine Mail-Fakes im Integrationstest und kein externer Mailversand.
- Die vorgegebene Prüfmatrix wurde vollständig ausgeführt; kein erforderliches Szenario übersprungen oder abgeschwächt. Weitere PHP-/Laravel-Versionskombinationen wurden nicht zusätzlich ausgeführt. Es fand kein manueller Browser-/Screenreader-Durchlauf statt; die UI-Prüfungen erfolgten über den echten Anwendungskernel/Livewire und jsdom.

### Ergänzungen und Ablaufabweichungen

- Der verschlüsselte Kontext enthält zusätzlich `consent_snapshot`, damit Änderungen an Listentexten zwischen Darstellung und Absenden keinen falschen Nachweis erzeugen, und `instance` für Rückmeldungen bei mehrfach eingebundenen identischen Formularen. Die vorgegebenen Felder und eine neue zufällige `form_id` pro Darstellung bleiben erhalten.
- Das statische Script-Flag ist über eine WeakMap an den Request gebunden, damit es auch in langlebigen PHP-Prozessen einmal pro Seite gilt.
- CSV wird als StreamedResponse ausgegeben, weil die echte Livewire-Download-Prüfung zeigte, dass eine normale Response keinen Dateidownload auslöst.
- Für die lokale Integration wurde die reale Verbraucher-Anwendung mit einer isolierten SQLite-In-Memory-Datenbank geprüft. Die konfigurierte MySQL-Entwicklungsdatenbank wurde nicht verändert. Ein erster erfolgreicher Anwendungsdurchlauf scheiterte beim Test-Abbau an der bereits vorhandenen Core-Migration `2026_11_01_072027_add_translation_group_to_section_engine_pages_table.php`: SQLite kann `translation_group` nicht entfernen, solange der Index besteht. Keine Änderung daran; der abschließende Test verwendet vollständig `migrate:fresh` und verwirft die flüchtige Datenbank beim Prozessende, ohne fremde Core-Migrationen rückwärts auszuführen. Sämtliche Anwendungsassertions blieben erhalten.
- Ein anfänglicher Pfadfehler verhinderte den ersten Sicherungslauf. Die zwei Änderungen des darauf folgenden Composer-Dry-Runs an Composer-Konfiguration und Panel wurden gezielt entfernt und der Git-Status mit dem zuvor gelesenen Stand abgeglichen. Danach wurde eine vollständige Dateisicherung angelegt. Der eigentliche Installations-/Integrationstest wurde gegen diese Sicherung ausgeführt und vollständig zurückgesetzt. Die erste Composer-Datei wurde dabei aus ihren ursprünglichen JSON-Werten rekonstruiert; eine mögliche reine Whitespace-Abweichung vor dieser Sicherung lässt sich nicht bytegenau nachweisen.

### Rückbau des Verbrauchers

Temporären Test, veröffentlichte Newsletter-Migration und Paket-Symlink entfernt. Composer-Dateien, Composer-Autoload-/Installed-Metadaten, Panel-Datei, Paket-/Service-Caches, kompilierte Views, Logs und PHPUnit-Cache aus der Sicherung wiederhergestellt. Danach `git status --porcelain` identisch zur gesicherten Ausgangslage; Prüfsummen sämtlicher erfassten getrackten/ungetrackten Dateien sowie der gesicherten Laufzeitverzeichnisse identisch. Vorhandene lokale Änderungen und Asset-Symlinks wurden beibehalten. Keine Testdaten in MySQL; die Testdatenbank war ausschließlich `:memory:`.

### Offene Punkte und Übergabe an Claude

Keine offenen Implementierungsblocker. Die Prüfung gegen **Review Focus** wurde wie beauftragt nicht durchgeführt und bleibt bei Claude. Der oben genannte bestehende SQLite-Rollbackfehler gehört zum Core/Verbraucher und wurde nicht behoben. Die Retention löscht entsprechend dem Plan nur alte Pending-Abonnements mit ihren Ereignissen; verbleibende Teilnehmer-Stammdatensätze werden erst über Erasure gelöscht. Diese Grenze ist in `docs/privacy.md` ausdrücklich dokumentiert.

### Exakte spätere Verbraucheränderungen

Nach der separaten Review die private Composer-Quelle hinzufügen und `tok3/section-engine-newsletter` in einer verfügbaren geprüften Version anfordern. Für erneute lokale Tests das Path-Repository `../section-engine-newsletter` mit `options.symlink=true` und `composer require tok3/section-engine-newsletter:dev-main` verwenden. Core `^1.1.0`, Filament 5 und PHP 8.3+ voraussetzen. Im vorhandenen Panel zusätzlich `Tok3\SectionEngineNewsletter\SectionEngineNewsletterPlugin::make()` registrieren. `php artisan vendor:publish --tag=section-engine-newsletter-migrations` und `php artisan migrate` ausführen. Optional Konfiguration/Views/Übersetzungen veröffentlichen. Lokal `MAIL_MAILER=log`; später den tatsächlichen Laravel-Mailer, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` und `APP_URL` konfigurieren. Falls `section-engine-newsletter.queue` gesetzt wird, den Worker für diese Verbindung betreiben. Den Laravel-Scheduler minütlich über `php artisan schedule:run` ausführen. Aktive Listen mit Einwilligungstext mindestens für `default_locale` anlegen, dann die Live-Tags einbinden. Verbraucher benötigen keinen npm- oder Build-Schritt.

Alle Änderungen bleiben lokal auf `main`. Kein Push, kein Tag.

## Claude review — 2026-11-09

**Result:** Tasks 0–11 match plan and spec. One defect against Review Focus ("no address enumeration via … timing differences") must be fixed before the first release tag.

Checked: 12 commits (one per task), proof events append-only on model level, GET confirm/unsubscribe never change state, signatures/tokens (`hash_equals`, sha256), proof route behind `ManageSubscribers::authorize()`, identical responses incl. rate limit path, only documented core APIs (+ explicitly allowed `section-engine.models.page`), `source_page_id` decision implemented, `leibnizer-dev` fully reverted (only the pre-existing local Editorial changes remain; `composer.json` differs from the pre-test state only by re-expanded `keywords` whitespace, cosmetic and uncommitted). JS suite re-run by Claude: 5/5. PHP suite not re-run by Claude (no PHP in review environment); Codex results accepted.

### Defect: timing side channel and error leak on signup

`RequestSubscription::handle()` sends the confirmation mail synchronously (`DB::afterCommit` → `Mail::send`) when no queue is configured — the default. Mail is only sent for new / resubscribed / cooled-down pending addresses, never for confirmed ones. Consequences:

- Response time reveals whether an address is already confirmed on a list (SMTP roundtrip vs. none).
- An SMTP failure throws inside the request → HTTP 500 only for not-yet-confirmed addresses, also revealing state and breaking the "identical response" rule.

### Task 12 – Deliver confirmation mail outside the request

- [x] Without queue: send the mail after the response has been sent (`dispatch(...)->afterResponse()` or an equivalent terminating callback), never inside the request lifecycle. With queue: unchanged.
- [x] Mail failures (both paths) are caught and `report()`ed; they never change the HTTP response. No `confirmation_sent` event when sending failed.
- [x] Tests: response is identical (status, body, headers except cookies) for new, pending, confirmed and rate-limited cases while a mailer that throws is bound; no mail is sent before the response in sync mode; failure is reported and leaves the subscription `pending` with its `requested` event intact.
- [x] `docs/privacy.md` / `docs/installation.md`: recommend a queue in production; explain that without a queue the mail is sent after the response.
- [x] Commit `fix: send confirmation mail outside the signup request`
- [x] Re-run full matrix; append a short note below.


### Task 12 – Abschlussnotiz 2026-11-09

Ohne konfigurierte Queue wird der Versand nach dem Commit einmalig für die Anwendungsterminierung nach der HTTP-Antwort registriert. Mailer-/Transportfehler und Fehler beim Queue-Einreihen werden abgefangen und über `report()` gemeldet; die öffentliche Antwort sowie Pending-Status und Anforderungsnachweis bleiben erhalten. `confirmation_sent` entsteht weiterhin nur nach tatsächlicher Übergabe an den Mailer. Queue-Verbindung und Laravel-Worker-Verhalten bleiben unverändert. Installation, Datenschutz, Entwickler-Konfiguration und Changelog sind aktualisiert; für Produktion wird eine dauerhafte Queue empfohlen.

Test-first: sechs neue Regressionstests zunächst mit frühem Transportaufruf bzw. HTTP 500 rot, danach grün. Die Tests trennen `Kernel::handle()` von `terminate()` und prüfen JSON/Redirects bei neuen, offenen, bestätigten und rate-limitierten Anmeldungen mit tatsächlich werfendem Mailtransport, außerdem Queue-Backend-Ausfall, Fehlermeldung und unveränderte IP-/User-Agent-Nachweise. Cookies werden beim Headervergleich ausgenommen; der naturgemäß anfrageabhängige HTTP-`Date`-Wert wird separat auf sein Format geprüft und für den Vergleich normalisiert.

Vollständige Prüfmatrix erfolgreich: `composer test` **95 Tests / 722 Assertions**, `composer format:check`, `composer analyse` und `npm test` **5/5** ohne übersprungene Tests. Keine Core-/Editorial-/Verbraucheränderung. Kein Push, kein Tag.
