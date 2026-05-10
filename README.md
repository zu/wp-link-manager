# Link Manager – WordPress Plugin

Ein WordPress-Plugin das einen Custom Post Type „Link" bereitstellt, inklusive:

- **Kategorisierung** via eigener Taxonomie `link_category`
- **Bewertungssystem** (👍 / 👎, einmal pro IP)
- **Kommentarfunktion** (WordPress-Core-Kommentare)
- **Link-Vorschläge** durch Besucher mit Redakteurs-Freigabe
- **Automatische Screenshots** via WordPress mShots-Service

---

## Dateistruktur

```
link-manager/
├── link-manager.php              # Plugin-Header + Bootstrap
├── includes/
│   ├── class-plugin.php          # Singleton / Bootstrapper
│   ├── class-installer.php       # Aktivierung / DB-Tabellen
│   ├── class-post-type.php       # CPT "link"
│   ├── class-taxonomy.php        # Taxonomie "link_category"
│   ├── class-ratings.php         # Bewertungssystem + AJAX
│   ├── class-submissions.php     # Link-Vorschläge + Freigabe-Logik
│   ├── class-screenshot.php      # Screenshot via mShots
│   ├── class-admin.php           # Admin-Moderationsseite
│   └── class-frontend.php        # Frontend-Hooks + Shortcodes
├── public/
│   ├── js/lm-public.js           # AJAX (Vote + Formular)
│   └── css/lm-public.css         # Frontend-Styles
└── languages/                    # (leer, bereit für .po/.mo Dateien)
```

---

## Installation

1. Ordner `wp-link-manager` nach `/wp-content/plugins/` hochladen
2. Plugin in WordPress aktivieren
3. Die DB-Tabellen werden automatisch erstellt

## Shortcodes
## als nächstes werde ich versuchen aus den Shortcodes Blocks machen zu lassen....

| Shortcode | Beschreibung |
|---|---|
| `[link_archive]` | Zeigt alle Links als Karten-Grid |
| `[link_archive per_page="6" category="tools"]` | Gefiltert nach Kategorie |
| `[link_submit_form]` | Formular für Link-Vorschläge |

## Deployment via Git (empfohlen)

```bash
# Einmalig: Repository klonen auf dem Server
cd /var/www/html/wp-content/plugins
git clone https://github.com/USER/REPO.git link-manager

# Update einspielen
cd link-manager && git pull
```

## Screenshot-Service

Das Plugin verwendet standardmässig **WordPress mShots** (kostenlos, kein API-Key):
```
https://s.wordpress.com/mshots/v1/{url}?w=1200&h=675
```

Beim ersten Aufruf einer URL rendert mShots ~30 Sekunden im Hintergrund.
Das Plugin wartet via WP-Cron und importiert das Bild dann in die Mediathek.

Für einen schnelleren / zuverlässigeren Service kann `class-screenshot.php`
auf [Screenshot.one](https://screenshot.one) oder [ScreenshotAPI](https://screenshotapi.net) umgestellt werden.

---

## Entwicklungs-Workflow

### Nur iPad + Claude Web
→ Code über Claude generieren, per SFTP-App (z.B. Secure ShellFish) auf den Server übertragen.

### Mit Claude Desktop / Claude Code
→ Direkt im Filesystem arbeiten, Git für Versionierung nutzen.

### Git als Brücke
1. Änderungen in dieses Repo pushen
2. Server pullt automatisch (Webhook oder Cron)
