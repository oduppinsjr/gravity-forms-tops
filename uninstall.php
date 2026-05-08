<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package GF_Tops
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove plugin-level options only. Per-form TowX settings live in Gravity Forms
 * form meta; removing them here could destroy production configuration if the
 * plugin is reinstalled later.
 */
delete_option( 'gravityformsaddon_gravityformstops_settings' );
delete_option( 'gravityformsaddon_gravityformstops_version' );
delete_option( 'gf_tops_request_log_db_version' );

if ( ! function_exists( 'gf_tops_uninstall_drop_log_table' ) ) {
	/**
	 * Drop request log table (requires wpdb).
	 */
	function gf_tops_uninstall_drop_log_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'gf_tops_request_log';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall only; identifier via %i (WP 6.2+).
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}
}
gf_tops_uninstall_drop_log_table();
