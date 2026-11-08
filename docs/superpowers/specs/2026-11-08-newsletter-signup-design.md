# Section Engine Newsletter – Signup Collection Design

**Status:** Approved by Tobias on 2026-11-08
**Date:** 2026-11-08
**Package:** `tok3/section-engine-newsletter` (namespace `Tok3\SectionEngineNewsletter`)
**Repository:** `git@github.com:tok3/section-engine-newsletter.git`, local `~/Sites/section-engine-newsletter`
**Depends on:** `tok3/section-engine` `^1.1.0` (Live Tag Providers, see core `docs/live-tag-providers.md`)

Origin: Part 2 of the Section Engine spec `docs/superpowers/specs/2026-11-08-newsletter-signup-design.md` in the core repo. Part 1 (core Live Tag Providers) is released as core v1.1.0.

## Intent

Collect newsletter signups anywhere on a Section Engine site through one embeddable live tag, with a legally robust double opt-in, and manage lists and subscribers in Filament. Version 1 only collects; no sending.

## Decisions (confirmed by Tobias, 2026-11-08)

- Every signup belongs to exactly one **list** and may carry one free **tag**.
- The **source page** of every signup is recorded.
- **IP addresses are stored in full** (request and confirmation) as consent proof.
- Confirmation mails use the application's default Laravel mailer unless a package mailer is configured.
- Version 1 only collects. No sending, no third-party sync.
- Consent checkbox is **required by default** (`require_checkbox = true`), configurable.
- Documentation is a release artifact, including end-user embedding documentation.
- No changes to Section Engine core from this repository. A missing core seam is reported, not patched here.

## Non-goals for Version 1

- No campaign/bulk sending (Phase 2).
- No Mailchimp/Brevo/etc. sync.
- No CSV import of addresses.
- No subscriber self-service profile page beyond confirm/unsubscribe.
- No multi-tenant behaviour.
- No Leibnizer-specific copy, branding, list keys, or URLs in package code.

# Package design

## Embedding (end-user facing)

```text
{{ newsletter:leibnizer }}
{{ newsletter:leibnizer tag=hayek-serie }}
{{ newsletter:leibnizer tag=hayek-serie variant=compact }}
```

- argument = list key (must exist and be active; otherwise empty in frontend, marked in design mode)
- `tag` (optional): free value, normalised to `[a-z0-9_-]{1,64}`
- `variant` (optional): `default` | `compact` (maps to Blade views; projects can add own variants)

The form posts a **signed context** (list key, tag, page id, page URL, locale, render timestamp)
encrypted with the app key, so visitors cannot manipulate list, tag or source.

## Data model

`newsletter_lists`
- `id`, `key` (unique), `name`, `description` (nullable)
- `consent_text` (JSON per locale, required for at least the default locale)
- `confirmation_subject` / `confirmation_intro` (JSON per locale, nullable → package defaults)
- `active` (bool), timestamps, soft deletes

`newsletter_subscribers`
- `id`, `email` (unique, stored lowercased/trimmed), `locale`, timestamps
- **Hard delete** only (GDPR erasure) – deliberately different from core soft delete.

`newsletter_subscriptions` (one row per subscriber × list, unique pair)
- `id`, `subscriber_id`, `list_id`
- `status`: `pending` | `confirmed` | `unsubscribed`
- `tag` (nullable), `source_page_id` (nullable, null on page delete), `source_url`, `source_locale`
- `consent_text_snapshot` (exact text shown), `consent_hash`
- `requested_at`, `requested_ip`, `requested_user_agent`
- `confirmed_at`, `confirmed_ip`, `confirmed_user_agent`
- `unsubscribed_at`, `unsubscribed_ip`
- `confirmation_token_hash` (sha256), `confirmation_expires_at`
- timestamps

`newsletter_subscription_events` (append-only audit trail = consent proof)
- `id`, `subscription_id`, `type` (`requested`, `confirmation_sent`, `confirmed`, `unsubscribed`,
  `resubscribe_requested`, `admin_action`), `tag`, `source_page_id`, `source_url`, `ip`,
  `user_agent`, `meta` (JSON), `created_at`

## Signup flow (double opt-in)

1. **POST** `/{prefix}/subscribe` (route prefix configurable, default `newsletter`).
   Routes must be registered so they stay reachable next to the core page fallback route.
   - Middleware: `web` (CSRF), rate limit per IP and per email (configurable).
   - Spam protection: honeypot field + minimum fill time from the signed render timestamp (default 3 s).
   - Optional consent checkbox (`require_checkbox`, default `true`).
   - Validation: RFC email; optional DNS check configurable (default off).
2. Cases:
   - new address or `unsubscribed` → subscription `pending`, new token, mail sent
   - already `pending` → resend at most once per N minutes (default 10), token renewed
   - already `confirmed` → nothing sent
   - **Every case returns the identical response** (no address enumeration).
3. Response: without JS → redirect back to the form anchor with a flash message bound to the form
   instance; with JS → JSON, message announced via `aria-live`.
