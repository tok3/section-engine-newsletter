# Entwicklerschnittstellen

Namespace: `Tok3\SectionEngineNewsletter`. Laravel entdeckt `SectionEngineNewsletterServiceProvider`; das Panel registriert `SectionEngineNewsletterPlugin::make()` separat.

## Core-Anbindung

Der Provider registriert `NewsletterLiveTagProvider` über `LiveTagRegistry::register()`. Parsing, sicheres Einsetzen, Editor-Platzhalter und Verhalten bei fehlendem Paket gehören zum Core. Die Implementierung erhält `LiveTag` und `LiveTokenRenderContext` mit Locale, Editorflag, optionaler Seite und URL. `insertOptions()` liefert aktive Listen.

Die Admin-Oberfläche nutzt `LiveTagUsageFinder::usagesFor('newsletter', $key)` und `SectionEngineAccess::allows()`. Herkunftsseiten werden lazy über `config('section-engine.models.page')` einschließlich Soft Deletes aufgelöst; die historischen IDs werden nicht verändert. Es gibt keinen Seitenlöschungs-Observer.

## Domain-Events

Unter `Tok3\SectionEngineNewsletter\Events`:

- `SubscriptionRequested`: neues oder erneut angemeldetes Abonnement; öffentliche Property `subscription`.
- `SubscriptionConfirmed`: tatsächlicher Übergang Pending → Confirmed; `subscription`.
- `SubscriptionUnsubscribed`: tatsächlicher Übergang → Unsubscribed; `subscription`.
- `SubscriberDeleted`: endgültig gelöschter Teilnehmer; `subscriber` enthält den gerade gelöschten Modelzustand.

Events werden nach erfolgreichem Transaktions-Commit ausgelöst. Wiederholtes Bestätigen/Abmelden erzeugt keinen weiteren Zustandswechsel. Eine zulässige Pending-Mailwiederholung ist kein neuer Statuswechsel. Listener dürfen personenbezogene Daten nicht unbedacht dauerhaft kopieren; der Löschumfang des Pakets umfasst keine fremden Listener-Daten.

## Konfiguration

Alle Schlüssel stehen unter `section-engine-newsletter`:

| Schlüssel | Standard | Bedeutung |
| --- | --- | --- |
| `route_prefix` | `newsletter` | Präfix der öffentlichen Routen |
| `default_locale` | `null` | `null` übernimmt `section-engine.locales.default`; ein expliziter Wert überschreibt den Fallback der Listentexte |
| `mailer`, `queue` | `null` | Standard-Mailer, Versand nach HTTP-Antwort; Queue als Verbindungsname |
| `from.address`, `from.name` | `null` | Fallback auf `mail.from` |
| `confirmation_ttl_hours` | 48 | Bestätigungslink-Laufzeit |
| `context_ttl_hours` | 168 | Formularkontext-Laufzeit |
| `resend_cooldown_minutes` | 10 | Pending-Wiederholungssperre |
| `min_fill_seconds` | 3 | Mindestfüllzeit |
| `require_checkbox` | `true` | Pflicht-Checkbox |
| `validate_dns` | `false` | Optionaler DNS-Check zusätzlich zu RFC-E-Mail-Validierung |
| `rate_limits.per_ip` | 10 | Requests pro Minute/IP |
| `rate_limits.per_email` | 3 | Requests pro Stunde/normalisierter E-Mail und Liste |
| `prune.pending_days` | 30 | Löschfrist unbestätigter Abonnements |
| `prune.unsubscribed_proof_years` | 3 | IP-/User-Agent-Anonymisierung nach Abmeldung |
| `views.variants` | default/compact | Erlaubte Blade-Views |

Der verschlüsselte Kontext schützt Liste, Tag, Seiten-ID, URL, Locale, zufällige Formular-ID und Renderzeit. Zusätzlich enthält er den dargestellten `consent_snapshot` und einen Render-`instance`-Index für Rückmeldungen bei mehrfacher Einbindung. Kryptografische Unveränderbarkeit ersetzt weder CSRF noch eine korrekte Proxy-/IP-Konfiguration. Die Anwendung muss vertrauenswürdige Proxies passend konfigurieren, damit die vollständige Besucher-IP korrekt gespeichert wird.

## Routen und Antworten

Namen beginnen mit `section-engine-newsletter.`: `subscribe`, `confirm.show`, `confirm`, `unsubscribe.show`, `unsubscribe`, `unsubscribe.one-click` und der geschützte Nachweis `proof`. Die öffentlichen Formularrouten benutzen `web`; Abmeldungen verlangen eine Laravel-Signatur ohne Laufzeit. GET bestätigt/meldet niemals ab. RFC 8058 erwartet einen signierten POST mit `List-Unsubscribe=One-Click` und benötigt keinen CSRF-Token.

Mit `Accept: application/json`: Erfolg `{status:"success",message:"…"}`, Validierungsfehler HTTP 422. Alle gültigen Anmeldestatus und Rate-Limit-Fälle haben dieselbe Erfolgsantwort. Der Erfolg ersetzt nur die abgesendete Formularinstanz durch einen fokussierten Statushinweis. Ohne JS geschieht das nach dem Redirect zur Herkunfts-URL mit Formularanker und instanzgebundener Session-Rückmeldung. Ein belegter Honeypot oder zu schneller/ungültiger Kontext wird abgelehnt, ohne Teilnehmer anzulegen.

## Modell- und View-Erweiterung

Listen sind soft-deletable; Teilnehmer werden endgültig gelöscht. `SubscriptionStatus::label()` und `color()` definieren Statusdarstellung zentral. `SubscriptionEventType` enthält die Ereignistypen. Ereignisse lassen sich über Models nicht nachträglich ändern oder löschen; Retention und FK-Kaskaden sind bewusste Ausnahmen.

Views und Übersetzungen sind veröffentlichbar. Eigene Varianten über die View-Map registrieren. Die JavaScript-Erweiterung ist inline, wird pro Request einmal eingebunden und verwendet ausschließlich Browser-APIs. Verbraucher benötigen keinen Asset-Build.

Phase 2 ist nur ein Ausblick: Kampagnen, Versandjobs, Mail-Header für One-Click-Abmeldung, Zustellprotokolle und externe Integrationen sind nicht Bestandteil von v1.
