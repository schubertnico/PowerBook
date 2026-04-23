# PowerBook Userbereich - Improvement-Report
**Datum:** 2026-04-23
**Tester:** Senior-QA-Engineer (Claude)
**Testumgebung:** http://localhost:8080

> Diese Datei enthält nur **Workflow- und UX-Verbesserungen**. Bugs siehe `2026-04-23-Userbereichs-bugs.md`.

---

## Improvement-Liste

### IMP-001: AdminCenter-Home ohne Login zeigt Navigation & Einträge-Counts
- **Bereich:** AdminCenter Home
- **URL / Route:** `/pb_inc/admincenter/index.php` (kein Login)
- **Beobachtung:** Auch ohne gültige Session wird die Willkommensseite mit Schnellnavigation ("Einträge verwalten", "Admins verwalten", "Konfiguration"), PHP-Version, sowie Counts "Öffentliche Einträge: 1 / Versteckte Einträge: 0" angezeigt. Klicks auf die Links liefern jedoch nur den leeren Header-Bereich.
- **Problem im Workflow:** Anonyme User sehen Navigation zu geschützten Seiten, die sie nicht erreichen können. Klickt man, landet man auf einer quasi-leeren Seite ohne Fehlerhinweis.
- **Auswirkung:** Verwirrung, unnötige Preisgabe von Infrastruktur-Infos (PHP-Version, Admin-Navigation).
- **Verbesserungsvorschlag:**
  - Bei fehlender Session sofort nach `?page=login` weiterleiten.
  - Auf geschützten Routen statt Leer-Render klar anzeigen: "Bitte einloggen" + Link.
  - PHP-Version nur eingeloggten Admins zeigen.
- **Priorität:** Mittel

---

### IMP-002: Login-Formular ohne `required`-Attribute, autoComplete fehlt
- **Bereich:** Admin Login
- **URL / Route:** `/pb_inc/admincenter/index.php?page=login`
- **Beobachtung:** `<input name="name">` und `<input type="password">` haben weder `required`, `autocomplete="username"` noch `autocomplete="current-password"`.
- **Problem im Workflow:** Leerformular kann submitted werden (Server-Fallback greift, aber unnötige Roundtrips). Password-Manager erkennen Felder schlechter.
- **Auswirkung:** Schlechte Browser-Autofill-Unterstützung, kein Client-seitiger Blitz-Feedback.
- **Verbesserungsvorschlag:** `required`, `autocomplete="username"` bzw. `"current-password"`, `placeholder` hinzufügen.
- **Priorität:** Niedrig

---

### IMP-003: Fehlen einer expliziten "Bitte einloggen"-Page nach Session-Ablauf
- **Bereich:** Session-Timeout
- **URL / Route:** Alle geschützten `?page=*`-Routen
- **Beobachtung:** Nach Ablauf der PHP-Session bleibt man auf der geschützten Seite, sieht aber nur leeren Content-Bereich mit "Nicht eingeloggt"-Text in der Header-Leiste.
- **Problem im Workflow:** User weiß nicht, dass er neu einloggen muss; keine klare Call-to-Action.
- **Auswirkung:** User bleibt ratlos, navigiert manuell zurück oder refresht.
- **Verbesserungsvorschlag:** Bei protected-page + fehlender Session redirect auf `?page=login&next=<originalPage>`. Nach erfolgreichem Login zurück auf originale Seite.
- **Priorität:** Mittel

---

### IMP-004: Passwort-Reset — "Admin nicht gefunden" verrät Existenz (User Enumeration)
- **Bereich:** Password Recovery
- **URL / Route:** `/pb_inc/admincenter/index.php?page=password`
- **Beobachtung:** Nicht-existierender Name liefert "Admin in Datenbank nicht gefunden!" — existierender Name liefert Erfolgsmeldung.
- **Problem im Workflow:** Angreifer kann gültige Admin-Namen aufzählen.
- **Auswirkung:** Information Disclosure / Vorstufe zu gezieltem Brute Force.
- **Verbesserungsvorschlag:** Einheitliche generische Response: "Falls ein Account mit diesem Namen/E-Mail existiert, wurde eine E-Mail an die hinterlegte Adresse versandt."
- **Priorität:** Mittel (Security)

---

