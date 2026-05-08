<?php
/**
 * WordPress HTTP / cURL tweaks for TowX hosts (TLS/SNI compatibility).
 *
 * @package GF_Tops
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GF_Tops_Http
 */
class GF_Tops_Http {

	/**
	 * Register cURL adjustments for outbound TowX requests.
	 */
	public static function register_hooks() {
		add_action( 'http_api_curl', array( __CLASS__, 'configure_curl_for_towx' ), 10, 3 );
	}

	/**
	 * Whether the URL is a TowX / TowXchange API host.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_towx_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || $host === '' ) {
			return false;
		}
		$host = strtolower( $host );
		return (bool) preg_match( '/(^|\.)towxchange\.net$/', $host );
	}

	/**
	 * Apply cURL options that commonly fix OpenSSL "unrecognized name" / SNI issues.
	 *
	 * @param resource|\CurlHandle $handle cURL handle.
	 * @param array                $r      Request args.
	 * @param string               $url    URL.
	 */
	public static function configure_curl_for_towx( $handle, $r, $url ) {
		if ( ! self::is_towx_url( $url ) ) {
			return;
		}

		// wp_remote_* does not expose CURLOPT_IPRESOLVE / TLS version; hook http_api_curl is the supported extension point (WP docs).

		/**
		 * Force IPv4 when TowX IPv6 or SNI on the v6 path misbehaves (common with OpenSSL 3 + bad AAAA).
		 *
		 * @param bool $force Whether to set CURLOPT_IPRESOLVE to V4.
		 */
		$force_ipv4 = apply_filters( 'gf_tops_http_force_ipv4', true );
		if ( $force_ipv4 && defined( 'CURL_IPRESOLVE_V4' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- TowX TLS/SNI workaround via http_api_curl only.
			curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
		}

		/**
		 * Prefer HTTP/1.1; some API gateways mishandle HTTP/2 + SNI.
		 *
		 * @param bool $use_11 Whether to force HTTP/1.1.
		 */
		$use_http_11 = apply_filters( 'gf_tops_http_force_http11', true );
		if ( $use_http_11 && defined( 'CURL_HTTP_VERSION_1_1' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- TowX TLS/SNI workaround via http_api_curl only.
			curl_setopt( $handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
		}

		/**
		 * Minimum TLS 1.2 for predictable handshake with OpenSSL 3.
		 *
		 * @param bool $set_tls12 Whether to set CURLOPT_SSLVERSION to TLSv1.2.
		 */
		$set_tls12 = apply_filters( 'gf_tops_http_force_tls12', true );
		if ( $set_tls12 && defined( 'CURL_SSLVERSION_TLSv1_2' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt -- TowX TLS/SNI workaround via http_api_curl only.
			curl_setopt( $handle, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2 );
		}
	}

	/**
	 * Default args merged into wp_remote_get / wp_remote_post for TowX.
	 *
	 * @return array
	 */
	public static function default_remote_args() {
		return array(
			'timeout'     => 60,
			'sslverify'   => true,
			'httpversion' => '1.1',
			'headers'     => array(),
		);
	}
}
