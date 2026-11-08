# Verwaltung in Filament

## Listen

Unter „Newsletter → Listen“ Listen anlegen, bearbeiten und deaktivieren. Ein Schlüssel ist eindeutig und enthält ausschließlich `[a-z0-9_-]`. Der Einwilligungstext in `default_locale` ist Pflicht. Für Deutsch und Englisch stehen Textfelder bereit; die konfigurierbare Standardsprache wird zusätzlich berücksichtigt. Betreff und Einleitung der Bestätigungsmail lassen sich pro Sprache überschreiben. Leere Overrides verwenden die Pakettexte.

Die Tabelle zeigt Bestätigt/Pending/Abgemeldet getrennt und bietet einen kopierbaren Einbettungscode. Die Bearbeitungsseite zeigt gespeicherte Einbindungen über den Core-Usage-Finder, einschließlich Entwürfen und archivierten Inhalten. Löschen archiviert die Liste per Soft Delete; vorhandene Abonnements und Nachweise bleiben erhalten.

## Teilnehmer und Filter

Die Tabelle zeigt E-Mail-Adresse, Listen, Statuschips, Tags, Herkunft und Zeitpunkte. Die gemeinsamen Abonnementfilter umfassen Liste, Status, Tag, Herkunftsseiten-ID sowie den Zeitraum der Anforderung. Alle ausgewählten Kriterien müssen auf dasselbe Abonnement passen. Die E-Mail-Suche kann zusätzlich genutzt werden.

Herkunftsseiten werden bei Anzeige über das konfigurierte Page-Modell einschließlich archivierter Seiten aufgelöst. Die gespeicherte ID wird nie wegen einer Seitenlöschung geändert. Existiert die Seite nicht mehr, zeigt die Oberfläche „Seite gelöscht“ und die dauerhaft gespeicherte Herkunfts-URL. Ohne ursprünglich gespeicherte Seiten-ID erscheint „Keine Seite“.

## Aktionen

- **Bestätigung anfordern:** Adresse, aktive Liste, Sprache und optional Tag angeben. Neue Adressen werden ausschließlich als `pending` angelegt und erhalten eine Bestätigungsmail. Bestehende Anmeldungen folgen den normalen Status-/Cooldown-Regeln; es gibt keine direkte manuelle Bestätigung.
- **Abmelden:** Das betreffende Abonnement auswählen und bestätigen. Statuswechsel und Admin-Aktion werden mit IP, User-Agent, Zeitpunkt und Akteur protokolliert.
- **Endgültig löschen:** Die Bestätigung im Dialog löscht den Teilnehmer samt sämtlichen Abonnements und Ereignissen unwiederbringlich. Das Domain-Event `SubscriberDeleted` wird ausgelöst. Der entfernte Nachweis wird nicht in einer separaten Kopie aufbewahrt.
- **Gefilterte CSV exportieren:** Berücksichtigt E-Mail-Suche und Abonnementfilter. Pro passendem Abonnement entsteht eine Zeile mit Adresse, Liste, Status, Tag, Herkunft und Zeitpunkten. Andere Abonnements derselben Adresse werden bei unpassenden Filtern nicht exportiert. Tabellenkalkulations-Formelpräfixe werden beim Export entschärft.
- **Einwilligungsnachweis:** Öffnet eine geschützte druckbare HTML-Ansicht; über den Browser drucken oder als PDF speichern. Die Detailansicht enthält ebenfalls die vollständige Ereignishistorie. Es wird keine PDF-Bibliothek benötigt.

Das Dashboard-Widget zählt Abonnements mit Anforderungszeitpunkt innerhalb der letzten 30 Tage, gruppiert nach Liste und aktuellem Tag. Wiederholte Requests sind nicht einzelne neue Teilnehmer; der aktuelle Anforderungszeitpunkt wird bei zulässiger erneuter Anforderung aktualisiert.

Exportdateien und Ausdrucke enthalten personenbezogene Daten und sind vom Betreiber passend zu verwalten. Zugriff auf Ressourcen und Nachweise setzt den dokumentierten Core-Zugang voraus.
