# Prüfstand · 22.09.2026

## Erfolgreich geprüft

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
