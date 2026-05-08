<?php
/**
 * Persist TowX Create Call attempts for auditing and resend.
 *
 * @package GF_Tops
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GF_Tops_Request_Log
 */
class GF_Tops_Request_Log {

	const DB_VERSION = '1.0';

	const MAX_RESPONSE_BYTES = 262144;

	/**
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'gf_tops_request_log';
	}

	/**
	 * Create or upgrade table.
	 */
	public static function install() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table         = self::table_name();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL,
			entry_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			endpoint_url varchar(512) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT '',
			http_code smallint unsigned NOT NULL DEFAULT 0,
			call_key varchar(255) NULL,
			error_message text NULL,
			request_xml_redacted longtext NOT NULL,
			response_raw longtext NOT NULL,
			source varchar(32) NOT NULL DEFAULT 'submission',
			resend_of_id bigint(20) unsigned NOT NULL DEFAULT 0,
			resend_by_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY form_created (form_id, created_at),
			KEY entry_id (entry_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'gf_tops_request_log_db_version', self::DB_VERSION );
	}

	/**
	 * Install when version option is missing or stale.
	 */
	public static function maybe_install() {
		$v = get_option( 'gf_tops_request_log_db_version' );
		if ( $v !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * @param array $row Row data.
	 * @return int|false Insert ID.
	 */
	public static function insert( array $row ) {
		global $wpdb;

		$defaults = array(
			'form_id'               => 0,
			'entry_id'              => 0,
			'created_at'            => current_time( 'mysql', true ),
			'endpoint_url'          => '',
			'status'                => '',
			'http_code'             => 0,
			'call_key'              => null,
			'error_message'         => null,
			'request_xml_redacted'  => '',
			'response_raw'          => '',
			'source'                => 'submission',
			'resend_of_id'          => 0,
			'resend_by_user_id'     => 0,
		);

		$row = array_merge( $defaults, $row );

		if ( strlen( $row['response_raw'] ) > self::MAX_RESPONSE_BYTES ) {
			$row['response_raw'] = substr( $row['response_raw'], 0, self::MAX_RESPONSE_BYTES ) . "\n…";
		}

		$insert = array(
			'form_id'              => (int) $row['form_id'],
			'entry_id'             => (int) $row['entry_id'],
			'created_at'           => $row['created_at'],
			'endpoint_url'         => substr( (string) $row['endpoint_url'], 0, 512 ),
			'status'               => substr( (string) $row['status'], 0, 32 ),
			'http_code'            => $row['http_code'] !== null && $row['http_code'] !== '' ? (int) $row['http_code'] : 0,
			'call_key'             => $row['call_key'] !== null ? substr( (string) $row['call_key'], 0, 255 ) : null,
			'error_message'        => $row['error_message'],
			'request_xml_redacted' => (string) $row['request_xml_redacted'],
			'response_raw'         => (string) $row['response_raw'],
			'source'               => substr( (string) $row['source'], 0, 32 ),
			'resend_of_id'         => ! empty( $row['resend_of_id'] ) ? (int) $row['resend_of_id'] : 0,
			'resend_by_user_id'    => ! empty( $row['resend_by_user_id'] ) ? (int) $row['resend_by_user_id'] : 0,
		);

		$formats = array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom log table; wpdb->insert uses placeholders via $formats.
		$ok = $wpdb->insert( self::table_name(), $insert, $formats );
		if ( ! $ok ) {
			return false;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $form_id Form ID.
	 * @param int $page    1-based page.
	 * @param int $per_page Rows per page.
	 * @return array{ rows: array<int, object>, total: int }
	 */
	public static function query_for_form( $form_id, $page = 1, $per_page = 20 ) {
		global $wpdb;

		$form_id  = (int) $form_id;
		$page     = max( 1, (int) $page );
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$lim = (int) $per_page;
		$off = (int) $offset;

		// Identifier placeholder %i requires WordPress 6.2+ (see plugin header).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom log table; no object cache for admin log UI.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE form_id = %d',
				self::table_name(),
				$form_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom log table; no object cache for admin log UI.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE form_id = %d ORDER BY id DESC LIMIT %d OFFSET %d',
				self::table_name(),
				$form_id,
				$lim,
				$off
			)
		);

		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * @param int $id      Log row ID.
	 * @param int $form_id Expected form ID (authorization).
	 * @return object|null
	 */
	public static function get_row( $id, $form_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom log table; single row for resend/preview.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d AND form_id = %d LIMIT 1',
				self::table_name(),
				(int) $id,
				(int) $form_id
			)
		);
		return $row ? $row : null;
	}

	/**
	 * @param int $id Log ID.
	 */
	public static function uninstall_drop() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin uninstall only.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', self::table_name() ) );
		delete_option( 'gf_tops_request_log_db_version' );
	}
}
