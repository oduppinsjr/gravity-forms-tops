<?php
/**
 * Plugin Name:       Gravity Forms TOPS / TowX Integration
 * Plugin URI:        https://github.com/oduppinsjr/gravity-forms-tops
 * Description:       Connect Gravity Forms to the TowX / TOPS (TOPSLink) API with configurable field mapping and dynamic vehicle options.
 * Version:           1.3.9
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Duppins Technology
 * Author URI:        https://github.com/duppins-technology
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gravity-forms-tops
 *
 * @package GF_Tops
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GF_TOPS_VERSION', '1.3.9' );
define( 'GF_TOPS_FILE', __FILE__ );
define( 'GF_TOPS_PATH', plugin_dir_path( __FILE__ ) );
define( 'GF_TOPS_URL', plugin_dir_url( __FILE__ ) );

require_once GF_TOPS_PATH . 'includes/class-gf-tops-constants.php';
require_once GF_TOPS_PATH . 'includes/class-gf-tops-github-updater.php';

add_action( 'admin_init', array( 'GF_Tops_GitHub_Updater', 'init' ), 5 );

/**
 * TLS/SNI workarounds for TowX (OpenSSL 3 + cURL). Runs before Gravity Forms loads.
 */
add_action(
	'plugins_loaded',
	static function () {
		require_once GF_TOPS_PATH . 'includes/class-gf-tops-http.php';
		GF_Tops_Http::register_hooks();
	},
	1
);

/**
 * Bootstrap after Gravity Forms loads.
 */
add_action(
	'gform_loaded',
	static function () {
		if ( ! class_exists( 'GFForms' ) ) {
			return;
		}

		if ( method_exists( 'GFForms', 'include_addon_framework' ) ) {
			GFForms::include_addon_framework();
		}

		if ( ! class_exists( 'GFAddOn' ) ) {
			return;
		}

		require_once GF_TOPS_PATH . 'includes/class-gf-tops-addon.php';

		GFAddOn::register( 'GF_Tops_Addon' );
	},
	5
);

add_action(
	'plugins_loaded',
	static function () {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Needed for GitHub/private installs; WP.org language packs are not used.
		load_plugin_textdomain( 'gravity-forms-tops', false, dirname( plugin_basename( GF_TOPS_FILE ) ) . '/languages' );
	}
);

/**
 * Admin notice when Gravity Forms is inactive.
 */
add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( 'activate_plugins' ) || class_exists( 'GFForms' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Gravity Forms TOPS requires Gravity Forms to be installed and active.', 'gravity-forms-tops' );
		echo '</p></div>';
	}
);
