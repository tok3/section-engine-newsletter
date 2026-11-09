# Changelog

## 1.1.0 - 2026-11-09

- Bestätigungs-, Ablauf-, Abmelde- und Ergebnisseiten werden mit korrekten Statuscodes und `noindex, nofollow` im konfigurierten Section-Engine-Theme dargestellt.
- Nach erfolgreicher Bestätigung erfolgt ausschließlich für lokale Herkunfts-URLs die sichere Rückleitung zum passenden Newsletter-Formular mit fokussierter Erfolgsmeldung.
- Neutrale, barrierefreie `sen-`-Formularstyles ohne Framework-Abhängigkeit, einmal pro Seite, abschaltbar und durch Theme-CSS mit null Spezifität überschreibbar.
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
