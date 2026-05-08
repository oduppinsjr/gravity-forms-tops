<?php
/**
 * Build and POST TowX XML requests.
 *
 * @package GF_Tops
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GF_Tops_Xml
 */
class GF_Tops_Xml {

	const ENCODING = 'WINDOWS-1252';

	/**
	 * Escape a string for TowX XML payloads.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function escape( $value ) {
		return htmlspecialchars( (string) $value, ENT_XML1 | ENT_QUOTES, self::ENCODING );
	}

	/**
	 * Normalize dispatch notes: single line, trimmed.
	 *
	 * @param string $notes Notes.
	 * @return string
	 */
	public static function normalize_notes( $notes ) {
		if ( $notes === '' || $notes === null ) {
			return '';
		}
		$notes = preg_replace( '/\r\n|\r|\n/', ' ', $notes );
		$notes = preg_replace( '/\s+/', ' ', $notes );
		return trim( $notes );
	}

	/**
	 * Build the Create Call XML body.
	 *
	 * @param array $auth Keys: user_id, password, session_id, authentication_key.
	 * @param array $data Keys matching TowX Data element names.
	 * @return string
	 */
	public static function build_create_call_xml( array $auth, array $data ) {
		$uid     = self::escape( $auth['user_id'] );
		$pass    = self::escape( $auth['password'] );
		$sess    = self::escape( $auth['session_id'] );
		$authkey = self::escape( $auth['authentication_key'] );

		$location    = self::escape( $data['location'] );
		$caller      = self::escape( $data['caller_name'] );
		$phone       = self::escape( $data['caller_phone'] );
		$destination = self::escape( $data['destination'] );
		$vehicle     = self::escape( $data['vehicle_info'] );
		$tag         = self::escape( $data['tag_number'] );
		$state       = self::escape( $data['tag_state'] );
		$notes       = self::escape( $data['dispatch_notes'] );
		$year        = self::escape( $data['year'] );
		$make        = self::escape( $data['make_key'] );
		$model       = self::escape( $data['model_key'] );
		$color       = self::escape( $data['color_key'] );

		return '<?xml version="1.0"?>
<!DOCTYPE towXRequest>
<towXRequest>
  <Operation>
    <Product>TOPSLink</Product>
    <Noun>Call</Noun>
    <Verb>Create</Verb>
    <Mode>[Blank]</Mode>
    <callback>[Blank]</callback>
    <Format>
      <EnumerateFields>[Blank]</EnumerateFields>
      <ResponseType>[Blank]</ResponseType>
      <ResponseData>[Blank]</ResponseData>
    </Format>
  </Operation>
  <Authentication>
    <UserID>' . $uid . '</UserID>
    <Password>' . $pass . '</Password>
    <SessionID>' . $sess . '</SessionID>
    <AuthenticationKey>' . $authkey . '</AuthenticationKey>
  </Authentication>
  <Data>
    <UserID>' . $uid . '</UserID>
    <Password>' . $pass . '</Password>
    <Location>' . $location . '</Location>
    <CallerName>' . $caller . '</CallerName>
    <CallerPhone>' . $phone . '</CallerPhone>
    <Destination>' . $destination . '</Destination>
    <VehicleInfo>' . $vehicle . '</VehicleInfo>
    <TagNumber>' . $tag . '</TagNumber>
    <TagState>' . $state . '</TagState>
    <DispatchNotes>' . $notes . '</DispatchNotes>
    <Year>' . $year . '</Year>
    <MakeKey>' . $make . '</MakeKey>
    <ModelKey>' . $model . '</ModelKey>
    <ColorKey>' . $color . '</ColorKey>
  </Data>
</towXRequest>';
	}

	/**
	 * Redact secrets from Create Call XML for storage / display.
	 *
	 * @param string $xml Full XML.
	 * @return string
	 */
	public static function redact_for_log( $xml ) {
		$xml = (string) $xml;
		$patterns = array(
			'/<Password>.*?<\/Password>/is',
			'/<AuthenticationKey>.*?<\/AuthenticationKey>/is',
			'/<SessionID>.*?<\/SessionID>/is',
		);
		$repl     = array(
			'<Password>***</Password>',
			'<AuthenticationKey>***</AuthenticationKey>',
			'<SessionID>***</SessionID>',
		);
		return (string) preg_replace( $patterns, $repl, $xml );
	}

	/**
	 * POST XML to TowX. On success returns array with body and HTTP code; on failure WP_Error.
	 *
	 * @param string $url Full endpoint URL.
	 * @param string $xml XML body.
	 * @return array{ code: int, body: string }|\WP_Error
	 */
	public static function post( $url, $xml ) {
		$args = array(
			'headers' => array(
				'Content-Type' => 'text/xml; charset=' . self::ENCODING,
			),
			'body' => $xml,
		);

		if ( class_exists( 'GF_Tops_Http' ) ) {
			$args = array_merge( GF_Tops_Http::default_remote_args(), $args );
		} else {
			$args = array_merge(
				array(
					'timeout'     => 60,
					'sslverify'   => true,
					'httpversion' => '1.1',
				),
				$args
			);
		}

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'gf_tops_http_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'TowX HTTP error (%d).', 'gravity-forms-tops' ),
					(int) $code
				),
				array(
					'body' => $body,
					'code' => (int) $code,
				)
			);
		}

		return array(
			'code' => (int) $code,
			'body' => $body,
		);
	}

	/**
	 * Parse TowX response for CallKey or errors.
	 *
	 * @param string $output Raw XML.
	 * @return array{ call_key: ?string, error_message: ?string, error_context: ?string, raw: string }
	 */
	public static function parse_response( $output ) {
		$result = array(
			'call_key'       => null,
			'error_message'  => null,
			'error_context'  => null,
			'raw'            => $output,
		);

		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $output );
		if ( false === $xml ) {
			$result['error_message'] = __( 'Invalid XML response from TowX.', 'gravity-forms-tops' );
			return $result;
		}

		if ( isset( $xml->Errors->Error->Message ) ) {
			$result['error_message'] = (string) $xml->Errors->Error->Message;
			$result['error_context'] = isset( $xml->Errors->Error->Context ) ? (string) $xml->Errors->Error->Context : '';
			return $result;
		}

		if ( isset( $xml->Data->CallKey ) ) {
			$result['call_key'] = (string) $xml->Data->CallKey;
		}

		return $result;
	}
}
