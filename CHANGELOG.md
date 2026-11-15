# Changelog

## 1.4.0 - 2026-11-15

- Der Hinweis nach dem Absenden nennt die eingegebene Adresse („Wir haben dir eine E-Mail an … geschickt.“), dezent hervorgehoben (`mark.sen-newsletter__email`, escaped, kein Link), und bietet „Andere Adresse eingeben“ an: mit JavaScript kehrt das Formular mit der bisherigen Eingabe zurück, ohne JavaScript führt der Link zum Formular-Anker `newsletter-<liste>-<n>`. Die Adresse steht nur in der Antwort bzw. der einmaligen Session-Rückmeldung, nie in einer URL; solche Antworten senden `Cache-Control: no-store, private` (neue Middleware in der `web`-Gruppe). JSON enthält zusätzlich `email` und `change`. Listentexte können die Adresse mit `:email` einsetzen; bestehende Texte ohne Platzhalter bleiben unverändert.

## 1.3.0 - 2026-11-15

- Bestätigungs-, Ergebnis- und Abmeldeseiten zeigen eine kleine Überschrift (`h1.sen-newsletter__title`, Standardgröße 1,5 rem) mit einem Satz und Button bzw. Link statt einer Meldung als Seitenüberschrift. Neue, überschreibbare Texte: `confirm_text`, `confirmed_title`, `confirmed_text`, `invalid_title`, `unsubscribe_text`, `unsubscribed_title`; `confirm_title` lautet kürzer „Anmeldung bestätigen“.
- Der Hinweis nach dem Absenden besteht aus Überschrift „Fast geschafft“ (`success_title`) und einem Satz; die JSON-Antwort enthält zusätzlich `title`. Der Standardtext lautet „Wir haben dir eine E-Mail geschickt. Erst wenn du die Anmeldung dort bestätigst, bist du eingetragen.“ (Englisch und Spanisch entsprechend). Gespeicherte Listentexte bleiben unverändert.

## 1.2.0 - 2026-11-10

- Nach einer Anmeldung ersetzt ein fokussierter, barrierefreier Hinweis genau die abgesendete Formularinstanz; der Text ist pro Liste und Sprache überschreibbar.
- Zulässige Anfragen außerhalb des Cooldowns versenden auch für bereits bestätigte Abonnements eine neue Bestätigungsmail, ohne Status oder Bestätigungsnachweis zu verändern.
- Der Filament-Listeneditor folgt den in Section Engine konfigurierten Website-Sprachen, nutzt bei mehreren Sprachen Tabs und zeigt gespeicherte Einbindungen nur beim Bearbeiten.
- Neue Listen erhalten editierbare Standardtexte für Deutsch, Englisch und Spanisch; weitere Website-Sprachen verwenden englische Vorbelegungen.
- Vollständige spanische Übersetzungen für Formular, Hinweise, Bestätigungsmail, Theme-Seiten und Verwaltung.

## 1.1.0 - 2026-11-09

- Bestätigungs-, Ablauf-, Abmelde- und Ergebnisseiten werden mit korrekten Statuscodes und `noindex, nofollow` im konfigurierten Section-Engine-Theme dargestellt.
- Nach erfolgreicher Bestätigung erfolgt ausschließlich für lokale Herkunfts-URLs die sichere Rückleitung zum passenden Newsletter-Formular mit fokussierter Erfolgsmeldung.
- Neutrale, barrierefreie `sen-`-Formularstyles mit sichtbarem Outline-Button, ohne Framework-Abhängigkeit, einmal pro Seite, abschaltbar und durch Theme-CSS mit null Spezifität überschreibbar.
- Laravel-Markdown-Bestätigungsmail mit Branding, optionalem Logo, lokalisiertem Footer, Listenname, URL-Fallback und Plain-Text-Part.
- Mindestversion von `tok3/section-engine` auf `^1.3.0` angehoben.

## 1.0.0 - 2026-11-09

- Bestätigungsmails ohne Queue erst nach der HTTP-Antwort; Transport- und Queue-Einreihungsfehler werden gemeldet, ohne die öffentliche Antwort zu verändern.
- Composer-Paket für Section Engine ab v1.1.0 mit Newsletter-Live-Tags und aktiven Listen.
- Barrierefreie Formulare ohne JS sowie optionale Vanilla-JS-Erweiterung; deutsche und englische Texte.
- Double Opt-in mit verschlüsseltem Formularkontext, Einwilligungssnapshot, Token-Hash, GET/POST-Trennung, Rate Limits und Spam-Schutz.
- Bestätigungsmails über Laravel-Mailer, optional Queue; signierte Abmeldung und RFC-8058-POST.
- Ereignisnachweise mit vollständiger IP und User-Agent, Domain-Events und täglicher Retention.
- Filament-Listen und Teilnehmer, Filter, CSV, druckbare Nachweise, manuelle Bestätigungsanforderung, Abmeldung, Erasure und 30-Tage-Widget.
- Historische Seitenreferenzen, dauerhafte Herkunfts-URL und lazy Anzeige einschließlich archivierter Seiten.
