<?php
/**
 * Check GitHub Releases for newer plugin versions (public API).
 *
 * @package GF_Tops
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GF_Tops_GitHub_Updater
 */
class GF_Tops_GitHub_Updater {

	const TRANSIENT_PREFIX = 'gf_tops_gh_rel_';

	/**
	 * @return array{ owner: string, repo: string, token: string }
	 */
	public static function config() {
		$defaults = array(
			'owner' => 'oduppinsjr',
			'repo'  => 'gravity-forms-tops',
			'token' => '',
		);

		/**
		 * GitHub updater settings.
		 *
		 * @param array $defaults owner, repo (repository name), optional token for private repos / higher API limits.
		 */
		return apply_filters( 'gf_tops_github_updater_config', $defaults );
	}

	/**
	 * Boot hooks (admin only).
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		if ( defined( 'GF_TOPS_DISABLE_GITHUB_UPDATER' ) && GF_TOPS_DISABLE_GITHUB_UPDATER ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ), 10, 2 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 10, 3 );
		add_filter( 'http_request_args', array( __CLASS__, 'github_release_zip_http_args' ), 10, 2 );
	}

	/**
	 * Cached GET https://api.github.com/repos/{owner}/{repo}/releases/latest
	 *
	 * @return array|null Release JSON or null on failure / no release.
	 */
	protected static function fetch_latest_release() {
		$config = self::config();
		$owner  = isset( $config['owner'] ) ? sanitize_key( $config['owner'] ) : '';
		$repo   = isset( $config['repo'] ) ? preg_replace( '/[^a-zA-Z0-9._-]/', '', $config['repo'] ) : '';
		if ( $owner === '' || $repo === '' ) {
			return null;
		}

		$key    = self::TRANSIENT_PREFIX . md5( $owner . '/' . $repo );
		$cached = get_site_transient( $key );
		if ( false !== $cached ) {
			if ( is_array( $cached ) && ! empty( $cached['error'] ) ) {
				return null;
			}
			if ( is_array( $cached ) && ! empty( $cached['tag_name'] ) ) {
				return $cached;
			}
			return null;
		}

		$url  = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', rawurlencode( $owner ), rawurlencode( $repo ) );
		$args = array(
			'timeout' => 12,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . esc_url( home_url( '/' ) ),
			),
		);
		if ( ! empty( $config['token'] ) && is_string( $config['token'] ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $config['token'];
		}

		/**
		 * Args for wp_remote_get to GitHub releases API.
		 *
		 * @param array  $args   Remote args.
		 * @param string $url    Request URL.
		 * @param array  $config Owner/repo/token.
		 */
		$args = apply_filters( 'gf_tops_github_release_http_args', $args, $url, $config );

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			set_site_transient( $key, array( 'error' => true ), 15 * MINUTE_IN_SECONDS );
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			set_site_transient( $key, array( 'error' => true ), 15 * MINUTE_IN_SECONDS );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
			set_site_transient( $key, array( 'error' => true ), HOUR_IN_SECONDS );
			return null;
		}

