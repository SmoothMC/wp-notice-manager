# Prüfstand · aktualisiert 25.10.2026

## Historischer Prüfstand bis 1.0.3 (Überschneidungssperre ab 1.1.0 ersetzt)

- Version 1.0.3: 38 zusätzliche PHP-Prüfungen für den GitHub-Updater (gültige/ungültige Metadaten, feste Paketadresse, Versionen, Cache, Netzwerkfehler, Plugin-Isolation, Detailansicht und Auto-Update-Auswahl). Release-ZIP und JSON lokal erstellt und auf Versions-/Ordnerkonsistenz geprüft.

- PHP 8.4: Syntax aller Plugin-PHP-Dateien; 44 automatisierte Prüfungen der Planungs-, Speicher- und Complianz-Erkennungslogik mit isolierten WordPress-Testdoubles. Einschließlich Standardwert und Eingabegrenzen der konfigurierbaren Freigabeverzögerung.
- Pflichtfelder, ungültige Kalenderdaten, nicht existierende Uhrzeit bei Sommerzeitbeginn, Winter-/Sommerzeitumrechnung.
- Identische, teilweise überlappende und umschließende Intervalle; direkt anschließende Zeiträume; Bearbeiten desselben Beitrags; Entwürfe reservieren keine Zeit.
- Veröffentlichung mit fehlendem Ende wird zum Entwurf; Zeitstempel speichern; konkurrierende Veröffentlichung wird bei fehlender Sperre nicht freigegeben.
- Maximal ein aktives Popup, mehrere aktive Ticker, exklusive Endzeit, Schutz passwortgeschützter Inhalte.
- Schließzustände bei geändertem Inhalt/Zeitraum und dauerhafter Ausblendung.
- JavaScript-Syntax mit Node.js.
- Automatisierter Chrome-Test: Standalone-Dialog, Escape, persistentes Ausblenden, erneute Anzeige nach Inhaltsänderung, Ticker-Wechsel, Laufband, reduzierte Bewegung, leerer Ticker, Ablauf eines offenen Popups, Elementor-Adapter mit simuliertem Frontend-Modul, Standalone-Rückfall bei fehlendem Modul, mobile Breite und einspaltiger Text.
- Zusätzliche Complianz-Vertragstests mit simulierten APIs: Sperre bei offenem Banner, unveränderter Ticker, 300-ms-Abstand, Akzeptieren/Ablehnen, wiederholte Events ohne doppelte Popups, erneutes Öffnen der Einstellungen, Schließzustand unverändert bei automatischer Unterbrechung, verspätete/fehlende API ohne 5-Sekunden-Freigabe, Freigabe ohne Event, abgelaufener/ersetzter Hinweis, Elementor-Öffnungs-/Schließevents, Standalone-Fallback, Banneröffnung während der Popup-Verzögerung, deaktivierter Banner, Regionen ohne Banner, inaktiver Cookie-Wall-Container und DNT.

Version 1.0.2 ergänzt Browserprüfungen für eine benutzerdefinierte Freigabeverzögerung von 1.200 ms und für 0 ms bei weiterhin aktiver Banner-Sperre.

## Noch auf einer echten WordPress-Testseite zu prüfen

Der lokale Projektordner enthält keine lauffähige WordPress-Installation und kein Elementor Pro. Deshalb sind die folgenden Integrationstests nicht durch die isolierten Prüfungen abgedeckt:

- WordPress-Admin-Oberfläche, tatsächliche Datenbank-/Hook-Reihenfolge und Berechtigungen.
- Laden, Öffnen und Schließen des realen Elementor-Pro-Templates, insbesondere in Verbindung mit Elementor Element Caching.
- Divi-Header/Theme-Builder, tatsächliches Theme-CSS und Cache-/Optimierungsplugins.
- Migration der gespeicherten Praxis-Einstellungen in der Zielinstallation.
- Reales Complianz Free/Premium zusammen mit dem verwendeten Banner-Design, Cookie-Wall und Script-Optimierung; die automatisierten Complianz-Prüfungen simulieren die dokumentierten Schnittstellen.
- Tatsächliche WordPress-Installation eines Folge-Releases über den neuen Updater. Die Schnittstelle und Paketstruktur sind geprüft; ein produktiver WordPress-Update-Lauf wurde nicht ausgeführt.

## Tests erneut ausführen

`php tests/schedule.php` führt die isolierten PHP-Prüfungen aus. `node tests/browser.cjs` und `node tests/complianz.cjs` benötigen Playwright und Google Chrome; `CHROME_PATH` kann einen anderen Chromium-Pfad angeben. Die Browserskripte verwenden ausschließlich simulierte Antworten und ein separates Browserprofil. Der allgemeine Browsertest erstellt zwei Ansichtsaufnahmen im temporären Verzeichnis.

Die installierbare ZIP enthält keine Testskripte. Originalplugin und alte ZIP bleiben unverändert.

## 1.0.5