### IMP-005: Passwort-Reset ersetzt Passwort sofort — kein Recovery-Link
- **Bereich:** Password Recovery
- **URL / Route:** `/pb_inc/admincenter/index.php?page=password`
- **Beobachtung:** Jede Recovery-Anfrage generiert ein neues Passwort und ersetzt das alte sofort in der DB.
- **Problem im Workflow:** Ein Angreifer, der nur den Admin-Namen kennt, kann den legitimen Admin aussperren. Mailpit zeigt: neue Passwort im Klartext in der E-Mail.
- **Auswirkung:** Permanenter DoS gegen Admin. Schlechter Security-Standard.
- **Verbesserungsvorschlag:** Klassischer Token-Reset-Flow: E-Mail mit Einmal-Link (TTL 30 Min) → erst nach Klick wird Passwort zurückgesetzt. Bis dahin bleibt das alte Passwort gültig.
- **Priorität:** Hoch

---

### IMP-006: Standard-Admin-Passwort "powerbook" ist in `install_deu.php` hart codiert
- **Bereich:** Installation / Bootstrap
- **URL / Route:** `/install_deu.php`
- **Beobachtung:** Installationsscript legt Admin "PowerBook" mit Passwort "powerbook" an (`install_deu.php:178`).
- **Problem im Workflow:** Wörterbuch-Angriffe auf fresh installations extrem einfach. Das Standardpasswort ist öffentlich (im Install-Script sichtbar).
- **Auswirkung:** Volle Übernahme einer neu installierten Instanz in Sekunden.
- **Verbesserungsvorschlag:** Erstes Admin-Passwort während der Installation interaktiv abfragen und erzwingen (8+ Zeichen, Mix). Alternativ sicheres Zufallspasswort generieren und am Ende einmalig anzeigen.
- **Priorität:** Hoch

---

### IMP-007: install_deu.php nach Installation nicht automatisch gesperrt
- **Bereich:** Installation / Bootstrap
- **URL / Route:** `/install_deu.php`
- **Beobachtung:** Installationsscript bleibt erreichbar, Hinweis "Bitte löschen Sie diese Datei" wird nur angezeigt — nicht erzwungen. (Siehe auch BUG-011.)
- **Problem im Workflow:** Manuelles Löschen wird oft vergessen.
- **Auswirkung:** Angreifer können DB wipen & Default-Admin re-installieren.
- **Verbesserungsvorschlag:** Nach erfolgreicher Installation ein Lock-File `pb_inc/.installed` anlegen. Beim nächsten Aufruf direkt 403 mit Hinweis "Bereits installiert".
- **Priorität:** Hoch

---

### IMP-008: Admin-Add-Workflow bricht bei SMTP-Fehler still ab
- **Bereich:** Admin-Management
- **URL / Route:** `/pb_inc/admincenter/index.php?page=admins` (action=add)
- **Beobachtung:** Der Flow versucht, über `sendAdminEmail()` eine E-Mail mit Temp-Passwort zu verschicken. Aktuell: Fatal Error wegen Function-Loading (BUG-006). Unabhängig davon: selbst wenn SMTP fehlschlägt, würde der neue Admin in der DB existieren, aber das Passwort wäre nicht kommuniziert.
- **Problem im Workflow:** Kein Fallback zur UI-Anzeige des Temp-Passworts, falls Mail nicht versandt werden konnte.
- **Auswirkung:** Admin ist angelegt, kann sich aber nicht einloggen (niemand kennt das Temp-Passwort).
- **Verbesserungsvorschlag:**
  - Beim Fail der Mail: Temp-Passwort direkt im AdminCenter anzeigen (dezent, einmalig, mit Warnung "Notieren Sie das Passwort jetzt").
  - Oder: Transaktional — wenn Mail fehlschlägt, Rollback.
- **Priorität:** Hoch

---

### IMP-009: Keine Anzeige des temporären Passworts beim Admin-Anlegen — Admin bekommt E-Mail im Klartext
- **Bereich:** Admin-Management
- **URL / Route:** `/pb_inc/admincenter/index.php?page=admins`
- **Beobachtung:** Ein neu angelegter Admin erhält das Temp-Passwort per unverschlüsselter E-Mail.
- **Problem im Workflow:** E-Mail-Versand im Klartext ist eine bekannte Schwachstelle; Passwort kann in Log-Systemen landen.
- **Auswirkung:** Credential-Exposure bei Mail-Abhören.
- **Verbesserungsvorschlag:** Stattdessen einen Einmal-Setup-Link (TTL kurz) per Mail schicken, Admin setzt Passwort selbst beim ersten Login.
- **Priorität:** Mittel

