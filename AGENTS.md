# Section Engine Newsletter – Arbeitsregeln

- Dieses Repository enthält ausschließlich `tok3/section-engine-newsletter` (Namespace `Tok3\SectionEngineNewsletter`). Section Engine Core und Section Engine Editorial werden hier nicht geändert.
- Das Paket dockt ausschließlich über die dokumentierten Core-Schnittstellen an (Live Tag Provider, `LiveTokenRenderContext`, `LiveTagUsageFinder`, `SectionEngineAccess`). Fehlt eine Schnittstelle: stoppen und `BLOCKED_BY_CORE_SEAM: <beschreibung>` melden, nichts im Core oder per Workaround nachbauen.
- Version 1 sammelt nur Anmeldungen. Kein Versand, keine Drittanbieter-Anbindung.
- Einwilligungsnachweis ist Pflicht: Jeder Schritt (Anmeldung, Mail, Bestätigung, Abmeldung, Admin-Aktion) wird mit Zeitpunkt, voller IP und User-Agent unveränderlich protokolliert.
- Normale Installation ist Composer-first. Verbraucher brauchen keinen npm- oder Build-Schritt.
- Texte, Branding, URLs und Locales bleiben generisch und konfigurierbar. Keine Leibnizer-spezifischen Inhalte im Paket.
- Frontend-Formulare sind barrierefrei (Labels, Fehler per `aria-describedby`, Status per `aria-live`, Fokusführung) und funktionieren ohne JavaScript.
- Jede Produktionsänderung erhält zuerst einen fehlschlagenden Test. Vor jedem Commit laufen die relevanten Tests, vor Abschluss die vollständige Prüfmatrix.
- Ein Commit pro Plan-Task, Conventional Commits. Kein Push, kein Tag ohne ausdrückliche Anweisung.
- Widerspricht der Plan dem Code oder einer Core-Schnittstelle: stoppen und melden, nicht improvisieren.
- Interne Planungsdokumente unter `docs/superpowers/**` werden nie in öffentliche Dokumentation übernommen.
