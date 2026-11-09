# Installation

## Composer und Datenbank

PHP 8.3+, Laravel 11–13, Filament 5 und `tok3/section-engine ^1.3.0` sind erforderlich. Composer muss auf beide privaten Paketquellen zugreifen können. Beispiel für einen Entwicklungscheckout im benachbarten Verzeichnis:

```json
{"repositories":[{"type":"path","url":"../section-engine-newsletter","options":{"symlink":true}},{"type":"vcs","url":"git@github.com:tok3/section-engine.git"}]}
```

In vorhandene Repositories integrieren, diese nicht überschreiben. Alternativ die private VCS-Quelle `git@github.com:tok3/section-engine-newsletter.git` registrieren und später eine verfügbare Release-Version anfordern. Zur lokalen Entwicklung:

```sh
composer require tok3/section-engine-newsletter:dev-main
php artisan vendor:publish --tag=section-engine-newsletter-migrations
php artisan migrate
```

Laravel entdeckt den Service Provider automatisch. Die Migration erzeugt `section_engine_newsletter_lists`, `section_engine_newsletter_subscribers`, `section_engine_newsletter_subscriptions` und `section_engine_newsletter_events`. Die Herkunftsseiten-ID hat bewusst keinen Foreign Key.

## Filament

Im vorhandenen Panel, zusätzlich zum Section-Engine-Plugin:

```php
use Tok3\SectionEngineNewsletter\SectionEngineNewsletterPlugin;

$panel->plugin(SectionEngineNewsletterPlugin::make());
```

Zugriff erhalten ausschließlich Benutzer, für die `SectionEngineAccess::allows()` gilt. Das Paket eröffnet keinen eigenen öffentlichen Admin-Zugang. Das Plugin registriert Listen, Teilnehmer und das 30-Tage-Widget.

## Mail und Queue

Der Standard-Laravel-Mailer wird benutzt, sofern `section-engine-newsletter.mailer` nicht gesetzt ist. Für lokale Prüfungen:

```dotenv
MAIL_MAILER=log
```

Unter Laravel 11+ heißt die Variable `MAIL_MAILER`, nicht das ältere `MAIL_DRIVER`.

Ein echter Betrieb benötigt einen funktionierenden Laravel-Mailer und korrekte `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` und `APP_URL`. `APP_URL` bestimmt bei Queue-Versand die Linkbasis. Log-Mails enthalten Bestätigungslinks und müssen entsprechend geschützt werden.

```sh
php artisan vendor:publish --tag=section-engine-newsletter-config
```

In `config/section-engine-newsletter.php`:

```php
'mailer' => null, // null = Laravel-Standard
'queue' => null,  // null/leer = Versand nach der HTTP-Antwort; sonst Queue-Verbindungsname
'from' => ['address' => null, 'name' => null], // Laravel mail.from als Standard
'mail' => [
    'brand_name' => config('app.name'),
    'logo_url' => null,
    'footer' => null, // String oder z. B. ['de' => '…', 'en' => '…']
],
```

Die Bestätigungsmail ist eine Laravel-Markdown-Mail und übernimmt das konfigurierte Laravel-Mail-Theme. `mail.brand_name` benennt den Absender im Inhalt, `mail.logo_url` ergänzt optional ein Logo und `mail.footer` einen allgemeinen oder lokalisierten Footer. Für ein eigenes Mail-Theme können Laravels Mail-Views mit `php artisan vendor:publish --tag=laravel-mail` veröffentlicht und `mail.markdown.theme` beziehungsweise die Theme-CSS der Anwendung angepasst werden.

**Für den Produktionsbetrieb wird eine dauerhafte Queue wie `database` oder `redis` mit laufendem Worker empfohlen.** Eine `sync`-Queue-Verbindung führt Jobs weiterhin unmittelbar aus und bietet diese Entkopplung nicht.

Ohne konfigurierte Queue wird die Bestätigungsmail nach dem Datenbank-Commit für einen Terminierungs-Callback registriert und erst nach dem Senden der HTTP-Antwort verschickt. Dafür wird kein Queue-Worker benötigt; der PHP-Prozess bleibt bis zum Ende des Versandversuchs beschäftigt. Dieser Callback ist keine dauerhaft gespeicherte Versandaufgabe. Versandfehler werden über Laravels `report()` gemeldet und ändern die bereits gesendete generische Antwort nicht. Auch Fehler beim Einreihen einer Mail in die konfigurierte Queue ändern die Antwort nicht. Bei fehlgeschlagenem Versand wird kein `confirmation_sent` protokolliert.

Bei z. B. `queue => 'database'` die Laravel-Queue-Tabelle und einen Worker für diese Verbindung einrichten: `php artisan queue:work database`. Die Mailjobs werden nach dem Datenbank-Commit eingereiht und verschlüsselt. Schlüsselwechsel und Queue-Betrieb nach den Laravel-Regeln planen. `confirmation_sent` wird erst nach tatsächlicher Mailer-Übergabe protokolliert.

## Scheduler

Das Paket registriert `section-engine-newsletter:prune` täglich. Der Betreiber muss den Laravel-Scheduler minütlich ausführen, z. B. per Cron:

```cron
* * * * * cd /path/to/application && php artisan schedule:run >> /dev/null 2>&1
```

Manuell: `php artisan section-engine-newsletter:prune`. Einzelheiten zur Wirkung in [Datenschutz](privacy.md).

## Views und Sprachen

Optional veröffentlichen:

```sh
php artisan vendor:publish --tag=section-engine-newsletter-views
php artisan vendor:publish --tag=section-engine-newsletter-translations
```

Deutsch und Englisch sind enthalten. `default_locale` muss mit mindestens einem Einwilligungstext jeder Liste übereinstimmen. CSRF- und Session-Middleware sind erforderlich. Nur der signierte RFC-8058-One-Click-Endpunkt ist von CSRF ausgenommen. Für vollständig gecachte Seiten muss ein frischer, sitzungsgebundener Formularbereich vorgesehen werden; Formularkontexte sind zeitlich begrenzt und enthalten CSRF-Tokens.

## Entfernung

Vor Entfernung Bestätigungsprozesse beenden und benötigte Daten exportieren. `composer remove tok3/section-engine-newsletter` entfernt die dynamische Darstellung; gespeicherte Seiten bleiben durch das Core-Verhalten für unbekannte Provider renderbar. Datenbanktabellen werden dabei nicht automatisch gelöscht. Eine bewusste Datenlöschung, Mail-Queue-Bereinigung und Entfernung der Panel-Registrierung liegen beim Betreiber.