---

### IMP-010: Gästebuch-Form hat keinen Fortschritts-/Abschickenstatus
- **Bereich:** Gästebuch-Frontend
- **URL / Route:** `POST /pbook.php`
- **Beobachtung:** Nach Klick auf "Abschicken" (Preview) oder "Eintragen!" gibt es keinen Lade-Indikator.
- **Problem im Workflow:** User klicken evtl. mehrfach → mehrere Einträge? (Ja, Spam-Check greift nach 30 s).
- **Auswirkung:** Irritation bei langsamer Internetverbindung.
- **Verbesserungsvorschlag:** Submit-Button nach Klick deaktivieren + Lade-Spinner.
- **Priorität:** Niedrig

---

### IMP-011: Preview zeigt Benutzereingaben doppelt HTML-escaped
- **Bereich:** Gästebuch-Preview
- **URL / Route:** `POST /pbook.php` (preview=yes)
- **Beobachtung:** Name mit `<script>` wird als `&amp;lt;script&amp;gt;` angezeigt (siehe BUG-003). Preview zeigt also nicht, was der User tatsächlich eingegeben hat.
- **Problem im Workflow:** User kann Preview nicht verlässlich nutzen. Wer im Namen Unicode/Sonderzeichen benutzt, sieht Entities als Text.
- **Auswirkung:** Preview ist unzuverlässig.
- **Verbesserungsvorschlag:** Escape im Preview nur einmal anwenden. Hidden-Fields mit Rohwert transportieren, Escape beim finalen Render vornehmen.
- **Priorität:** Mittel

---

### IMP-012: Kein Logout-Bestätigungsschritt — Session-Hijack-Absicherung
- **Bereich:** Admin Logout
- **URL / Route:** `?page=logout` (GET)
- **Beobachtung:** Logout funktioniert sowohl per GET (`?logout=yes` oder `?page=logout`) als auch per POST.
- **Problem im Workflow:** GET-basierter Logout ist CSRF-anfällig. Ein Angreifer kann eine Admin-Seite mit `<img src="http://site/pb_inc/admincenter/index.php?page=logout">` einbauen → Logout via Cross-Origin.
- **Auswirkung:** Denial-of-Admin-Service (nervig, aber nicht kritisch).
- **Verbesserungsvorschlag:** Logout nur per POST + CSRF-Token zulassen.
- **Priorität:** Niedrig

---

### IMP-013: Paginierungs-Helper `pages.inc.php` in AllowedPages-Whitelist
- **Bereich:** AdminCenter-Routing
- **URL / Route:** `?page=pages`, `?page=emails`, `?page=empty`
- **Beobachtung:** Siehe BUG-004. Diese "Seiten" sind keine Admin-Views, nur Helfer/Placeholder.
- **Problem im Workflow:** Direkter Aufruf liefert blanke Seite ohne Fehlermeldung.
- **Auswirkung:** Verwirrung.
- **Verbesserungsvorschlag:** Aus der Whitelist entfernen oder explizit ungültig.
- **Priorität:** Niedrig

---

### IMP-014: Admin-Edit-Form erlaubt leeres Passwort-Feld — aber unklar, was das bedeutet
- **Bereich:** AdminCenter — Admin bearbeiten
- **URL / Route:** `?page=admins` (Edit-Modus)
- **Beobachtung:** `edit_password1`/`edit_password2` können leer gelassen werden → Passwort bleibt unverändert. Kein Hinweis für User.
- **Problem im Workflow:** Unklar, ob leeres Feld "Passwort leeren" oder "unverändert" bedeutet.
- **Auswirkung:** Verwirrende UX. Ein User könnte aus Versehen das Passwort nicht ändern, wenn er wollte.
- **Verbesserungsvorschlag:** Info-Text "Leer lassen = unverändert" unter dem Feld. Oder zwei separate Buttons "Passwort ändern" und "Speichern".
- **Priorität:** Niedrig

---

