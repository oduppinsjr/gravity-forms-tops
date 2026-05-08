<?php
/**
 * Resolve Gravity Forms entry values for TOPS mapping.
 *
 * @package GF_Tops
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GF_Tops_Entry
 */
class GF_Tops_Entry {

	/**
	 * Get value for a mapped field ID (handles address, name compound fields).
	 *
	 * @param array  $form      Form array.
	 * @param array  $entry     Entry array.
	 * @param string $field_id  Field or input ID (e.g. "16", "16.1").
	 * @return string
	 */
	public static function get_value( $form, $entry, $field_id ) {
		if ( $field_id === '' || $field_id === null ) {
			return '';
		}

		$field_id = (string) $field_id;
		$field      = self::get_field_by_id( $form, $field_id );

		if ( $field && 'address' === $field->type && strpos( $field_id, '.' ) === false ) {
			return self::format_address_field( $entry, $field_id );
		}

		if ( $field && 'name' === $field->type && strpos( $field_id, '.' ) === false ) {
			return self::format_name_field( $entry, $field_id );
		}

		return (string) rgar( $entry, $field_id );
	}

	/**
	 * @param array  $form Form.
	 * @param string $id   Field id string.
	 * @return \GF_Field|null
	 */
	protected static function get_field_by_id( $form, $id ) {
		$base = $id;
		if ( strpos( $id, '.' ) !== false ) {
			$parts = explode( '.', $id, 2 );
			$base  = $parts[0];
		}
		return GFFormsModel::get_field( $form, absint( $base ) );
	}

	/**
	 * Concatenate address inputs like the legacy snippet.
	 *
	 * @param array  $entry   Entry.
	 * @param string $field_id Parent field id.
	 * @return string
	 */
	protected static function format_address_field( $entry, $field_id ) {
		$line1   = (string) rgar( $entry, $field_id . '.1' );
		$line2   = (string) rgar( $entry, $field_id . '.2' );
		$city    = (string) rgar( $entry, $field_id . '.3' );
		$state   = (string) rgar( $entry, $field_id . '.4' );
		$zip     = (string) rgar( $entry, $field_id . '.5' );
		$country = (string) rgar( $entry, $field_id . '.6' );

		$location = $line1;
		if ( $line2 !== '' ) {
			$location .= ', ' . $line2;
		}
		$location .= ', ' . $city . ', ' . $state . ' ' . $zip;
		if ( $country !== '' ) {
			$location .= ', ' . $country;
		}

		return $location;
	}

	/**
	 * Full name from compound name field.
	 *
	 * @param array  $entry    Entry.
	 * @param string $field_id Field id.
	 * @return string
	 */
	protected static function format_name_field( $entry, $field_id ) {
		$prefix = (string) rgar( $entry, $field_id . '.2' );
		$first  = (string) rgar( $entry, $field_id . '.3' );
		$last   = (string) rgar( $entry, $field_id . '.6' );
		$suffix = (string) rgar( $entry, $field_id . '.8' );

		$name = trim( implode( ' ', array_filter( array( $prefix, $first, $last, $suffix ), 'strlen' ) ) );
		return $name;
	}

	/**
	 * Build optional prefix block for dispatch notes from optional mapped fields.
	 *
	 * @param array  $form    Form.
	 * @param array  $entry   Entry.
	 * @param array  $map     Field map array (keys => gf field ids).
	 * @param array  $labels  Keys in map with human labels for lines.
	 * @return string
	 */
	public static function build_custom_prefix( $form, $entry, $map, $labels ) {
		$lines = array();
		foreach ( $labels as $key => $label ) {
			$fid = rgar( $map, $key );
			if ( $fid === '' || $fid === null ) {
				continue;
			}
			$val = self::get_value( $form, $entry, $fid );
			if ( $val !== '' ) {
				$lines[] = $label . ': ' . $val;
			}
		}
		if ( empty( $lines ) ) {
			return '';
		}
		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Human-readable label for a choice-based field, or raw entry value (e.g. year text/number).
	 *
	 * @param array  $form     Form.
	 * @param array  $entry    Entry.
	 * @param string $field_id Field ID.
	 * @return string
	 */
	public static function get_choice_or_raw_display( $form, $entry, $field_id ) {
		if ( $field_id === '' || $field_id === null ) {
			return '';
		}
		$field_id = (string) $field_id;
		$value    = (string) rgar( $entry, $field_id );
		if ( $value === '' ) {
			return '';
		}
		$field = self::get_field_by_id( $form, $field_id );
		if ( ! $field || empty( $field->choices ) || ! is_array( $field->choices ) ) {
			return $value;
		}
		foreach ( $field->choices as $choice ) {
			if ( (string) rgar( $choice, 'value' ) === $value ) {
				$text = (string) rgar( $choice, 'text' );
				return $text !== '' ? $text : $value;
			}
		}
		return $value;
	}

	/**
	 * TowX VehicleInfo: space-separated Year, Make, Model, and Color from the field map.
	 *
	 * @param array $form  Form.
	 * @param array $entry Entry.
	 * @param array $map   tops_fields map.
	 * @return string
	 */
	public static function build_vehicle_info_from_mmc( $form, $entry, $map ) {
		$parts = array();
		foreach ( array( 'year', 'make_key', 'model_key', 'color_key' ) as $key ) {
			$fid  = rgar( $map, $key );
			$part = self::get_choice_or_raw_display( $form, $entry, $fid );
			if ( $part !== '' ) {
				$parts[] = $part;
			}
		}
		$combined = trim( implode( ' ', $parts ) );
		return (string) preg_replace( '/\s+/u', ' ', $combined );
	}
}
