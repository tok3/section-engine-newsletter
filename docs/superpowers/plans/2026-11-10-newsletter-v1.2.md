# Section Engine Newsletter 1.2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Read `AGENTS.md`. Test-first, one commit per task.

**Goal:** Clear visitor feedback after signup, a usable list editor that follows the site's configured languages, and ready-made default texts in German, English and Spanish.

**Base:** `main` at `v1.1.0`. Core `^1.3.0` unchanged.

## Decisions by Tobias (2026-11-10)

- After submitting, the form is replaced by an unambiguous notice: the visitor must confirm via the e-mail.
- Every accepted signup request sends a confirmation mail – also for addresses already confirmed on that list. Status of a confirmed subscription never changes through this; re-confirming is harmless. This keeps the notice truthful for everyone without revealing who is subscribed.
- List texts only for the languages the site actually uses; default texts prefilled for de, en, es.
- The form, mail and pages appear in the language of the page the visitor signed up on.

## Global Constraints

- Only this repository, only documented core APIs.
- Backward compatible: existing routes, tables, config keys keep working; migrations additive only.
- Anti-enumeration rules stay intact (identical responses, mail outside the request, rate limits, cooldown).
- No push, no tag. Stop and report on contradictions.

## Fixed Decisions

1. **Re-request of a confirmed subscription:** if outside the resend cooldown, issue a new confirmation token and send the normal confirmation mail; status stays `confirmed`, confirmation timestamps/IPs unchanged. Event `requested` with `meta.already_confirmed = true`. POST confirm on an already confirmed subscription: no state change, no new `confirmed` event, shows the normal confirmed result / return-to-source flow. Within cooldown: nothing sent, identical response (as today).
2. **Success notice after signup** (JS and no-JS): the form for that instance is replaced by a notice (`role="status"`, focus moved to it). Default texts:
   - de: „Fast geschafft! Wir haben dir eine E-Mail geschickt. Bitte bestätige deine Anmeldung über den Button darin – erst dann bist du eingetragen. Keine E-Mail erhalten? Schau auch im Spam-Ordner nach."
   - en: "Almost done! We've sent you an email. Please confirm your subscription using the button in it – only then are you subscribed. No email? Please also check your spam folder."
   - es: "¡Casi listo! Te hemos enviado un correo electrónico. Confirma tu suscripción con el botón que contiene; solo entonces quedarás suscrito. ¿No has recibido el correo? Revisa también la carpeta de spam."
   Per-list override `success_text` (JSON per locale, nullable) via additive migration.
3. **Languages:** the list editor uses the keys of `config('section-engine.locales.available')`; the required/fallback language is `config('section-engine.locales.default')`. Package config `default_locale` defaults to `null` (= core default); an explicitly set value keeps working (backward compatible). Fallback chain for texts: page locale → default locale → package translation.
4. **List editor layout (Filament):**
   - Section „Liste": key, name, active, description.
   - Section „Texte":
     - one site language → plain fields without language suffix (stored under that locale key);
     - several languages → `Tabs`, one tab per configured language, label = language name from `locales.available`; only the default-language consent text is required.
     - Fields per language: consent text, success text, confirmation subject, confirmation text.
   - Section „Eingebunden auf" (only when editing): list of pages with edit links via `LiveTagUsageFinder`; empty state text.
   - Helper texts explain each field briefly.
5. **Prefilled defaults:** on create, all text fields for every configured site language are prefilled from package translations (de, en, es shipped; other languages fall back to en). Editors can change them; saved values are stored as normal.
6. **Spanish:** add complete `es` translations for frontend, pages, mail and admin.
7. **Language of output:** form, notices, confirmation mail and theme pages use the signup page locale (already captured in the form context); verify with tests for de/en/es.

## Tasks

### Task 1 – Always send confirmation mail on accepted requests
- [x] Decision 1. Tests: confirmed + outside cooldown → mail sent, status confirmed, timestamps unchanged, event meta; within cooldown → nothing sent; POST confirm on confirmed → no new event; responses still identical across all cases (existing anti-enumeration tests stay green).
- [x] Commit `feat: resend confirmation mail for existing subscriptions`