### IMP-015: Guestbook-Formular akzeptiert URLs im `url`-Feld, prefixt aber blind `http://`
- **Bereich:** Gästebuch-Formular
- **URL / Route:** `POST /pbook.php`
- **Beobachtung:** Das Homepage-Feld wird mit `http://`-Präfix gerendert. Gibt der User `javascript:alert(1)` ein, wird daraus `http://javascript:alert(1)` — gebrochener Link. Aber bei `example.com/path?q=1&x=2` wird `http://example.com/path?q=1&x=2` (OK). Bei `file://c:\...` wird `http://file://...` (grotesk).
- **Problem im Workflow:** Keine echte URL-Validierung. Parser-Inkonsistenz.
- **Auswirkung:** Unschöne gebrochene Links; keine XSS, aber Datenintegrität.
- **Verbesserungsvorschlag:** `filter_var($homepage, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED)` und nur `http/https` erlauben. Sonst Feld ignorieren.
- **Priorität:** Mittel

---

### IMP-016: Fehlende Längenvalidierung auf Server-Seite
- **Bereich:** Gästebuch-Formular, Admin Entry-Edit
- **URL / Route:** `POST /pbook.php`, `POST ?page=edit`
- **Beobachtung:** Siehe BUG-008. Nur `maxlength="X"` im HTML (Client-Seite umgehbar).
- **Problem im Workflow:** Kein Abfangen von langen Payloads.
- **Auswirkung:** Performance, Speicherplatz, Layout-Bruch.
- **Verbesserungsvorschlag:** Server-seitige Längenprüfung + klare Fehlermeldung.
- **Priorität:** Mittel

---

### IMP-017: Fehlende Rate-Limit-Logik für wiederholte Login-Fehler
- **Bereich:** Admin Login
- **URL / Route:** `POST /pb_inc/admincenter/index.php?page=login`
- **Beobachtung:** Fehlgeschlagene Logins werden geloggt (security.log), aber **nicht gezählt**. Keine temporäre Sperre nach X Fehlversuchen.
- **Problem im Workflow:** Brute Force ist ohne Reibung möglich.
- **Auswirkung:** Passwort-Cracking-Angriffe möglich.
- **Verbesserungsvorschlag:** Nach z. B. 5 fehlgeschlagenen Logins pro IP/Account innerhalb 10 Min: Block oder Captcha.
- **Priorität:** Mittel

---

### IMP-018: Smilies-Hilfefenster verwendet `window.open` mit festen Pixel-Maßen
- **Bereich:** Gästebuch-Formular
- **URL / Route:** `/pbook.php`
- **Beobachtung:** `SmiliesHelp()` öffnet ein klassisches Popup (175×260 Px), `TextHelp()` (320×170 Px).
- **Problem im Workflow:** Popup-Blocker verhindern oft den Aufruf. Mobile-User sehen das gar nicht.
- **Auswirkung:** Hilfe-Funktion nicht erreichbar für viele User.
- **Verbesserungsvorschlag:** Modal/Dialog im selben Dokument (z.B. `<dialog>`) oder Lightbox.
- **Priorität:** Niedrig

---

### IMP-019: Guestbook-Paginierung springt in 15er-Schritten, aber pro-Seite-Config ignoriert
- **Bereich:** Admin Entry-Liste Paginierung
- **URL / Route:** `?page=entries&tmp_start=N`
- **Beobachtung:** `entries.inc.php:33` hat `$perPage = 15;` fest codiert. Konfiguration `show_entries` (10) wird nicht berücksichtigt.
- **Problem im Workflow:** Admin-Pagination inkonsistent zur Frontend-Pagination.
- **Auswirkung:** Kosmetisch.
- **Verbesserungsvorschlag:** `$perPage = $config_show_entries ?? 15;`.
- **Priorität:** Niedrig

---

### IMP-020: Keine sichtbare Bestätigung nach Konfigurations-Save
- **Bereich:** AdminCenter Configuration
- **URL / Route:** `POST ?page=configuration`
- **Beobachtung:** Bei gültigen Daten wird die Konfiguration gespeichert, aber es gibt keine grüne Erfolgsmeldung am Seitenanfang.
- **Problem im Workflow:** User bleibt unsicher, ob das Speichern geklappt hat.
- **Auswirkung:** User klickt mehrfach.
- **Verbesserungsvorschlag:** Erfolgs-Banner wie in `release.inc.php` implementieren (grün, oben).
- **Priorität:** Mittel

---

