<?php
namespace WebLorem\MultilangTabs\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes translations stored next to the original: meta _wl_mlt_{lang}_{field}
 * on posts, terms and menu items, and options named _wl_mlt_{lang}_{option}.
 * Values are raw; fallback to the default language is applied by the callers.
 */
final class Translator {

	public const PREFIX = '_wl_mlt_';

	public static function key( string $lang, string $field ): string {
		return self::PREFIX . $lang . '_' . $field;
	}

	/** @param string $type Meta type: post or term. */
	public static function get( int $id, string $lang, string $field, string $type = 'post' ): string {
		return (string) get_metadata( $type, $id, self::key( $lang, $field ), true );
	}

	/** An empty value removes the meta, so "no row" always means "use the default language". */
	public static function set( int $id, string $lang, string $field, string $value, string $type = 'post' ): void {
		$key = self::key( $lang, $field );

		if ( self::is_empty( $value ) ) {
			delete_metadata( $type, $id, $key );

			return;
		}

		// update_metadata() unslashes its input, the value here is already raw.
		update_metadata( $type, $id, $key, wp_slash( $value ) );
	}

	/**
	 * Every translation of an object in one query (the whole meta cache of the object is warmed once).
	 *
	 * @param string[] $fields
	 * @param string[] $langs
	 * @return array<string,array<string,string>> [lang => [field => value]]
	 */
	public static function all( int $id, array $fields, array $langs, string $type = 'post' ): array {
		$meta = get_metadata( $type, $id );
		$out  = array();

		foreach ( $langs as $lang ) {
			foreach ( $fields as $field ) {
				$value                  = $meta[ self::key( $lang, $field ) ][0] ?? '';
				$out[ $lang ][ $field ] = is_string( $value ) ? $value : '';
			}
		}

		return $out;
	}

	public static function get_option_value( string $name, string $lang ): string {
		return (string) get_option( self::key( $lang, $name ), '' );
	}

	public static function set_option_value( string $name, string $lang, string $value ): void {
		$key = self::key( $lang, $name );

		if ( self::is_empty( $value ) ) {
			delete_option( $key );

			return;
		}

		update_option( $key, $value, false );
	}

	/** True for "nothing worth showing": whitespace, &nbsp;, empty paragraphs and bare line breaks. */
	public static function is_empty( string $value ): bool {
		return (bool) preg_match( '~^(?:\s|&nbsp;|<br\s*/?>|<p>\s*</p>)*$~i', $value );
	}
}