### Task 2 – Success notice
- [x] Decision 2 incl. migration `add_success_text_to_section_engine_newsletter_lists_table`.
- [x] Tests: no-JS redirect shows notice instead of that form instance only; JSON mode returns the text and jsdom replaces the form and focuses the notice; per-list override and locale fallback.
- [x] Commit `feat: clear success notice after newsletter signup`

### Task 3 – Languages from Section Engine and list editor layout
- [x] Decisions 3 and 4.
- [x] Tests: single locale → no tabs, no suffixes; three locales → three tabs with names; required only for default locale; explicit package `default_locale` still respected; "Eingebunden auf" only on edit.
- [x] Commit `feat: list editor follows site languages`

### Task 4 – Default texts and Spanish
- [x] Decisions 5, 6, 7.
- [x] Tests: create form prefilled for de/en/es; unknown site language falls back to en texts; mail, notice and pages render in es when signing up on an es page.
- [x] Commit `feat: default list texts in german, english and spanish`

### Task 5 – Docs and release prep
- [x] `docs/admin.md` (list editor, languages, defaults), `docs/embedding.md` (notice, language behaviour), `docs/privacy.md` (repeated confirmation mails). CHANGELOG `## 1.2.0 - <date>`, commit `chore: release 1.2.0` (no tag).
- [x] Full matrix; manual check in `~/Sites/leibnizer` is **not** allowed (live data) – use the Workbench and, if needed, `~/Sites/leibnizer-dev` locally with full revert as in earlier tasks.
- [x] Handoff note at the end of this file. No push, no tag.

## Review Focus (Claude review after the build)

- Anti-enumeration still holds with the new resend behaviour.
- Confirmed subscriptions never downgraded.
- Editor: only site languages, tabs when multilingual, defaults prefilled.
- es output end to end.

## Übergabenotiz — Build abgeschlossen (2026-11-10)

Tasks 1–5 sind in der vorgegebenen Reihenfolge und mit je einem eigenen Commit umgesetzt. Produktionsänderungen wurden jeweils zuerst durch einen fehlschlagenden Test abgesichert. Die Fixed Decisions wurden unverändert übernommen; es war keine zusätzliche Core-Schnittstelle nötig.

### Gebaut

- Bereits bestätigte Abonnements erhalten außerhalb des Cooldowns einen neuen Bestätigungslink, ohne Status oder ursprünglichen Bestätigungsnachweis zu verändern; die öffentlichen Antworten bleiben identisch.
- Nach erfolgreichem Absenden ersetzt ein fokussierter Statushinweis nur die betreffende Formularinstanz, mit und ohne JavaScript. `success_text` ist über eine additive Migration pro Liste und Locale überschreibbar.
- Der Filament-Listeneditor folgt `section-engine.locales.available`, verlangt den Einwilligungstext der Core-Standardsprache, verwendet bei mehreren Sprachen benannte Tabs und zeigt Einbindungen nur beim Bearbeiten über `LiveTagUsageFinder`.
- Neue Listen sind für `de`, `en` und `es` vollständig vorbefüllt; unbekannte Website-Sprachen erhalten englische Texte. Formular, Hinweis, Mail und Theme-Seiten verwenden die Locale der Anmeldeseite. Spanische Frontend-, Mail-, Seiten- und Admintexte sind vollständig enthalten.
- Öffentliche Dokumentation und `CHANGELOG.md` sind für `1.2.0 - 2026-11-10` aktualisiert.

### Verifikation

- `composer validate --strict`: erfolgreich.
- `composer test`: **139 Tests / 1072 Assertions**, erfolgreich.
- `npm test`: **7/7**, keine übersprungenen Tests.
- `composer format:check`: erfolgreich.
- `composer analyse`: **38 Dateien**, keine Fehler und keine neue Unterdrückung.
- Composer-Archiv: **105 Dateien**; `docs/superpowers/**`, `.superpowers/**` und `AGENTS.md` sind ausgeschlossen.
- Die Filament-/Livewire-Prüfungen laufen über den registrierten Testbench-Workbench-Provider. Eine zusätzliche Verbraucherprüfung war nicht erforderlich. `~/Sites/leibnizer` und `~/Sites/leibnizer-dev` wurden nicht verändert.