### IMP-021: Entry-Edit-Form zeigt Statement-Feld nicht — getrennter Workflow
- **Bereich:** AdminCenter Entry Edit
- **URL / Route:** `?page=edit&edit_id=N`
- **Beobachtung:** Um ein Statement zu verfassen/löschen muss man eine separate Seite `?page=statement&id=N` aufrufen. In Entry-Edit gibt's kein Statement-Feld.
- **Problem im Workflow:** Zwei Klicks für Zusammenarbeit zwischen Eintrags-Edit und Statement.
- **Auswirkung:** Ineffizient.
- **Verbesserungsvorschlag:** Statement-Feld in Entry-Edit integrieren — oder per Tab.
- **Priorität:** Niedrig

---

### IMP-022: Keine Paginierung bei Release-Seite / Statement-Übersicht
- **Bereich:** AdminCenter Release / Entries
- **URL / Route:** `?page=release`
- **Beobachtung:** `release.inc.php` lädt ALLE unreleased Einträge (`SELECT * FROM entries WHERE status='U'`) ohne LIMIT.
- **Problem im Workflow:** Bei spammigem Gästebuch mit 1000+ unfreigeschalteten Einträgen: Out-of-Memory / langsame Seite.
- **Auswirkung:** Skalierungsproblem.
- **Verbesserungsvorschlag:** Paginieren analog zu `entries.inc.php`.
- **Priorität:** Niedrig

---

### IMP-023: Fehlende Hinweis-Meldung, wenn ein Admin sich selbst löschen/entrechtigen möchte
- **Bereich:** AdminCenter Admin-Management
- **URL / Route:** `?page=admins` (edit/delete)
- **Beobachtung:** Code zeigt "Sie können sich selbst nicht löschen!" / "SuperAdmin kann nicht gelöscht werden!" — aber diese Meldungen werden nur im Message-Banner angezeigt. Die Delete-Checkbox ist aber clickable.
- **Problem im Workflow:** Nach Klick erwartet User Aktion; stattdessen Fehler.
- **Auswirkung:** Reibung.
- **Verbesserungsvorschlag:** Delete-Checkbox + Submit-Button für SuperAdmin / Self-Edit disablen mit `disabled` und Tooltip.
- **Priorität:** Niedrig

---

### IMP-024: JavaScript-basiertes "Zurück"-Konzept (history.back()) für Fehlermeldungen
- **Bereich:** Edit, Statement, Suche (keine Treffer)
- **URL / Route:** `?page=edit`, `?page=statement`, `?tmp_search=nothing`
- **Beobachtung:** Diverse Fehlertexte nutzen `<a href="javascript:history.back()">`, was bei Direktaufruf leer führt (keine History).
- **Problem im Workflow:** Direkt-Links aus E-Mails / Bookmarks produzieren Sackgassen.
- **Auswirkung:** Navigation ineffektiv.
- **Verbesserungsvorschlag:** Statt JS-History einen echten Link auf eine sinnvolle Rückseite (`?page=entries`, `?page=home`).
- **Priorität:** Niedrig

---

### IMP-025: Keine Session-Timeout-Kommunikation — absolute/idle Timeouts fehlen
- **Bereich:** Sitzung / Cookie
- **URL / Route:** Alle geschützten
- **Beobachtung:** `session.gc_maxlifetime` und PHP-Defaults bestimmen Session-Ablauf. Kein explizites Timeout/Re-Auth.
- **Problem im Workflow:** User weiß nicht, wann Session abläuft.
- **Auswirkung:** Bei langen Editier-Sessions geht Formular-Inhalt verloren.
- **Verbesserungsvorschlag:** Explizites Idle-Timeout (z. B. 30 Min), Keep-Alive bei Aktivität. Warnung kurz vor Ablauf.
- **Priorität:** Niedrig

---

### IMP-026: Standard-Texte enthalten Platzhalter (leere `<b></b>`)
- **Bereich:** Gästebuch-Frontend
- **URL / Route:** `/pbook.php`
- **Beobachtung:** Output enthält `<div align="center"><b></b></div>` (leer) — Artefakt der Message-Logik wenn `$message` leer ist.
- **Problem im Workflow:** Keine sichtbare Auswirkung, aber unsauberes HTML.
- **Verbesserungsvorschlag:** Nur rendern wenn `$message` nicht leer.
- **Priorität:** Niedrig

---

