=== Gravity Forms TOPS / TowX Integration ===
Contributors: duppinstechnology
Tags: gravity forms, tops, towx, towing, api
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.3.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect Gravity Forms to the TowX / TOPS (TOPSLink) API with per-form field mapping, dynamic vehicle lists, and optional connection testing.

== Description ==

This plugin registers a Gravity Forms add-on that:

* Submits tow requests using TowX **Create Call** XML.
* Maps form fields to TowX data via the Gravity Forms **field map** UI (no hard-coded field IDs).
* Optionally loads **makes**, **colors**, and **models** from TowX JSON endpoints.
* Masks **password** and **authentication key** in the form settings screen; saved values are preserved when those fields are left blank.
* Provides a **Test authentication** action (GetMakes) with expanded failure details (HTTP status, response body excerpt, TowX error JSON).
* **Request log** per form (Forms → TOPS log) with redacted XML, responses, and **Resend Create Call** after fixing issues.
* Sends **Create Call** XML to the same TowX host as JSON (Production vs QA) from **Forms → Settings → TOPS**—no per-form URL override.

**Requirements:** Gravity Forms (licensed copy per your site). TowX / TOPS credentials from your provider.

== Installation ==

1. Upload the `gravity-forms-tops` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen.
3. Open **Forms → Settings → TOPS** for global options (API environment, notifications).
4. Edit each tow form → **Settings → TOPS** to enable the integration, enter credentials, map fields, and save.

== Frequently Asked Questions ==

= WordPress.org rejected my plugin name or slug — why? =

The Plugin Directory restricts starting certain trademark-related terms (including variations of “Gravity Forms”). This repo name is fine for **GitHub**; if you submit to WordPress.org later, rename the display name / slug per their guidelines (for example a neutral prefix plus “for Gravity Forms”) while keeping this codebase folder name if you prefer.

= Where are my TowX password and key stored? =

In Gravity Forms form meta for that form. Restrict who can edit forms and use HTTPS for wp-admin.

= Can I use different field IDs on different sites? =

Yes. Configure the field map separately on each site.

== Changelog ==

= 1.3.4 =
* Production readiness: Plugin Check fixes — translators comments for JS i18n strings with placeholders; escaped admin request-log output; documented nonce waiver for read-only GET navigation; `phpcs:ignore` on required `http_api_curl` options (no `wp_remote_*` equivalent for these TLS/SNI flags).
* Database: use `$wpdb->prepare()` identifier placeholder `%i` for the custom log table (requires **WordPress 6.2+**); `DROP TABLE` on uninstall uses the same pattern.
* `load_plugin_textdomain` PHPCS note for non–WordPress.org distribution.
* `languages/index.php` instead of a hidden placeholder file; readme `Tested up to` / tag count for WordPress.org rules.
* **Minimum WordPress** version raised to **6.2** (identifier placeholders).

= 1.3.3 =
* Register Elementor HTML capture on `elementor/loaded` (not only `wp`) so `gform_wrapper_*` IDs are recorded before `wp_footer` enqueue.
* Footer HTML comment `<!-- GF TOPS … -->` lists discovered form IDs, integration on/off, and whether `gf-tops-frontend` enqueued (View Source → search “GF TOPS”).

= 1.3.2 =
* Discover Gravity Forms IDs from rendered HTML (`gform_wrapper_{id}`) via late `the_content`, widgets, `render_block`, and Elementor `elementor/frontend/the_content` — fixes missing scripts when page builders never expose shortcodes in raw post_content.

= 1.3.1 =
* Discover Gravity Forms on singular pages using `get_queried_object_id()` (fixes empty discovery when `global $post` is unset during `wp_enqueue_scripts`).
* Scan Elementor `_elementor_data` JSON for form IDs / embedded shortcodes.
* `wp_footer` fallback enqueue if TOPS assets were not queued earlier.

= 1.3.0 =
* Front-end status callout when TOPS is enabled (API environment, makes/colors counts, model cascade status); DOM recount after Gravity Forms renders.
* Enqueue TOPS scripts via `wp_enqueue_scripts` when form IDs are found in post shortcodes/blocks (fallback when `gform_enqueue_scripts` does not run).
* Filter `gf_tops_discover_form_ids` for page builders that embed forms outside post_content.
* Cascade diagnostics optional UI; hidden-field sync options; improved GetModelsForMake error handling.

= 1.2.0 =
* Request log: each Create Call attempt is stored (redacted request XML, raw response, HTTP code, Call ID when TowX returns CallKey).
* Forms → TOPS settings: link to the log; Forms → TOPS log submenu lists attempts per form with pagination.
* Resend Create Call from the log (rebuilds XML from the current entry and saved credentials; does not re-trigger confirmation email notifications).

= 1.1.0 =
* Mask password and authentication key in UI; preserve on save when left blank.
* Create Call XML uses the same API environment as JSON (production / QA / custom); removed per-form Create Call URL.
* VehicleInfo XML value built from Year, Make, Model, and Color mappings (labels when available).
* Admin authentication test (GetMakes) with detailed diagnostics on failure or unexpected responses.
* Repository layout: `includes/`, `assets/`, `languages/`, legal and security docs.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.3.4 =
Requires **WordPress 6.2 or newer** (database identifier placeholders). Stay on 1.3.3 if you must run WordPress 6.0–6.1.

= 1.1.0 =
Secrets UI, environment-based TowX URLs, VehicleInfo from year/make/model/color, and richer connection-test diagnostics.