Kein Push und kein Tag. Es bestehen keine Implementierungsblocker. Die Prüfung gegen **Review Focus** wurde wie beauftragt nicht durchgeführt und bleibt bei Claude.

## Claude review — 2026-11-10

**Result:** Tasks 1–5 match the plan (notice, site languages with tabs, de/en/es defaults, es output). JS suite re-run by Claude: 7/7. PHP not re-run by Claude. One defect in Task 1 must be fixed before tagging 1.2.0.

### Defect: re-request overwrites the data of a confirmed subscription

For an already confirmed subscription, `RequestSubscription::handle()` keeps status and `confirmed_*`, but still overwrites `tag`, `source_page_id`, `source_url`, `source_locale`, `consent_text_snapshot`, `consent_hash`, `requested_at`, `requested_ip`, `requested_user_agent` with the data of the new request. Anyone can submit a known address again, so a third party can rewrite the row of someone else's confirmed subscription: tag/source statistics change, and the row's consent snapshot and request IP no longer belong to the confirmation. The append-only events still hold the truth, but the subscription row must stay consistent with the confirmation it represents.

### Task 6 – Keep confirmed subscriptions untouched on re-request

- [x] For `confirmed` subscriptions a re-request outside cooldown changes **only** `confirmation_token_hash`, `confirmation_expires_at` and a new cooldown marker. All other columns stay as they were. The new request is fully recorded in its `requested` event (already the case).
- [x] Cooldown marker: additive migration `add_last_requested_at_to_section_engine_newsletter_subscriptions_table` (nullable timestamp, backfilled from `requested_at`). Cooldown for every status uses `last_requested_at` (fallback `requested_at`). Pending/unsubscribed flows update `last_requested_at` together with `requested_at`.
- [x] Tests: confirmed row unchanged except token/expiry/`last_requested_at` after a re-request with different tag, page, IP and consent text; cooldown applies to confirmed re-requests; pending/unsubscribed behaviour unchanged; identical responses.
- [x] CHANGELOG stays `1.2.0`. Commit `fix: keep confirmed subscriptions unchanged on re-request`. Full matrix. No push, no tag.

## Übergabenotiz — Task 6 abgeschlossen (2026-11-10)

Task 6 wurde test-first umgesetzt. Die additive Migration ergänzt `last_requested_at` und übernimmt für bestehende Datensätze den Wert aus `requested_at`. Bestätigte Abonnements behalten bei einer erneuten Anmeldung sämtliche bisherigen Herkunfts-, Einwilligungs-, Anfrage-, Bestätigungs- und Zeitstempeldaten; geändert werden ausschließlich Bestätigungstoken, Ablaufzeit und Cooldown-Marker. Die neue Anfrage bleibt mit Tag, Seite, URL, IP, User-Agent, Einwilligungstext und Locale vollständig im unveränderlichen `requested`-Event dokumentiert. Pending- und Resubscribe-Abläufe aktualisieren `requested_at` und `last_requested_at` gemeinsam; für Altdaten greift der Cooldown auf `requested_at` zurück.

Vollständige Prüfmatrix: `composer validate --strict` erfolgreich; `composer test` **141 Tests / 1105 Assertions**; `npm test` **7/7**; `composer format:check` erfolgreich; `composer analyse` **38 Dateien**, keine Fehler; `git diff --check` erfolgreich. `CHANGELOG.md` bleibt unverändert bei 1.2.0. Kein Push, kein Tag.

## Claude re-review — 2026-11-10 (commit 847557f)

**Result: approved for release v1.2.0.** Confirmed rows change only token, expiry and `last_requested_at` (timestamps suppressed); the full new request lives in its event. Cooldown uses `last_requested_at` with fallback. Removed/rewritten assertions in `ConfirmationTest`/`SubscribeTest` reflect the intended new behaviour (confirmed `requested_at` must no longer move), not a weakening. JS 7/7 re-run by Claude.