### IMP-027: Admin-Icon-Auswahl ist begrenzt auf wenige Icons — keine Upload-Option
- **Bereich:** Gästebuch-Formular
- **URL / Route:** `/pbook.php`
- **Beobachtung:** Icon-Dropdown: no, text, question, mark, shock, sad2, happy1, happy5.
- **Problem im Workflow:** Klassische Style-Konventionen, statische Auswahl.
- **Auswirkung:** Kein Problem, nur begrenzt.
- **Verbesserungsvorschlag:** Modern: Emoji-Picker oder Avatar-System.
- **Priorität:** Niedrig

---

### IMP-028: ICQ-Feld ist Legacy — in 2026 irrelevant
- **Bereich:** Gästebuch-Formular & Admin
- **Beobachtung:** ICQ-Nummer-Feld + Settings-Toggle bleiben im Code.
- **Problem im Workflow:** ICQ existiert seit 2020 nicht mehr.
- **Auswirkung:** Toter Code, mehr Feldüberladung.
- **Verbesserungsvorschlag:** ICQ-Feature entfernen / als ersetzte Contact-Felder (Discord/Telegram/Matrix) umbauen.
- **Priorität:** Niedrig

---

### IMP-029: Standard-HTML-Layout nutzt `<table>`, `<font>`, `bgcolor=` usw. — HTML 4.01 Ära
- **Bereich:** Gesamtes UI
- **Beobachtung:** Komplett Tabellen-Layout + inline style Attribute + Frame-lese-Konzepte.
- **Problem im Workflow:** Schlechte Mobile-Tauglichkeit, Accessibility-Defizite, schwer zu customizen.
- **Auswirkung:** Unzeitgemäß.
- **Verbesserungsvorschlag:** Re-Design mit CSS-Grid/Flexbox, semantischem HTML5.
- **Priorität:** Niedrig (schon bekannter Legacy-Status laut README)

---

### IMP-030: Kein Breadcrumb/Current-Page-Highlight in der AdminCenter-Navigation
- **Bereich:** AdminCenter
- **URL / Route:** Alle `?page=*`
- **Beobachtung:** Die Navigation (Einträge | Admins) bleibt konstant, das aktuelle Tab wird nicht hervorgehoben.
- **Problem im Workflow:** User verliert Orientierung auf Tief-Seiten (Edit, Statement).
- **Auswirkung:** Kleiner UX-Makel.
- **Verbesserungsvorschlag:** Active-Navigation per `if ($page === 'entries')` styling. Breadcrumb-Leiste "AdminCenter › Einträge › Bearbeiten".
- **Priorität:** Niedrig

---

### IMP-031: Keine Auto-Refresh/Lazy Loading auf Home-Dashboard
- **Bereich:** AdminCenter Home
- **URL / Route:** `?page=home`
- **Beobachtung:** Einträge-Counter wird nur beim Seiten-Refresh aktualisiert.
- **Problem im Workflow:** Admin sieht keine Live-Updates während er z. B. freischaltet.
- **Auswirkung:** Klein.
- **Verbesserungsvorschlag:** AJAX-Polling für Counts.
- **Priorität:** Sehr niedrig

---

### IMP-032: Fehlende Empty-State-Illustrationen
- **Bereich:** AdminCenter Entries / Release
- **URL / Route:** `?page=entries`, `?page=release`
- **Beobachtung:** "Keine Einträge vorhanden" ist nur Text.
- **Problem im Workflow:** Kalter Eindruck, keine Handlungsanleitung.
- **Verbesserungsvorschlag:** Empty State mit Icon + Handlungsaufforderung ("Neuer Eintrag über die öffentliche Seite").
- **Priorität:** Sehr niedrig

---

### IMP-033: Fehlende Export-Funktion für Gästebuch-Einträge
- **Bereich:** AdminCenter Entries
- **URL / Route:** `?page=entries`
- **Beobachtung:** Keine Möglichkeit, Einträge als CSV/JSON/Backup zu exportieren.
- **Problem im Workflow:** Migration/Backup schwierig.
- **Verbesserungsvorschlag:** Export-Button für Admins mit Konfigurationsrecht.
- **Priorität:** Niedrig

---

### IMP-034: Passwort-Feld zeigt keine Passwort-Stärke-Anzeige
- **Bereich:** Admin-Add / Passwort-Change
- **URL / Route:** `?page=admins`
- **Beobachtung:** Fehlt klassischer Stärke-Bar-Indikator.
- **Verbesserungsvorschlag:** Bei Passwort-Eingabe live anzeigen (zxcvbn o.ä.).
- **Priorität:** Niedrig

---