Zusätzliche isolierte Prüfungen für Popup-spezifische Schließregeln, Default/Fallback und Divi-Layout-Validierung. Browser-Test mit Divi-Markup-Doppel prüft Ausgabe, Zurücklegen des Layouts beim Schließen und erneute Anzeige bei „immer“. Eine echte Divi-Installation inklusive generierter Modul-Styles und Scripts wurde lokal nicht getestet.

## 1.0.9

Seitenauswahl isoliert für Homepage, Archive, Posts, Seiten, ausgewählte Seiten und fehlenden Kontext geprüft. Browser-Test prüft übertragenen Seitenkontext, Fade-in-Dauer, ausgeschaltete Animation, Dauer 0, reduzierte Bewegung, Elementor-Adapter und das separate Schließen-Icon. Die tatsächliche Divi-Installation bleibt ein manueller Integrationstest.


## 1.1.0 – Popup-Warteschlange und native Update-Prüfung

- PHP-Domainprüfungen: Pflichtdaten, erlaubte Überschneidungen, Priorität absteigend, ID-Gleichstand, bestehende Schließschlüssel.
- Updater-Vertrag: reguläre Core-Prüfungen umgehen Cache und Fehlercache, „Erneut prüfen“ ist berechtigungsgeprüft, Detaildialog darf cachen.
- `node tests/queue.cjs`: Standalone-, Divi- und Elementor-Testadapter; automatische Folge, keine Verdrängung, entfernte/abgelaufene Hinweise, gespeicherte Schließregeln, Divi-Links, Complianz-Unterbrechung und verspätetes Elementor-Hide.
- Bestehende `tests/browser.cjs`, `tests/complianz.cjs` und `tests/preview.php` bleiben Regressionstests.
- Browser-Tests verwenden Playwright mit separatem Chrome-Profil und nachgebildeten Builder-APIs. Abschließend auf der Zielinstallation mit echtem Divi 4 / Elementor Pro und Cache-Plugin prüfen.


## 1.1.1 – Admin-Menüs, Mitarbeiter und Builder-Auswahl

`php tests/admin.php none` sowie Varianten `divi`, `elementor`, `both` prüfen 140 Verträge: gezielte Rollenerweiterung, keine zusätzlichen globalen Rechte, geschützte Einstellungen, aktivierungsabhängige Topbar, sichtbare Builder-Optionen und Template-Felder, Erhalt verborgener Template-IDs und Plugin-Aktionslink. `tests/preview.php` prüft nun auch Mitarbeiter-Vorschauen ohne Einstellungsrechte. Der Release-Workflow führt alle vier Builder-Varianten aus.

Auf der Zielseite mit einem echten Mitarbeiter-Konto prüfen: Liste, Anlegen/Bearbeiten/Veröffentlichen, Vorschau und Topbar; Einstellungen dürfen nicht zugänglich sein. Die automatisierten Prüfungen verwenden WordPress-Testdoubles und ersetzen keinen vollständigen WordPress-/Divi-Integrationstest.


## 1.2.0 – Dashboard und Bezeichnung

`php tests/dashboard.php` prüft aktive Reihenfolge, geplante Starts einschließlich WordPress-Veröffentlichung, exklusive Endzeit, unvollständige Entwürfe, fehlende/veraltete Seitenauswahl, Passwortschutz, Mitarbeiterrechte, deaktivierte Bereiche, HTML-Escaping, die Begrenzung auf fünf geplante Einträge, „Endet heute“, Vorschau-Nonce und rollenabhängige Direktaktionen. Der Release-Workflow führt diesen Test ebenfalls aus. Die Darstellung verwendet die WordPress-Dashboard-Standardstruktur; die echte Dashboard-Ansicht und Zielrollen bleiben auf der WordPress-Installation zu prüfen.


## 1.2.1 – Dashboard-Gestaltung

Dashboard-Vertragstests erfolgreich. Lokal gerendertes Widget-Markup mit beispielhaften Hinweisen in Chrome bei 620 und 360 Pixel Fensterbreite visuell geprüft; kein horizontaler Überlauf. Die lokale Vorschau verwendet einen vereinfachten WordPress-Rahmen und Ersatzsymbole, die Installation verwendet die WordPress-Dashicons. Keine Änderungen an der Frontend-Logik.


## 1.2.2 – Cookiefreie Sitzungsregel

PHP-Prüfungen für Erkennen, Speichern und stabilen Sitzungsschlüssel. `node tests/session.cjs` prüft in Chrome: keine Cookies, Neuladen und Navigation im selben Tab, unabhängige/neue Tabs, Isolation gegenüber dauerhaftem localStorage-Schließzustand und blockierten sessionStorage.


## Release-Notizen

`python3 tests/release_notes.py` prüft die Isolation aller Versionsabschnitte, ungültige/fehlende/doppelte Versionen sowie die Beschränkung der GitHub-Synchronisierung auf das Body-Feld. Entwürfe werden übersprungen, unveränderte Beschreibungen nicht erneut geschrieben.
