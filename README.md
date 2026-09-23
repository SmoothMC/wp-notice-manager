# WP Notice Manager by ZZZOOO · 1.0.9

Weiterentwicklung von **Praxis Popup Hinweis 0.9.6**, mit getrennten Post Types für Popups und Ticker. Der ursprüngliche Plugin-Ordner bleibt unverändert.

GitHub-Projekt: [SmoothMC/wp-notice-manager](https://github.com/SmoothMC/wp-notice-manager). Der technische WordPress-Ordner bleibt `notice-manager-by-zzzooo`, damit bestehende Installationen bei einem Update korrekt ersetzt werden.

## Installation

1. Den Ordner `notice-manager-by-zzzooo` nach `wp-content/plugins/` kopieren oder die ZIP-Datei über WordPress installieren.
2. „Praxis Popup Hinweis“ deaktivieren und „WP Notice Manager by ZZZOOO“ aktivieren. Solange das alte Plugin aktiv ist, startet der neue Manager nicht, um doppelte Popup-Steuerung und Shortcode-Konflikte zu vermeiden.
3. Unter **Notice Manager → Einstellungen** die Darstellung wählen.
4. Optional den gespeicherten Praxis-Hinweis mit **Als Entwurf übernehmen** importieren. Der Import kopiert Inhalt, Spalten und gültige Datumswerte; er verändert weder die alten Daten noch die neuen globalen Einstellungen. Fehlende Pflichtdaten vor der Veröffentlichung ergänzen.

Voraussetzungen: WordPress 6.0+, PHP 7.4+, aktuelle Browser mit nativem HTML-Dialog und Web Animations API. Standalone benötigt weder Elementor noch Divi. Elementor-Ausgabe benötigt Elementor Pro mit Popup-Modul.

## Installation und Updates ab 1.0.9

Das installierbare Paket heißt `wp-notice-manager-X.Y.Z.zip` und liegt unter [GitHub Releases](https://github.com/SmoothMC/wp-notice-manager/releases/latest). Bitte dieses ZIP verwenden, nicht GitHubs automatisch erzeugtes „Source code“-Archiv.

Versionen bis einschließlich 1.0.2 enthalten noch keinen Updater. Deshalb Version 1.0.9 einmalig über **Plugins → Installieren → Plugin hochladen** installieren und die vorhandene Version ersetzen. Der unveränderte Ordnername erhält die Zuordnung; Beiträge und Einstellungen bleiben gespeichert.

Ab 1.0.9 erscheinen neue stabile Releases in der normalen WordPress-Plugin-Verwaltung. **Nach Updates suchen** prüft bei Bedarf sofort. Über **Automatische Aktualisierungen aktivieren** entscheidest du, ob WordPress Updates selbst installieren darf. Anders als beim bisherigen WooSales-Updater werden automatische Installationen nicht erzwungen.

Die Metadaten kommen aus [update.json im neuesten Release](https://github.com/SmoothMC/wp-notice-manager/releases/latest/download/update.json), das ZIP aus demselben versionierten GitHub-Release. Dafür werden keine GitHub-Tokens in WordPress benötigt. Erfolgreiche Prüfungen werden sechs Stunden, fehlgeschlagene fünf Minuten zwischengespeichert. Die manuelle Prüfung umgeht diesen Cache. Das Repository und die Releases müssen öffentlich erreichbar bleiben.

## Neue Releases veröffentlichen

1. Version im PHP-Header und in `ZZZNM_VERSION` erhöhen, README und CHANGELOG aktualisieren; Tests ausführen und committen.
2. Einen passenden Tag `vX.Y.Z` pushen oder unter **Actions → Build and publish release → Run workflow** die Version eingeben.
3. Der Workflow prüft PHP und Update-Logik, baut das ZIP mit stabilem Ordnernamen und erzeugt passende Metadaten.
4. ZIP und `update.json` werden zuerst in einen Entwurf geladen; erst danach wird das Release veröffentlicht. Vorhandene Releases werden nicht überschrieben.

Lokal bauen: `python3 tools/build_release.py 1.0.9`. Die Ergebnisse liegen in `build/`. Abweichungen zwischen Version und Plugin-Header brechen den Build ab. Die automatisch generierte JSON-Datei liegt als Release-Anhang bereit; kein separates CDN oder SFTP-Zugang ist erforderlich.

## Popups planen

Unter **Notice Manager → Popups** einen Beitrag anlegen. Titel = sichtbare Überschrift, Editor = Inhalt. Start und Ende inklusive Uhrzeit sind zur Veröffentlichung Pflicht. Entwürfe dürfen unvollständig sein. Zeiten beziehen sich auf die WordPress-Zeitzone und werden als echte Zeitstempel gespeichert.

Veröffentlichte/geplante Popup-Beiträge reservieren ihren Zeitraum. Überschneidungen werden serverseitig verhindert. Bei ungültiger Veröffentlichung wird der bearbeitete Beitrag als Entwurf gespeichert; auch ein zuvor veröffentlichter Beitrag wird dann nicht mehr angezeigt. Der Inhalt bleibt erhalten und WordPress zeigt den Grund an. Entwürfe reservieren keine Zeit. Ende ist exklusiv: 10:00–12:00 und 12:00–14:00 sind erlaubt. Die Prüfung wird durch eine Datenbanksperre gegen gleichzeitige Speichervorgänge geschützt.

**Veröffentlichen** gibt den Hinweis für seinen Anzeigezeitraum frei. Das separate WordPress-Veröffentlichungsdatum unverändert lassen; die Hinweisplanung erfolgt über die Felder Start/Ende. Maximal ein Popup des Managers wird angezeigt. Andere Popup-Plugins werden nicht gesteuert.

Textspalten (1–6), Verzögerung und Wiederanzeige bleiben verfügbar. Auf kleinen Bildschirmen wird einspaltig ausgegeben. Schließzustände werden pro Popup-Beitrag im lokalen Browser-Speicher abgelegt; bei blockiertem Speicher gilt das Ausblenden nur für den laufenden Seitenaufruf.

## Elementor

Global **Elementor Pro** und ein veröffentlichtes **Standard-Elementor-Template** auswählen. Es gibt keine Template-Auswahl je Beitrag. Im Template diese Shortcodes einsetzen:

```text
[notice_popup_heading]
[notice_popup_text]
[notice_popup_notice]
```

`notice_popup_notice` enthält Überschrift und Text. Die Aliasse `praxis_popup_heading`, `praxis_popup_text` und `praxis_popup_notice` bleiben erhalten. Der Manager setzt den jeweils aktiven Popup-Inhalt ein, auch bei gecachtem Template-Markup. Shortcodes werden im Browser befüllt; im Builder-Editor gibt es keine Inhaltsvorschau. Im Elementor-Shortcode-Widget **Element Caching deaktivieren**, sofern dessen Verarbeitung Shortcodes unterdrückt.

Die eigenen automatischen Öffnungs-Trigger des Elementor-Templates deaktivieren, damit allein der Manager öffnet. Das Template wird über `add_popup_to_location` geladen. Fehlt Elementor Pro, ein gültiges Template oder das benötigte Frontend-Modul, dient Standalone als Rückfall. Nach einem Template-Wechsel den Seiten-/Elementor-Cache leeren, damit das neue Template-Markup auf allen Seiten vorhanden ist.

Die Popup-Shortcodes berücksichtigen nun den Aktivierungszustand und Zeitraum. Außerhalb aktiver Zeiträume geben sie keinen sichtbaren Inhalt aus. Für eine manuelle Einbindung kann der globale Modus weiterhin eingeschaltet bleiben; die Shortcodes ersetzen nicht den automatischen Popup-Auslöser.

## Complianz: Cookie-Banner hat Vorrang

Die integrierte Sperre gilt automatisch für **Standalone- und Elementor-Popups des Notice Managers**. Der Ticker wird nicht blockiert. Solange der Complianz-Banner offen ist oder dessen angekündigte Initialisierung noch aussteht, öffnet der Manager kein Popup. Zustimmung zu Marketing- oder Statistik-Cookies ist nicht erforderlich: Auch Ablehnen oder Speichern der eigenen Auswahl gibt das Popup frei, sobald Complianz den Banner als geschlossen meldet und er nicht mehr sichtbar ist.

Unter **Einstellungen → Popups → Verzögerung nach Complianz-Freigabe (ms)** ist die Wartezeit von 0 bis 60.000 ms einstellbar (Standard: 300 ms). 0 deaktiviert nur die zusätzliche Wartezeit, nicht die Banner-Sperre. Bestehende Installationen erhalten automatisch den Standardwert. Nach dieser Wartezeit lädt der Manager den aktuellen Anzeigeplan erneut und berücksichtigt zusätzlich die allgemeine Popup-Verzögerung. Ein inzwischen abgelaufener oder deaktivierter Hinweis wird nicht nachträglich geöffnet. Wird der Banner später erneut geöffnet, wird ein bereits sichtbares Manager-Popup geschlossen und anschließend wieder freigegeben; dies zählt nicht als manuelles Ausblenden durch den Besucher.

Die Integration nutzt die [Complianz-Banner-API und Events](https://complianz.io/help/wordpress/configuration/developers-guide-for-third-party-integrations/) sowie eine Prüfung der Banner-Sichtbarkeit. Es gibt keine pauschale Freigabe nach fünf Sekunden. Ist das Banner-Skript vorgesehen, wird aber dauerhaft blockiert oder nicht initialisiert, bleibt auch das Popup zurückgehalten. Ohne Complianz oder bei explizit deaktiviertem/nicht benötigtem Banner bleibt die normale Popup-Ausgabe verfügbar.

Das vorhandene **„Complianz – Popup Maker Guard“-MU-Plugin** kann daneben bestehen bleiben. Es wird weiterhin für andere **Popup-Maker-Popups** benötigt; Notice Manager steuert ausschließlich seine eigene Popup-Ausgabe und greift nicht in Popup Maker ein. Für ein Notice-Manager-Popup ist kein zusätzliches MU-Plugin nötig.

Nach dem Update Seiten-/Script-Cache leeren. Auf der Zielseite prüfen: neuer Besucher, akzeptieren, ablehnen, Einstellungen speichern, erneutes Öffnen der Cookie-Einstellungen und verzögertes Laden durch Optimierungsplugins.

## Ticker

Unter **Notice Manager → Ticker** mehrere Einträge anlegen. Der Titel dient intern der Verwaltung, der Editor enthält die sichtbare Meldung. Start/Ende sind optional; mehrere aktive Meldungen sind ausdrücklich erlaubt. Kleinere Werte im Feld Reihenfolge erscheinen zuerst, bei Gleichstand entscheidet die Beitrags-ID.

In ein shortcodefähiges Modul im Header, in Divi/Elementor oder in Seiteninhalte einfügen:

```text
[notice_ticker]
[notice_ticker mode="marquee" speed="45"]
[notice_ticker mode="rotate" interval="6"]
```

- `mode`: `marquee` = Laufband, `rotate` = wechselnde Meldungen.
- `speed`: 10–200 Pixel/Sekunde; nur Laufband.
- `interval`: 2–60 Sekunden; nur Wechselmodus.
- Ohne Attribute gelten die zentralen Einstellungen. Mehrere Shortcode-Instanzen auf einer Seite werden unterstützt.
- Keine aktiven Meldungen: Der gesamte Ticker einschließlich Bedienelementen bleibt ausgeblendet.
- Pause bei Mauszeiger/Fokus, zusätzlicher Pause-Schalter. Wechselmodus bietet eine Weiter-Taste. Bei reduzierter Bewegung startet die Ausgabe pausiert im manuell bedienbaren Wechselmodus.

Für Ticker kurze Texte verwenden. Formatierungen und Links sind erlaubt; verschachtelte Shortcodes werden aus Sicherheits- und Rekursionsgründen nicht ausgeführt.

## Gestaltung

CSS lässt sich im Theme oder Builder überschreiben. Zentrale Variablen:

```css
.zzznm-ticker {
  --zzznm-ticker-background: #193f33;
  --zzznm-ticker-color: #fff;
}
.zzznm-dialog {
  --zzznm-surface: #fff;
  --zzznm-color: #202824;
}
```

## Cache und Betrieb

Aktive Inhalte werden über einen öffentlichen, ausschließlich lesenden WordPress-AJAX-Endpunkt mit No-Cache-Headern geladen. Er liefert nur veröffentlichte, aktuell gültige und nicht passwortgeschützte Hinweise. Die Ausgabe wird spätestens nach 60 Sekunden bzw. an der nächsten Zeitgrenze aktualisiert. Ein geöffnetes Popup schließt zum Ende seines Zeitraums. Bei einem Abruffehler werden alte Inhalte ausgeblendet und der Abruf wiederholt. JavaScript und erreichbares `admin-ajax.php` sind erforderlich; ein Content-Blocker oder eine Firewall darf diese Anfrage nicht sperren.

Deaktivierung/Deinstallation löscht keine Beiträge oder Optionen. Es werden keine Änderungen an der ursprünglichen Plugin-Version vorgenommen.

## Prüfung vor dem Einsatz

Auf einer WordPress-Testinstallation prüfen: Standalone und Elementor Pro mit dem tatsächlichen Theme, Divi-Header mit beiden Ticker-Modi, Cache-Plugin, mobile Ansicht, Tastaturbedienung, aufeinanderfolgende Zeiträume und Wiederanzeige nach Änderungen. Eine lokale Browserprüfung ersetzt diesen Integrationstest nicht.

API-Referenzen: [WordPress wp_insert_post_data](https://developer.wordpress.org/reference/hooks/wp_insert_post_data/), [wp_after_insert_post](https://developer.wordpress.org/reference/functions/wp_after_insert_post/), [Elementor Popup Events](https://developers.elementor.com/elementor-pro-2-7-popup-events/).

### Bereiche und Ticker-Titel

In den Einstellungen lassen sich Popup und Ticker einzeln deaktivieren. Der jeweilige Posttyp wird dann nicht registriert; vorhandene Beiträge bleiben gespeichert. Pro Tickerbeitrag gibt es einen optionalen zusätzlichen sichtbaren Titel (z. B. „NEU“). Das Laufband trennt Titel und Meldungen mit • und läuft auch mit einer einzelnen Meldung nahtlos weiter. Bei reduzierter Bewegung bleibt die bedienbare Wechselansicht erhalten.

„Ticker-Steuerung anzeigen“ blendet Pause/Weiter ein oder aus. Bei ausgeblendeter Steuerung und reduzierter Bewegung werden alle Meldungen statisch angezeigt.

### Popup-Regeln und Divi

„Nach dem Schließen“ wird im jeweiligen Popup eingestellt. Ohne eigene Auswahl gilt „Erneut bei geändertem Inhalt oder Zeitraum“, auch bei bestehenden Popups; die frühere globale Einstellung wird nicht mehr verwendet.

Für Divi ein veröffentlichtes Layout in der Divi-Bibliothek mit dem Tag **Popup** versehen. Unter Einstellungen die Darstellung **Divi-Bibliothek** und das **Standard-Divi-Template** auswählen. Inhalte über `[notice_popup_heading]`, `[notice_popup_text]` oder `[notice_popup_notice]` einbinden. Das Layout erscheint im Plugin-Dialog. Ohne verfügbaren Renderer oder passendes Layout wird die Standalone-Ausgabe verwendet. Nach Template-Änderungen den Seiten-Cache leeren.

### Popup-Button

Im Popup die optionalen Felder **Button-Text** und **Button-Link** ausfüllen. Standalone und `[notice_popup_notice]` enthalten den Button automatisch, wenn beide Felder ausgefüllt sind. Bei eigenen Templates:

- `[notice_popup_button]` in ein Textmodul: vollständiger Button mit Text und Link.
- `[notice_popup_button_text]`: nur die Beschriftung, für Textinhalte (nicht für URL-Felder).
- Divi-Button- oder Bildmodul: als normale Link-URL `#notice-popup-link` eintragen, keine dynamische Quelle auswählen. Der aktive Popup-Link wird auch auf gecachten Seiten eingesetzt. Ohne gültigen Link wird dieses Element ausgeblendet.
- Divi-Button-Modul zusätzlich unter Erweitert → CSS-ID & Klassen die CSS-Klasse `zzznm-popup-button` geben: übernimmt auch den Button-Text. Im Button-Textfeld kann ein Platzhalter wie „Weitere Informationen“ stehen. Diese Klasse nur für Buttons verwenden, nicht für Bildmodule.

Bei fehlendem Button-Text oder Link bleibt der vollständige Button verborgen. HTTP(S), mailto, tel und interne Pfade sind möglich.

### Popup-Vorschau

Im Popup-Beitrag zuerst speichern, dann **Popup-Vorschau öffnen** anklicken. Die Vorschau öffnet die Startseite in einem neuen Tab und zeigt nur das gewählte Popup mit dem zentralen Template. Sie ist per Administrator-Berechtigung und zeitlich begrenztem Sicherheitslink geschützt. Zeitraum, Schließregel und Complianz-Sperre werden nur in der Vorschau ignoriert; sie veröffentlicht keinen Entwurf und verändert keine gespeicherten Besucherentscheidungen. Nach dem Schließen lässt sich das Popup über **Erneut öffnen** nochmals ansehen. Ungespeicherte Änderungen werden nicht gezeigt. Cache-Plugins müssen wie üblich eingeloggte Administratoren und Vorschau-URLs vom Seiten-Cache ausschließen.

### Seitenauswahl und Fade-in

Im jeweiligen Popup unter **Auf welchen Seiten anzeigen?** wählen: alle Seiten (Standard), nur Homepage, Archive und Beitragsübersicht, einzelne Blogbeiträge, alle WordPress-Seiten oder ausgewählte Einzelseiten. Mehrere konkrete Seiten mit Strg/Cmd auswählen. Eine leere Auswahl zeigt nichts an. „Homepage“ folgt der in WordPress konfigurierten Startseite; „Archive“ enthält auch die Beitragsübersicht, „Posts“ meint einzelne Beiträge vom Typ Beitrag. Die geschützte Vorschau ignoriert die Seiteneinschränkung. Zeiträume bleiben wie bisher global überschneidungsfrei. Nach Änderungen an der WordPress-Startseite den Seiten-Cache leeren.

Unter den globalen Popup-Einstellungen kann **Popup sanft einblenden (Fade-in)** abgeschaltet und die Dauer von 0 bis 5000 ms angepasst werden. Standard ist 300 ms. Die Startverzögerung bleibt unabhängig. Standalone und Divi blenden auch den Hintergrund ein; Elementor erhält eine zusätzliche Transparenzanimation. Für ein einheitliches Ergebnis zusätzliche Elementor-Eingangsanimationen im Template ausschalten. Bei reduzierter Bewegung wird nicht animiert.