### IMP-035: Statements werden nur als `<i>` rendered — BBCode/Markdown minimal
- **Bereich:** Admin-Statement + Public-Render
- **URL / Route:** `/pbook.php`
- **Beobachtung:** Statement wird im public-View als kursives Italic gerendert. Keine Formatierung über BBCode hinaus.
- **Verbesserungsvorschlag:** Admin-Statement visuell deutlicher abgesetzt (Box, Farbe, Timestamp).
- **Priorität:** Niedrig

---

### IMP-036: IP-Adresse wird im AdminCenter im Klartext angezeigt — keine Privacy-Hinweise
- **Bereich:** AdminCenter Entries
- **URL / Route:** `?page=entries`
- **Beobachtung:** Jeder Eintrag zeigt: "IP: 172.27.0.1".
- **Problem im Workflow:** DSGVO-relevant — IPs dürfen ohne Zweck nicht dauerhaft gespeichert/angezeigt werden.
- **Auswirkung:** Compliance-Risiko.
- **Verbesserungsvorschlag:** IP nur für Admins mit entsprechender Rolle, nach X Tagen anonymisieren. DSGVO-Hinweis.
- **Priorität:** Mittel

---

### IMP-037: Einzeiler-Logout hat keinen visuellen Abschied
- **Bereich:** Logout
- **URL / Route:** `?page=logout`
- **Beobachtung:** Nach Logout sieht User das Login-Formular, keinerlei "Sie wurden abgemeldet"-Bestätigung.
- **Verbesserungsvorschlag:** Erfolgs-Banner "Sie wurden erfolgreich abgemeldet.".
- **Priorität:** Niedrig

---

### IMP-038: Kein Hinweis auf Browser-Support / Javascript-Required bei `SmiliesHelp`
- **Bereich:** Gästebuch-Formular
- **Beobachtung:** Wenn JS deaktiviert: "(Hilfe)"-Links toter Anker.
- **Verbesserungsvorschlag:** No-JS-Fallback: Hilfe in separatem Anker-Tab öffnen.
- **Priorität:** Sehr niedrig

---

### IMP-039: `thanks`-Mail-Versand für Besucher fehlt SMTP-Feedback
- **Bereich:** Gästebuch → Thank-Email
- **URL / Route:** `pb_inc/thank-email.php` (inklusioniert)
- **Beobachtung:** Aus `send-email`-Log-Einträgen sieht man "Email Error: Empty recipient" — wenn Besucher keine Mail angibt, scheitert der Versand silently.
- **Problem im Workflow:** Admin kriegt nur via Log mit, dass Mail-Versand fehlschlägt.
- **Verbesserungsvorschlag:** Nur bei vorhandener Adresse versuchen; Admin-Log-UI mit Mail-Fehlern.
- **Priorität:** Niedrig

---

### IMP-040: Fehlende Security-Header (CSP, X-Frame-Options, Referrer-Policy)
- **Bereich:** HTTP-Response-Header
- **Beobachtung:** Keine strikte `Content-Security-Policy`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` Header gesetzt.
- **Problem im Workflow:** Clickjacking-Gefahr; Ressourcen können easily externalized werden.
- **Verbesserungsvorschlag:** In Apache-Config oder PHP `header()` entsprechende Header ausliefern. Mindestens `X-Frame-Options: DENY`.
- **Priorität:** Mittel

---

### IMP-041: Cookies ohne explizite `Secure`/`SameSite`-Attribute
- **Bereich:** PHP Session Cookie
- **Beobachtung:** PHPSESSID Cookie enthält `HttpOnly` (gut), aber vermutlich kein `SameSite=Strict` und `Secure` (je nach Umgebung).
- **Problem im Workflow:** Session-Hijack-Risiko über HTTPS-Downgrade/CSRF erhöht.
- **Verbesserungsvorschlag:** In `session_set_cookie_params()` explizit `'samesite' => 'Strict'`, `'secure' => true` (falls HTTPS).
- **Priorität:** Mittel

---

## Priorität-Summary

| Priorität | Anzahl | IMPs |
|-----------|--------|------|
| Hoch      | 3      | IMP-005, IMP-006, IMP-007, IMP-008 |
| Mittel    | 11     | IMP-001, IMP-003, IMP-004, IMP-009, IMP-011, IMP-015, IMP-016, IMP-017, IMP-020, IMP-036, IMP-040, IMP-041 |
| Niedrig   | 20+    | Rest |

