# Gravity Forms TOPS / TowX Integration

WordPress plugin by **Duppins Technology** that connects [Gravity Forms](https://www.gravityforms.com/) to the **TowX / TOPS (TOPSLink)** API for tow dispatch (Create Call XML), with per-form field mapping and optional dynamic vehicle dropdowns.

## Repository layout

This follows common WordPress plugin conventions:

| Path | Purpose |
|------|---------|
| `gravity-forms-tops.php` | Bootstrap, constants, TLS helper registration, add-on registration |
| `uninstall.php` | Removes plugin-level options when the plugin is deleted (not form meta) |
| `includes/` | PHP classes (add-on, HTTP, XML, entry helpers) |
| `assets/js/` | Front-end (model cascade) and admin (form settings) scripts |
| `assets/css/` | Admin styles for connection test UI |
| `languages/` | Translation files (`.pot` / `.po` / `.mo`) |
| `readme.txt` | WordPress.org–style readme (for directory packaging, if applicable) |
| `LICENSE` | GPL-2.0-or-later copyright notice |
| `SECURITY.md` | Vulnerability reporting and data-handling notes |
| `CONTRIBUTING.md` | Contribution guidelines |

You can add a future `tests/` or `bin/` directory without changing the bootstrap.

## Requirements

- WordPress **6.0+**
- PHP **7.4+**
- **Gravity Forms** (active)

## Install

1. Clone or copy this repository into `wp-content/plugins/gravity-forms-tops/`.
2. Activate **Gravity Forms TOPS / TowX Integration** in **Plugins**.
3. Configure **Forms → Settings → TOPS** (global: **API environment** for both JSON and Create Call XML, optional error email, logging).
4. For each form: **Form → Settings → TOPS** — enable integration, set credentials, map fields, optionally enable dropdown population and model cascade.

## Features

- **Field map** for Create Call data (plus optional lines prepended to dispatch notes). **VehicleInfo** in the XML is built from **Year, Make, Model, and Color** (choice labels when available).
- **Password fields** for TowX password and authentication key: values are not re-displayed after save; leave blank to keep the stored secret.
- **Create Call** XML and **JSON** (GetMakes, etc.) share the same base URL, controlled only under **Forms → Settings → TOPS → API environment** (Production `api.towxchange.net`, QA `apiqa.towxchange.net`, or Custom).
- **Test authentication** runs **GetMakes** with the Session ID and key from the form (including unsaved input) and shows **summary + detailed response** (HTTP status, body excerpt, or pretty-printed TowX errors) on failure.
- **TLS helpers** for `towxchange.net` hosts (see `includes/class-gf-tops-http.php` and filters `gf_tops_http_*`).

## Security

See [SECURITY.md](SECURITY.md). This plugin stores TowX credentials in **form meta**; it does not encrypt them at rest. Limit form admin access and use HTTPS.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) and [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html).

## Legal

Gravity Forms is a trademark of Rocketgenius, Inc. This project is not affiliated with or endorsed by Rocketgenius or TowXchange. TowX / TOPS are used here to describe API compatibility only.
