# Werbe Manager – Installations- und Nutzungshandbuch

Diese Anleitung beschreibt Schritt für Schritt, wie du das Plugin **Werbe Manager** installierst, konfigurierst und in WordPress nutzt.

> **Voraussetzung:** WordPress ≥ 6.5, PHP ≥ 8.1. Das Plugin wurde bereits in `/wp-content/plugins/werbe-manager/` abgelegt (siehe `werbe-manager.php`).

---

## 1. Aktivierung

1. Melde dich im WordPress-Backend an (`/wp-admin`).
2. Navigiere zu **Plugins → Installierte Plugins**.
3. Aktiviere **Werbe Manager**.
4. Beim Aktivieren werden automatisch vier Tabellen angelegt:
   - `wp_wm_slots`
   - `wp_wm_campaigns`
   - `wp_wm_campaign_slot`
   - `wp_wm_creatives`

*(Bei Multisite: Aktivierung entweder pro Site oder Network Activation.)*

---

## 2. Plugin-Struktur

```
werbe-manager/
├── werbe-manager.php           # Plugin Bootstrap
├── README.md                   # Kurzanleitung
├── INSTALLATION.md             # Diese Datei
├── includes/
│   ├── plugin.php              # Core Bootstrap
│   ├── db/
│   │   ├── migrations.php      # CREATE TABLE / dbDelta
│   │   └── repository.php      # CRUD-Hilfsklasse
│   ├── admin/admin.php         # Admin-Menüs & Formulare
│   ├── frontend/
│   │   ├── resolver.php        # Kampagnenlogik & Creative-Rendering
│   │   └── shortcode.php       # [promo_slot]-Shortcode
│   └── support/
│       ├── context.php         # Kontextinformationen (Post, Kategorien …)
│       └── assets.php          # Textdomain, Styles, Scripts
└── assets/
    └── css/unit.css            # Grundlegende Styles (.promo-slot, .promo-unit)
```

---

## 3. Admin-Oberfläche

Nach der Aktivierung findest du im Backend das Menü **Werbe Manager** (Megaphon-Icon). Die Oberfläche ist in Tabs unterteilt:

| Tab          | Zweck | Relevante Felder |
|--------------|-------|------------------|
| **Slots**    | Plätze für Werbeeinheiten (z. B. `home_third_1`) | Slug, Titel, Format (`third`, `half`, …), erlaubte Post-Types, CSS-Klasse |
| **Kampagnen**| Kampagnen-Definition mit Targeting | Slug, Titel, Status, Gewicht, Formate, Brand-In/Exclude, Post-Type-Filter, Kategorien/Tags, URL-Like, Zeitfenster |
| **Creatives**| Konkrete Creatives je Kampagne | Kampagnen-ID, Typ (`shortcode`, `html`, `image`), Payload als JSON |
| **Slot-Zuordnung** | Kampagnen einem Slot mit Gewicht zuweisen | Slot auswählen, `campaign_id:weight`-Paare eingeben |

### 3.1 Slots anlegen

1. Öffne Tab **Slots**.
2. Fülle das Formular aus:
   - **Slug** (z. B. `home_third_1`, nur Kleinbuchstaben/Zahlen/`-_`)
   - **Titel** (Sichtbarer Name)
   - **Format** (z. B. `third`)
   - **Post Types** (CSV, Standard `post,page`)
   - Optionale CSS-Klasse
3. Klicke **Slot speichern**.
4. Unterhalb wird eine Tabelle aller Slots angezeigt; von dort kannst du Slots löschen.

### 3.2 Kampagnen anlegen

1. Öffne Tab **Kampagnen**.
2. Formularfelder:
   - **Slug** (z. B. `roboforex_third`)
   - **Titel**
   - **Status** (`active` oder `paused`)
   - **Gewicht** (Standard 10; beeinflusst die Ausspielwahrscheinlichkeit)
   - **Formate** (CSV, z. B. `third` oder `third,half`)
   - **Brands Include/Exclude** (Slugs, CSV)
   - **Post Types** (Standard `post,page`)
   - **Kategorien/Tags ein-/ausschließen** (Slugs, CSV)
   - **URL enthält** (SQL-LIKE; `%` als Wildcard, z. B. `%breakout-brain%`)
   - Weitere Felder können später ergänzt werden (z. B. Start-/Enddatum via DB).
