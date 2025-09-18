# Werbe Manager

WordPress-Plugin zur Verwaltung dynamisch rotierender Werbe-Slots und Kampagnen.

## Features

- Eigenes Slot- und Kampagnenmodell (Custom-Tabellen)
- Weighted-Random-Ausspielung mit stabilen Shuffle-Zeitfenstern (hour/day/session)
- Shortcode `[promo_slot]` zur Einbindung im Inhalt
- Unterstützung für Shortcode-, HTML- und Bild-Creatives
- Einfache Admin-Formulare für Slots, Kampagnen, Creatives sowie Slot-Zuordnung
- Adblock-resistente Klassen-/Dateinamen (`promo-slot`, `promo-unit`)

## Shortcode

```
[promo_slot slot="home_third_1" shuffle="day" brand_include="roboforex" brand_exclude="tegasfx"]
```

**Attribute**

- `slot` (erforderlich): Slug des Slots
- `shuffle`: `none|hour|day|session` (Standard `day`)
- `brand_include`, `brand_exclude`: CSV mit Marken-Slugs
- `category_include`, `category_exclude`, `tag_include`, `tag_exclude`: CSV mit Slugs
- `include_ids`, `exclude_ids`: CSV mit Post-IDs
- `fallback`: Optionaler Shortcode/HTML, falls keine Kampagne eligible ist

## Creative-Payloads

- `type=shortcode`: `{"slug":"roboforex_card","atts":{"flip1":"5000"}}`
- `type=html`: `{"html":"<div class=\"promo-unit-custom\">…</div>"}`
- `type=image`: `{"src":"https://…/banner.png","href":"https://…","alt":"Promo"}`

## Tabellen

- `wp_wm_slots`
- `wp_wm_campaigns`
- `wp_wm_campaign_slot`
- `wp_wm_creatives`

Die Tabellen werden bei Aktivierung erzeugt (per `dbDelta`).

## Lizenz

Released unter GPL-2.0-or-later.
