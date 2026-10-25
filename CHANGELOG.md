# WP Notice Manager – Changelog

Alle Versionen, neueste zuerst. Daten beziehen sich auf Europe/Berlin.
New = neue Funktionen · Fix = Fehlerbehebungen · Change = Änderungen · Remove = entfernte Funktionen.

Die GitHub-Release-Beschreibungen enthalten jeweils nur die Änderungen ihrer Version. Die vollständige Historie bleibt hier erhalten.

## 1.2.2 – 2026-10-25

### New
- Neue Schließregel pro Popup: „Für diese Tab-Sitzung ausblenden“ mit sessionStorage, ohne Cookie.
- Sitzungsschließung bei Neuladen und Seitenwechsel im selben Tab beibehalten; bei blockiertem Speicher Wiederholungen auf der aktuellen Seite verhindern.

## 1.2.1 – 2026-10-25

### Change
- Dashboard-Stil überarbeitet: weiße Kennzahlen-Kacheln auf sanft grünem Hintergrund, dezent hinterlegte Hinweiszeilen, Typ-Kennzeichnung und aufgeräumter Aktionsbereich.
- Widget-Styles auf das eigene Dashboard-Widget begrenzt und Darstellung für schmale Dashboard-Spalten angepasst.

### Remove
- Erklärungstext zur zeitlichen Freigabe aus dem Dashboard-Widget entfernt.

## 1.2.0 – 2026-10-25

### New
- WordPress-Dashboard-Widget für aktive und nächste geplante Hinweise, Handlungsbedarf und Direktaktionen. Mit Popup-Priorität/Reihenfolge, Anzeigeort, Endzeit und „Endet heute“; ohne Funktionsstatus-Anzeige.
- Mitarbeiter sehen nur ihre Verwaltungsaktionen; Einstellungen bleiben Administratoren vorbehalten. Vorschau-Links sind weiterhin geschützt.
- Übersicht berücksichtigt aktivierte Bereiche, Bearbeitungsrechte, exklusive Endzeiten, WordPress-Zeitzone und geplante Veröffentlichung.

### Fix
- Autor in Plugin- und Updateinformationen auf „Mikka | zzzooo Studio“ korrigiert.

### Change
- Sichtbaren Pluginnamen auf „WP Notice Manager“ gekürzt.

## 1.1.1 – 2026-10-25

### New
- Admin-Topbar: Megafon-Icon und Untermenüs für aktivierte Popups/Ticker sowie Einstellungen entsprechend der Berechtigung.
- Standardrolle Mitarbeiter darf Hinweis-Beiträge inklusive Veröffentlichung und geschützter Vorschau verwalten. Globale Einstellungen bleiben Administratoren vorbehalten; eigene Plugin-Berechtigung ohne zusätzliche allgemeine WordPress-Rechte.

### Change
- Divi- und Elementor-Pro-Auswahl und Template-Felder erscheinen nur bei verfügbarem Renderer. Verborgene Template-Zuordnungen bleiben erhalten.
- Plugin-Aktionslink „Einstellungen“ ersetzt „Nach Updates suchen“. Native WordPress-Update-Prüfungen bleiben unverändert.

## 1.1.0 – 2026-10-25

### New
- Priorität pro Popup (0–999, höhere zuerst; bei Gleichstand kleinere ID). Zeitliche Überschneidungen sind jetzt erlaubt; Start und Ende bleiben Pflicht.
- Passende Popups erscheinen nacheinander nach dem Schließen. Zeitraum, Seitenauswahl, Schließregel und Complianz-Sperre bleiben wirksam. Bereits offene Popups werden nicht verdrängt; keine Wiederholungsschleife pro Seitenaufruf.
- Divi-Inhalte und Links wechseln mit der Warteschlange. Vor Wiederverwendung eines Elementor-Templates wird dessen Schließanimation abgewartet.