3. Formular via Button **Kampagne speichern** sichern.
4. Die darunterliegende Liste zeigt alle Kampagnen (mit Löschbutton).

> *Hinweis:* In dieser ersten Version werden Start-/Endzeiten, Frequency-Caps etc. in den Tabellen gespeichert, aber noch nicht über die Admin-Form gepflegt. Sie können bei Bedarf über phpMyAdmin/CLI gesetzt werden.

### 3.3 Creatives anlegen

1. Tab **Creatives** öffnen.
2. Formularfelder:
   - **Kampagnen-ID** (siehe Liste im Mapping-Tab; ID aus DB)
   - **Typ** `shortcode`, `html`, `image`
   - **Payload (JSON)** – Beispiele:
     - *Shortcode:* `{ "slug": "roboforex_card", "atts": {"flip1":"4000"} }`
     - *HTML:* `{ "html": "<div class=\"promo-card\">Eigener HTML Inhalt</div>" }`
     - *Bild:* `{ "src": "https://…/banner.png", "href": "https://…", "alt": "Anzeige" }`
3. **Creative speichern** – JSON wird validiert (falls leer/ungültig, passiert nichts).
4. Liste zeigt alle Creatives, inklusive Zugehöriger Kampagne und Payload.

### 3.4 Slot-Zuordnung

1. Tab **Slot-Zuordnung** öffnen.
2. Slot auswählen (Drop-down).
3. Feld **Kampagnen:Gewicht** im Format `campaignID:weight` (CSV). Beispiel: `1:10,2:5` bedeutet Kampagne 1 Gewicht 10, Kampagne 2 Gewicht 5.
4. Speichern; vorherige Zuordnungen werden ersetzt.
5. Darunter befindet sich eine „Referenzliste“ mit `ID – Titel`, damit du die IDs kennst.

---

## 4. Shortcode `[promo_slot]`

Einbindung: ` [promo_slot slot="home_third_1" shuffle="day" brand_include="roboforex"]`

**Parameter**

- `slot` *(erforderlich)*: Slug des Slots.
- `shuffle`: `none|hour|day|session` (steuert das Cache-Fenster). Default `day`.
- `brand_include`, `brand_exclude`: CSV (Slugs). Wenn gesetzt, filtert gegen Kampagnen-Brand-Felder.
- `category_include`, `category_exclude`, `tag_include`, `tag_exclude`: Slug-Listen für Feintuning.
- `include_ids`, `exclude_ids`: CSV mit Post-IDs (nützlich, wenn Shortcode global platziert wird und nur auf bestimmten Posts wirken soll).
- `fallback`: Optionaler Inhalt (Shortcode/HTML). Wird gerendert, wenn kein Creative eligible ist.

### Beispiele

- **Startseite, Tagesrotation:**
  ```
  [promo_slot slot="home_third_1" shuffle="day"]
  ```
- **Breakout-Brain-Seite:**
  ```
  [promo_slot slot="content_third_right"
              shuffle="hour"
              brand_include="roboforex"
              brand_exclude="tegasfx"
              category_include="expert-advisor"
              fallback="[roboforex_card]"]
  ```

Der Resolver nutzt die aktuelle Seite (Post-ID, Kategorien, Tags, URL, Post-Type). Marken-Filter kommen aus Campaign + Shortcode. Shuffle sorgt dafür, dass z. B. `shuffle="day"` einen ganzen Tag lang dieselbe Kampagne zeigt (deterministisch per Hash). `shuffle="session"` speichert eine Session-ID in einem Cookie (`wm_session`).

---

## 5. Wie Rendern & Targeting funktionieren

1. Slot-Slug → Slot-Tabellenzeile.
2. Kampagnen, die diesem Slot zugeordnet wurden (siehe `campaign_slot`), werden gefiltert:
   - Status = `active`
   - Zeitfenster (Start/Ende) [vorausgesetzt, Werte in DB gesetzt]
   - Slot-Format (`third`, `half`, …)
   - Post-Type, Kategorien, Tags, Post-IDs, URL-Like
   - Brand-Filter (Kampagne & Shortcode)
3. Weighted Random: Kampagnengewicht aus Zuordnung (oder Kampagnen-Default). Hash (`slot|context|shuffle-window`) liefert deterministische Auswahl.
4. Creative wird aus Liste der Kampagne zufällig (deterministisch) gewählt.
5. Creative-Typen:
   - `shortcode` → `[slug attr=…]` via `do_shortcode`
   - `html` → `wp_kses_post`
   - `image` → `<img>` + optional `<a>`
