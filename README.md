# Section Engine Newsletter

`tok3/section-engine-newsletter` sammelt Newsletter-Anmeldungen für Section Engine mit Double Opt-in und verwaltet Listen und Teilnehmer in Filament. Version 1 versendet ausschließlich Bestätigungsmails: keine Kampagnen, kein Massenversand und keine Drittanbieter-Synchronisation.

## Voraussetzungen

PHP 8.3+, Laravel 11–13, Filament 5, Section Engine `^1.1.0`, eine relationale Datenbank und ein Laravel-Mailer. Das Paket ist privat und benötigt Composer-Zugriff auf die Paketquelle und Section Engine. Es wird kein npm- oder Build-Schritt für die Installation benötigt.

## Schnellstart

```sh
composer require tok3/section-engine-newsletter:dev-main
php artisan vendor:publish --tag=section-engine-newsletter-migrations
php artisan migrate
```

Die Composer-Quelle für dieses private Paket muss im Verbraucherprojekt hinterlegt sein. `dev-main` bezeichnet den Entwicklungsstand; für produktive Installationen eine geprüfte veröffentlichte Version verwenden, sobald verfügbar.

Im bestehenden Filament-Panel ergänzen:

```php
use Tok3\SectionEngineNewsletter\SectionEngineNewsletterPlugin;

$panel->plugin(SectionEngineNewsletterPlugin::make());
```

Unter „Newsletter → Listen“ eine aktive Liste mit Einwilligungstext anlegen. Danach in eine HTML- oder Smart Section einfügen:

```text
{{ newsletter:news tag=guide variant=compact }}
```

Den Laravel-Mailer und den Scheduler einrichten. Besucher geben ihre E-Mail-Adresse ein und bestätigen anschließend über einen POST-Button im Mail-Link. Die reine Öffnung des Links bestätigt nichts.

## Dokumentation

- [Installation](docs/installation.md)
- [Einbetten und gestalten](docs/embedding.md)
- [Listen und Teilnehmer verwalten](docs/admin.md)
- [Gespeicherte Daten und Aufbewahrung](docs/privacy.md)
- [Entwicklerschnittstellen](docs/developer.md)

## Entwicklung

```sh
composer install
npm ci
composer test
composer format:check
composer analyse
npm test
```

Node wird nur für die jsdom-Entwicklungstests gebraucht. Das Workbench-Panel registriert Core und Newsletter gemeinsam; Konfiguration in `testbench.yaml`.

Proprietäre Lizenz.