### Change
- Native WordPress-Update-Prüfungen laden wie bei woo-cart-gifts stets frische Metadaten. „Erneut prüfen“ leert den Detail-Cache. GitHub bleibt die tokenfreie Updatequelle; automatische Updates bleiben eine WordPress-Benutzerentscheidung.
- Automatischer Release-Build bei Versionsänderungen auf main; PHP-, Updater- und Browser-Regressionsprüfungen ergänzt.

## 1.0.9 – 2026-09-23

### New
- Seitenauswahl pro Popup: alle Seiten, Homepage, Archive/Beitragsübersicht, Blogbeiträge, WordPress-Seiten oder ausgewählte Einzelseiten.
- Globale Fade-in-Option mit 0–5000 ms Dauer (Standard 300 ms), berücksichtigt reduzierte Bewegung.

### Change
- Separates Schließen-Icon im Popup-Button integriert.

## 1.0.8 – 2026-09-23

### Fix
- Popup-Überschrift und Button-Text werden auch in Divi-Heading-Modulen ersetzt, wenn Divi die Shortcodes als Text ausgibt.

### Change
- Divi-Dialog mit maximal 640 px Breite, ohne Innenabstand und mit transparentem Hintergrund.

## 1.0.7 – 2026-09-23

### New
- Geschützte Popup-Vorschau für Administratoren direkt im Popup-Beitrag.
- Gespeicherte Entwürfe und abgelaufene Popups mit aktuellem Template ansehen.
- Vorschau unabhängig von Zeitplanung, Schließregel und Complianz; Besuchereinstellungen bleiben unverändert.

## 1.0.6 – 2026-09-23

### New
- Optionaler Button-Text und Button-Link pro Popup.
- Shortcodes für vollständigen Button und Button-Text.
- Dynamischer Link-Platzhalter für Divi-Button- und Bildmodule.

### Change
- Geänderte Button-Inhalte berücksichtigen die Popup-Schließregel.

## 1.0.5 – 2026-09-23

### New
- Zentrales Divi-Template aus veröffentlichten Bibliothekslayouts mit Tag „Popup“.
- Divi-Ausgabe im Plugin-Dialog mit Zeitplanung und Complianz-Sperre.

### Change
- Schließverhalten pro Popup statt global; Standard: erneut bei geändertem Inhalt oder Zeitraum.

## 1.0.4 – 2026-09-22

### New
- Popup und Ticker unabhängig deaktivierbar, einschließlich des jeweiligen Posttyps; Inhalte bleiben erhalten.
- Ticker-Steuerung (Pause / Weiter) optional ausblendbar.
- Optionaler sichtbarer Titel pro Tickerbeitrag und Punkt-Trenner zwischen Titel, Meldung und Beiträgen.

### Fix
- Nahtloses Laufband auch mit einer einzelnen kurzen Meldung.

## 1.0.3 – 2026-09-22

### New
- WordPress-Updates über GitHub-Releases und update.json.
- Manuelle Update-Prüfung in der Plugin-Liste, Detailansicht und Unterstützung der WordPress-Auto-Update-Einstellung.
- Automatischer Release-Build mit festem Plugin-Ordner und Versionsprüfung.

## 1.0.2 – 2026-09-22

### New
- Verzögerung nach Complianz-Freigabe zwischen 0 und 60.000 ms einstellbar; Standard 300 ms.

## 1.0.1 – 2026-09-22

### New
- Complianz-Sperre für Standalone- und Elementor-Popups; Ticker bleiben sichtbar.
- Wiedergeöffnete Cookie-Einstellungen erhalten Vorrang. Abgelaufene Hinweise werden nicht nachträglich geöffnet.

## 1.0.0 – 2026-09-22

### New
- Separate Post Types für geplante Popups und Ticker.
- Standalone-Popup oder zentrales Elementor-Pro-Template.
- Pflichtzeitraum und Überschneidungsprüfung für Popups.
- Ticker-Shortcode mit Laufband und wechselnden Meldungen.
- Kompatible Praxis-Shortcodes und einmaliger Import des alten Hinweises als Entwurf.