6. Ausgabe: `<section class="promo-slot promo-slot--home_third_1 promo-campaign--roboforex_third"><div class="promo-unit">…</div></section>`.

Adblock-Schutz: keine Klassen/IDs mit `ad/advert`, CSS/Javascript-Dateien heißen `unit.css`, `engine.js` (noch nicht implementiert), Endpunkte `wm_*`.

---

## 6. Ideen für Kampagnen (Start)

| Slot               | Kampagnen (Beispiel)                              | Creative-Payload Beispiel |
|--------------------|--------------------------------------------------|---------------------------|
| `home_third_1`     | `roboforex_third`, `tegasfx_third`, `newsletter` | `{"slug":"roboforex_card"}` |
| `content_third_right` | `roboforex_third`, `breakout_brain_third`     | `{"slug":"breakout_brain_card"}` |
| `content_full_footer` | `newsletter_full`, `robots_full`              | `{"html":"<div class='promo-newsletter'>…</div>"}` |

> Deine vorhandenen Shortcode-Manager-Seeds (z. B. `roboforex_card`) kannst du exakt so nutzen – Creative `type=shortcode`, Payload `{"slug":"roboforex_card"}`. Attribute kannst du mit `"atts":{"flip1":"5000"}` überschreiben.

---

## 7. Beispiel-Workflow (Quick Start)

1. **Slots** anlegen:
   - `home_third_1`, `content_third_right`, `content_full_footer`
2. **Kampagnen** anlegen:
   - `roboforex_third`, `tegasfx_third`, `breakout_brain_third`
3. **Creatives** hinzufügen:
   - Kampagne `roboforex_third` → Creative `{ "type":"shortcode", "payload": {"slug":"roboforex_card"} }`
   - Kampagne `tegasfx_third` → Creative `{ "type":"shortcode", "payload": {"slug":"tgasfx_card"} }`
4. **Mapping:**
   - Slot `home_third_1` → `1:10,2:5` (Gewichte anpassen)
5. **Shortcode im Inhalt** einfügen:
   - Startseite: `[promo_slot slot="home_third_1" shuffle="day"]`
   - Breakout-Brain Artikel: `[promo_slot slot="content_third_right" brand_include="roboforex" brand_exclude="tegasfx" shuffle="hour"]`
6. **Test:** Seite im Frontend aufrufen, Browser-Cache leeren, Kontrolle ob Kartenelemente rotieren.

---

## 8. Troubleshooting

- **Keine Ausgabe:** Prüfe, ob Kampagne auf „aktiv“ steht, Format mit Slot übereinstimmt, Filter nicht zu restriktiv sind.
- **Creative JSON wird nicht gespeichert:** Syntax prüfen (korrekte Anführungszeichen, kein trailing comma). Verwende bei Unsicherheit `jsonlint.com`.
- **Shortcode `[roboforex_card]` nicht gefunden:** Im Shortcode-Manager sicherstellen, dass der Seed existiert und `exec_php/do_shortcode` korrekt gesetzt ist.
- **Shuffle wirkt nicht:** Wert `shuffle` prüfen; bei `none` wird bewusst kein Cache gesetzt (d.h. Random bei jedem Render). Bei `day`/`hour` kann WordPress-Cache aktiv sein – Hard Refresh oder Parameter `?nocache=1` testen.
- **Neue Tabellen nicht angelegt:** Plugin einmal deaktivieren/aktivieren (stellt sicher, dass `dbDelta` läuft). Alternativ `wp fxpromo migrate` (eigener CLI-Befehl – optional implementieren).

---

## 9. Weiterentwicklung

- UI-Verbesserungen (Select-Felder, Autocomplete, Datumspicker)
- Start-/Enddatum, Frequenzkappen im Admin pflegen
- Impression/Klicktracking (REST-Endpunkte ohne „ad“-Namen)
- Export/Import (JSON) für Slots/Kampagnen/Creatives
- Gutenberg-Block für `[promo_slot]`
- Mehrsprachigkeitsunterstützung (POT-Datei generieren)

---

## 10. Support

Bei Fragen oder Bugs: README/INSTALLATION aktualisieren und über das Haupt-Repo dokumentieren (`docs/promo-rotator.md` empfohlen). Für Schema-Änderungen `includes/db/migrations.php` anpassen.
