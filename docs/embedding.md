# Newsletter einbetten

## Liste vorbereiten

Eine Liste muss in Filament existieren, aktiv sein und einen Einwilligungstext mindestens in der konfigurierten Standardsprache besitzen. Der Schlüssel ist eine eindeutige Zeichenfolge aus Kleinbuchstaben, Ziffern, Bindestrich und Unterstrich. Den Schlüssel nach bestehenden Einbindungen möglichst nicht ändern, da diese den Schlüssel speichern.

In eine HTML-Section, den HTML-Quelltext einer Smart Section oder ein HTML-Live-Token-Snippet einfügen:

```text
{{ newsletter:news }}
{{ newsletter:news tag=guide }}
{{ newsletter:news tag=guide variant=compact }}
```

Das erste Argument bezeichnet den Listenschlüssel. `tag` ist eine optionale freie Herkunftsmarkierung. Tags werden kleingeschrieben und müssen danach vollständig auf `[a-z0-9_-]{1,64}` passen. Ungültige Tags werden verworfen; das Formular und die Anmeldung bleiben möglich.

`variant` ist `default` oder `compact`. Unbekannte Varianten fallen auf `default` zurück. Beide mitgelieferten Varianten verwenden die gemeinsamen zugänglichen Felder und unterschiedliche CSS-Klassen. Das kompakte Layout kann über diese Klasse gezielt gestaltet werden.

## Was Besucher sehen

Ein sichtbares E-Mail-Label, den Einwilligungstext, standardmäßig eine Pflicht-Checkbox und den Anmeldebutton. Nach dem Absenden erscheint immer dieselbe allgemeine Rückmeldung – auch für bereits bekannte Adressen. Eine Bestätigungsmail wird für neue und erneut angemeldete Adressen versendet; bei bereits offenen Anmeldungen gilt standardmäßig eine zehnminütige Wiederholungssperre. Bereits bestätigte Adressen erhalten keine neue Mail.

Der Mail-Link öffnet eine Seite im vorhandenen Website-Theme mit Bestätigungsbutton. Erst dieser POST bestätigt das Abonnement. Danach kehren Besucher zur ursprünglichen Seite zurück, sofern deren Schema und Host zu `APP_URL` passen. Dort ersetzt eine fokussierte Erfolgsmeldung alle Formulare derselben Liste; Formulare anderer Listen bleiben stehen. Bei fehlender oder fremder Herkunft erscheint stattdessen eine Ergebnisseite im Website-Theme. Der Link gilt standardmäßig 48 Stunden; abgelaufene Links zeigen mit HTTP 410 ein neues Anmeldeformular an. Eine Abmeldung erfolgt ebenfalls über eine signierte Theme-Seite mit POST-Button. Eine erneute Anmeldung benötigt wieder Double Opt-in.

Ohne JavaScript funktioniert das Formular über einen Redirect zur eigenen Formularinstanz. Mit JavaScript werden Status und Validierungsfehler im Formular angezeigt und der Fokus auf den Status beziehungsweise das erste fehlerhafte Feld gesetzt. Mehrere Formulare auf einer Seite sind möglich.

## Gestaltung

Das Paket liefert neutrale Styles ohne Framework-Abhängigkeit inline aus, einmal pro Request. Alle Selektoren liegen in `:where()` und haben damit keine Spezifität; Regeln des Website-Themes gewinnen ohne `!important`. Die Klassen beginnen mit `sen-`. `.sen-newsletter--compact` stellt E-Mail-Feld und Button nebeneinander und bricht auf schmalen Viewports in eine Spalte um.

| Variable | Standard | Zweck |
| --- | --- | --- |
| `--sen-color` | `currentColor` | Text- und Akzentfarbe |
| `--sen-border` | `1px solid color-mix(in srgb, currentColor 55%, transparent)` | Rahmen; Fallback `1px solid currentColor` |
| `--sen-radius` | `.375rem` | Eckenradius |
| `--sen-gap` | `.75rem` | Abstand zwischen Feldern |
| `--sen-padding` | `.625rem .75rem` | Innenabstand von Eingaben und Button |
| `--sen-button-bg` | `currentColor` | Button-Hintergrund |
| `--sen-button-color` | `Canvas` | Button-Textfarbe |
| `--sen-focus` | `2px solid currentColor` | sichtbarer Fokusrahmen |
| `--sen-max-width` | `32rem` | maximale Formularbreite |

Die Paketstyles lassen sich nach Veröffentlichung der Konfiguration vollständig abschalten:

```php
'styles' => false,
```

Ein mögliches Tailwind-Override in der Anwendungs-CSS:

```css
@layer components {
    .sen-newsletter {
        --sen-button-bg: theme(colors.zinc.900);
        --sen-button-color: theme(colors.white);
        --sen-focus: 2px solid theme(colors.sky.600);
        @apply text-zinc-900 dark:text-zinc-100;
    }

    .sen-newsletter__input {
        @apply rounded-lg border-zinc-300 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-950;
    }

    .sen-newsletter__button {
        @apply rounded-lg px-5 font-semibold hover:opacity-90;
    }
}
```

Views veröffentlichen:

```sh
php artisan vendor:publish --tag=section-engine-newsletter-views
```

Danach liegen Overrides unter `resources/views/vendor/section-engine-newsletter/`, einschließlich Formular-, Theme-Seiten- und Mail-Views. Sichtbare Labels, eindeutige IDs, `aria-describedby`, die `aria-live`-Statusregion, Fokusziele und CSRF-/Kontextfelder erhalten. Das Formular muss weiterhin ohne JavaScript absendbar sein. Browser-Zoom und ausreichende Kontraste/Zielgrößen mit den Projektstyles prüfen.

Eigene Variante ergänzen:

```php
'views' => ['variants' => [
    'default' => 'section-engine-newsletter::form.default',
    'compact' => 'section-engine-newsletter::form.compact',
    'sidebar' => 'newsletter.sidebar',
]],
```

Die neue View erhält dieselben Daten wie die Standardviews. Idealerweise die gemeinsamen Formularfelder einbinden.

## Editor und Fehlersuche

Im Editor sind die Formularcontrols deaktiviert und ein Hinweis nennt Liste und Tag. Unbekannte oder inaktive Listen sind im normalen Frontend leer; im Editor oder im Core-Designmodus erscheint ein markierter Platzhalter.

- Kein Formular: Schlüssel, Aktivierung und installierten Provider prüfen.
- Markierter Platzhalter: Liste fehlt oder ist inaktiv; Core meldet Renderfehler im Laravel-Log.
- Keine Mail: Mailer, Queue-Worker, Spamordner, Cooldown und Abonnementstatus prüfen. Die öffentliche Rückmeldung verrät den Status absichtlich nicht.
- Ungültiges Formular: Seite neu laden; Mindestfüllzeit standardmäßig drei Sekunden, Kontextlaufzeit sieben Tage. CSRF-/Session-Konfiguration und Seiten-Caching prüfen.
- Falsche Sprache: Seiten-/Preview-Locale und `default_locale` sowie Listentexte und veröffentlichte Übersetzungen prüfen.

Provider-Tags als normalen Text außerhalb von HTML-Attributen, Kommentaren, Script-, Style- oder Textarea-Bereichen einsetzen. Zum wörtlichen Anzeigen die öffnenden Klammern als `&#123;&#123;` schreiben.