		set_site_transient( $key, $data, 12 * HOUR_IN_SECONDS );
		return $data;
	}

	/**
	 * Normalize v1.2.3 -> 1.2.3
	 *
	 * @param string $tag Tag name.
	 * @return string
	 */
	protected static function normalize_version( $tag ) {
		$tag = ltrim( (string) $tag, 'vV' );
		return $tag;
	}

	/**
	 * Pick install ZIP: release asset, else source archive for tag.
	 *
	 * @param array $release Decoded release JSON.
	 * @return string URL or empty.
	 */
	protected static function release_zip_url( array $release ) {
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( empty( $asset['browser_download_url'] ) || empty( $asset['name'] ) ) {
					continue;
				}
				$name = strtolower( (string) $asset['name'] );
				if ( substr( $name, -4 ) !== '.zip' ) {
					continue;
				}
				if ( false !== strpos( $name, 'gravity-forms-tops' ) || count( $release['assets'] ) === 1 ) {
					return (string) $asset['browser_download_url'];
				}
			}
			foreach ( $release['assets'] as $asset ) {
				if ( ! empty( $asset['browser_download_url'] ) && isset( $asset['name'] ) && substr( strtolower( (string) $asset['name'] ), -4 ) === '.zip' ) {
					return (string) $asset['browser_download_url'];
				}
			}
		}

		$config = self::config();
		$owner  = isset( $config['owner'] ) ? $config['owner'] : '';
		$repo   = isset( $config['repo'] ) ? $config['repo'] : '';
		$tag    = isset( $release['tag_name'] ) ? $release['tag_name'] : '';
		if ( $owner && $repo && $tag ) {
			return sprintf(
				'https://github.com/%s/%s/archive/refs/tags/%s.zip',
				rawurlencode( $owner ),
				rawurlencode( $repo ),
				rawurlencode( $tag )
			);
		}

		return '';
	}

	/**
	 * @param object $transient update_plugins transient value.
	 * @return object
	 */
	public static function inject_update( $transient ) {
		if ( empty( $transient->checked ) || ! is_object( $transient ) ) {
			return $transient;
		}

		$plugin_file = plugin_basename( GF_TOPS_FILE );
		if ( empty( $transient->checked[ $plugin_file ] ) ) {
			return $transient;
		}

		$release = self::fetch_latest_release();
		if ( ! is_array( $release ) ) {
			return $transient;
		}

		$remote_version = self::normalize_version( $release['tag_name'] );
		if ( $remote_version === '' || ! version_compare( $remote_version, GF_TOPS_VERSION, '>' ) ) {
			return $transient;
		}

		$package = self::release_zip_url( $release );
		if ( $package === '' ) {
			return $transient;
		}

		$config = self::config();

		$item = array(
			'id'          => $plugin_file,
			'slug'        => dirname( $plugin_file ),
			'plugin'      => $plugin_file,
			'new_version' => $remote_version,
			'url'         => sprintf(
				'https://github.com/%s/%s',
				rawurlencode( $config['owner'] ),
				rawurlencode( $config['repo'] )
			),
			'package'     => $package,
			'icons'       => array(),
			'banners'     => array(),
		);

		$transient->response[ $plugin_file ] = (object) $item;

		return $transient;
	}

	/**
	 * View details modal content.
	 *
	 * @param mixed|false $result Result.
	 * @param string      $action Action name.
	 * @param object      $args   Arguments.
	 * @return mixed
	 */
	public static function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}

		if ( $args->slug !== dirname( plugin_basename( GF_TOPS_FILE ) ) ) {
			return $result;
		}

		$release = self::fetch_latest_release();
		if ( ! is_array( $release ) ) {
			return $result;
		}

		$version = self::normalize_version( $release['tag_name'] );
		$body    = isset( $release['body'] ) ? (string) $release['body'] : '';
		$name    = isset( $release['name'] ) ? (string) $release['name'] : 'Gravity Forms TOPS';
		$config  = self::config();
		$gh_url  = sprintf(
			'https://github.com/%s/%s',
			rawurlencode( $config['owner'] ),
			rawurlencode( $config['repo'] )
		);

		return (object) array(
			'name'           => $name,
			'slug'           => dirname( plugin_basename( GF_TOPS_FILE ) ),
			'version'        => $version,
			'author'         => '<a href="' . esc_url( sprintf( 'https://github.com/%s', rawurlencode( $config['owner'] ) ) ) . '">GitHub</a>',
			'homepage'       => esc_url( $gh_url ),
			'download_link'  => self::release_zip_url( $release ),
			'sections'       => array(
				'description' => wp_kses_post( wpautop( make_clickable( $body ) ) ),
				'changelog'   => wp_kses_post( wpautop( make_clickable( $body ) ) ),
			),
			'banners'        => array(),
			'icons'          => array(),
		);
	}

	/**
	 * Improve reliability when WordPress downloads GitHub release ZIPs (redirects to objects.githubusercontent.com).
	 *
	 * @param array  $args Request arguments.
	 * @param string $url  URL.
	 * @return array
	 */
	public static function github_release_zip_http_args( $args, $url ) {
		if ( ! is_string( $url ) || substr( strtolower( $url ), -4 ) !== '.zip' ) {
			return $args;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) ) {
			return $args;
		}

		$host_lower = strtolower( $host );
		if ( $host_lower !== 'github.com' && $host_lower !== 'objects.githubusercontent.com' ) {
			return $args;
		}

		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}

		$args['headers']['Accept']     = 'application/octet-stream';
		$args['headers']['User-Agent'] = 'WordPress/' . get_bloginfo( 'version' ) . '; ' . esc_url( home_url( '/' ) );

		// Large redirects / slow disks on local envs.
		if ( empty( $args['timeout'] ) || (int) $args['timeout'] < 60 ) {
			$args['timeout'] = 60;
		}

		return $args;
	}
}