4. **Confirmation link** `GET /{prefix}/confirm/{subscription}/{token}` shows a page with a
   **confirm button (POST)**. Confirmation only happens on POST, because mail security scanners
   (e.g. Outlook Safe Links) open GET links automatically and would otherwise confirm without the person.
   Token valid 48 h (configurable); expired → page offers to request a new mail.
5. **Unsubscribe**: signed URL per subscription (no expiry), GET page + POST action. A one-click
   POST endpoint (RFC 8058) is prepared for Phase 2 mail headers.
6. Events `SubscriptionRequested`, `SubscriptionConfirmed`, `SubscriptionUnsubscribed`,
   `SubscriberDeleted` are dispatched (for Phase 2 sending and external integrations).

## Mail

- `ConfirmSubscriptionMail` sent via `Mail::mailer(config('section-engine-newsletter.mailer'))`;
  `null` = application default mailer.
- Queued when `queue` config is set, otherwise sent synchronously.
- Sent in the page locale; texts translatable (`de`, `en` shipped), Blade views publishable.
- Sender name/address default to `mail.from`, overridable in config.

## Retention

- `pending` subscriptions never confirmed: deleted with their events after N days (default 30).
- `unsubscribed`: proof data (IPs, timestamps, consent snapshot) kept for a configurable period
  (default 3 years), then IP/user-agent fields anonymised.
- Scheduled command `newsletter:prune` registered by the package; also runnable manually.
- The docs state what is stored and for how long and provide neutral building blocks for the
  operator's privacy policy, with the explicit note that this is not legal advice.

## Frontend form

- Blade views `section-engine-newsletter::form.default` / `form.compact`, publishable.
- Bootstrap 5 classes by default, no build step, works without JavaScript.
- Optional progressive enhancement: small inline script (included once per page) that submits via fetch.
- Accessibility is mandatory: visible label, error text linked via `aria-describedby`,
  status via `aria-live="polite"`, focus management after submit, sufficient target sizes,
  no placeholder-only labels.
- Editor preview renders the form disabled with a short "Newsletter: list X, tag Y" hint.

## Filament

`SectionEngineNewsletterPlugin` (registered in the panel like `SectionEnginePlugin`):

- **Lists**: CRUD; columns with counts confirmed / pending / unsubscribed; "embedded on" from
  `LiveTagUsageFinder`; copy-button for the embed snippet.
- **Subscribers**: email, lists with status chips, tags, source page (link), dates.
  Filters: list, status, tag, source page, date range.
  Detail view shows the full event history (consent proof).
  Actions:
  - export CSV (respects filters)
  - export consent proof per subscriber (PDF or printable view)
  - unsubscribe manually (logged as `admin_action`)
  - delete permanently (GDPR erasure, confirmation required)
  - **add manually** only as "send confirmation mail" (creates `pending`), never as directly confirmed
- Status colours defined once centrally, used everywhere as chips.
- Dashboard widget: signups last 30 days per list and per tag.
- Labels in `de` and `en`.

## Config (`config/section-engine-newsletter.php`)

`route_prefix`, `mailer`, `queue`, `from`, `confirmation_ttl_hours`, `resend_cooldown_minutes`,
`min_fill_seconds`, `require_checkbox`, `rate_limits`, `prune.pending_days`,
`prune.unsubscribed_proof_years`, `views.variants`.

## Package documentation (release artifact)

- `README.md` – what it does, requirements, quick start
- `docs/installation.md` – composer, migrations, panel plugin, scheduler, mailer
- `docs/embedding.md` – **end-user guide**: tag syntax, list and tag rules, variants, examples,
  styling/overriding views, what visitors see, troubleshooting
- `docs/admin.md` – lists, subscribers, filters, export, consent proof, deletion
- `docs/privacy.md` – stored data, retention, consent proof, privacy-policy building blocks (not legal advice)
- `docs/developer.md` – events, extension points, Phase 2 outlook

## Package tests (minimum)

- Tag rendering: valid list, inactive/unknown list, tag normalisation, variants, editor preview.
- Signed context cannot be tampered with.
- Honeypot and minimum fill time reject bots; rate limits apply.
- All signup cases produce identical responses; correct mail behaviour per case.
- GET confirm does not confirm; POST confirms; expired token handled.
- Unsubscribe via signed URL; resubscribe requires new double opt-in.
- Events written with IP/user agent for every step.
- Prune command deletes/anonymises per config.
- Filament: list counts, filters, CSV export, manual add sends confirmation, hard delete removes all data.
- Removing the package leaves pages rendering (core unknown-prefix behaviour).

## Phase 2 (outlook, not part of this spec)

Campaigns per list (optionally filtered by tag), body composed in Filament, sending via configured
SMTP mailer as chunked queue jobs with throttling, per-recipient delivery log, `List-Unsubscribe`
and `List-Unsubscribe-Post` headers, bounce handling.

