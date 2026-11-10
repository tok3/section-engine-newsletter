# Daten, Einwilligungsnachweis und Aufbewahrung

Diese Seite beschreibt die technische Speicherung. Die Textbausteine sind neutral und müssen für den konkreten Betrieb geprüft und ergänzt werden. Sie sind keine Rechtsberatung.

## Gespeicherte Daten

- Teilnehmer: normalisierte E-Mail-Adresse, Sprache sowie Erstellungs-/Änderungszeitpunkt; keine Soft Deletes.
- Abonnement je Teilnehmer und Liste: Status, optionaler Tag, historische Herkunftsseiten-ID, Herkunfts-URL und -Sprache, exakter angezeigter Einwilligungstext einschließlich Checkboxlabel und SHA-256-Prüfsumme.
- Anforderung und Bestätigung: Zeitpunkt, volle IP-Adresse und User-Agent; Abmeldung zusätzlich mit Zeitpunkt und voller IP im Abonnement.
- Append-only-Ereignisse: angefordert, Mail tatsächlich übergeben, bestätigt, abgemeldet, erneute Anmeldung und Admin-Aktion, jeweils Zeitpunkt, volle IP, User-Agent und Herkunft. Bei Admin-Aktionen wird der Akteur im Metadatenfeld festgehalten. Der User-Agent einer Abmeldung steht im Ereignis.
- Bestätigungstoken: in der Datenbank ausschließlich SHA-256-Hash und Ablaufzeit. Der unverhashte Token steht im versendeten Link und gegebenenfalls im geschützten Mail-/Queue-Log.

Die Herkunftsseiten-ID ist historisch, ohne Foreign Key. Sie bleibt auch bei endgültiger Löschung der Seite erhalten. Die Herkunfts-URL ist die dauerhafte Herkunft im Nachweis. Einwilligungstexte in vergangenen Ereignissen werden bei einer späteren Listenbearbeitung nicht überschrieben.

Zusätzlich können Laravel, Webserver und Mail-/Queue-Infrastruktur eigene Sessions, Logs, Warteschlangen und Backups führen. Deren Fristen sind außerhalb dieses Pakets zu konfigurieren.

## Bestätigungsmails und Fehler

Für den Produktionsbetrieb wird eine dauerhafte Queue mit laufendem Worker empfohlen. Ohne konfigurierte Queue erfolgt der Versand nach der HTTP-Antwort im terminierenden PHP-Prozess. Die öffentliche Antwort bleibt unabhängig vom Abonnementstatus und von Versand- oder Queue-Einreihungsfehlern gleich. Fehler werden über Laravels `report()` an die Anwendung gemeldet. Ein fehlgeschlagener Mailversand erzeugt keinen `confirmation_sent`-Nachweis; das Pending-Abonnement und das bereits gespeicherte Anforderungsereignis bleiben erhalten. Erst die tatsächliche Übergabe an den Mailer wird als `confirmation_sent` mit den ursprünglichen IP-/User-Agent-Daten protokolliert.

Außerhalb des konfigurierten Wiederholungs-Cooldowns löst jede zulässige Anmeldung eine Bestätigungsmail aus, auch wenn das Abonnement bereits bestätigt ist. Dabei bleibt der bestätigte Status samt ursprünglichen Bestätigungszeitpunkten, IP und User-Agent unverändert; protokolliert werden die neue Anforderung und, bei erfolgreicher Übergabe, der erneute Mailversand. Ein erneuter Bestätigungs-POST verändert den bestehenden Bestätigungsnachweis nicht. Betreiber sollten diese wiederholten transaktionalen Mails in Datenschutzhinweisen, Versandvolumen und Missbrauchsbeobachtung berücksichtigen.

## Aufbewahrung und Löschung

`section-engine-newsletter:prune` wird täglich über den Laravel-Scheduler aufgerufen:

- `pending`, nie bestätigt: Abonnement und zugehörige Ereignisse werden nach `prune.pending_days` (Standard 30 Tage seit letzter zulässiger Anforderung) gelöscht.
- `unsubscribed`: Nach `prune.unsubscribed_proof_years` (Standard drei Jahre seit Abmeldung) werden alle IP-/User-Agent-Felder des Abonnements und seiner Ereignisse auf `null` gesetzt. Zeitpunkte, Einwilligungssnapshots, Herkunft und übrige Metadaten bleiben bestehen.
- Bestätigte Abonnements werden durch diesen Command nicht verändert.

Der Command löscht keine Teilnehmer-Stammdatensätze. Eine Adresse kann daher nach Entfernung ihres letzten Pending-Abonnements im Teilnehmerbestand bleiben, bis sie endgültig gelöscht wird. Betreiber müssen diese verbleibenden Daten in ihrem Löschkonzept berücksichtigen. Die Admin-Aktion „Endgültig löschen“ entfernt Adresse, alle Abonnements und alle Ereignisse kaskadierend. Soft Delete einer Liste löscht die Nachweise nicht.

Ereignisse sind gegen normale Eloquent-Updates und -Deletes geschützt. Ausnahmen sind die ausdrücklich vorgesehene Retention-Anonymisierung und kaskadierende Löschung bei Erasure; privilegierte direkte Datenbankzugriffe unterliegen der Verantwortung des Betreibers.

## Neutrale Textbausteine

> Bei Ihrer Newsletter-Anmeldung speichern wir Ihre E-Mail-Adresse und die zugehörige Liste. Zur Dokumentation der Anmeldung, Bestätigung und Abmeldung speichern wir Zeitpunkte, die vollständige IP-Adresse, Informationen zum verwendeten Browser sowie den angezeigten Einwilligungstext und die Herkunft der Anmeldung. Eine Anmeldung wird erst nach Ihrer ausdrücklichen Bestätigung über den zugesandten Link aktiviert.

> Nicht bestätigte Abonnements und ihre Nachweise entfernen wir nach [Frist]. Nach einer Abmeldung anonymisieren wir IP-Adressen und Browserinformationen im Nachweis nach [Frist]. Weitere gespeicherte Daten und die endgültige Löschung behandeln wir nach [betrieblichem Löschkonzept]. Für Fragen oder Löschanliegen erreichen Sie uns unter [Kontakt].

Verantwortlichen, Zweck, Rechtsgrundlage, Mail-Infrastruktur, Empfänger, Übermittlungen, Betroffenenrechte, Kontakt und tatsächliche Aufbewahrungsfristen ergänzt der Betreiber passend zu seiner Anwendung. Paketversion 1 synchronisiert keine Daten mit Newsletter-Drittanbietern und führt keinen Kampagnenversand aus.
