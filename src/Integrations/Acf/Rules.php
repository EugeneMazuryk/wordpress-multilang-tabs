<?php
namespace WebLorem\MultilangTabs\Integrations\Acf;

defined( 'ABSPATH' ) || exit;

/**
 * Which ACF fields are translatable.
 *
 * A field is marked with the "wl_mlt_translatable" setting: '1' yes, '0' shared, '' inherit (from the parent
 * field, or from the field group for a top-level field). Everything inside a translatable container
 * (group, repeater, flexible content) belongs to its translated unit, whatever the sub-fields say.
 */
final class Rules {

	public const SETTING = 'wl_mlt_translatable';

	/** Field types that hold no value of their own. */
	private const UNSUPPORTED = array( 'tab', 'accordion', 'message', 'clone' );

	/** @var array<string,bool> */
	private static array $cache = array();

	public static function supported_type( string $type ): bool {
		return ! in_array( $type, self::UNSUPPORTED, true );
	}

	public static function is_translatable( array $field ): bool {
		$key = (string) ( $field['key'] ?? '' );

		if ( '' !== $key && isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		$parent = self::parent_field( $field );
		$result = ! empty( $field['type'] ) && self::supported_type( (string) $field['type'] )
			&& ( self::own( $field, $parent ) || ( $parent && self::is_translatable( $parent ) ) );

		if ( '' !== $key ) {
			self::$cache[ $key ] = $result;
		}

		return $result;
	}

	/** True for a field that sits directly in a field group (not inside another field). */
	public static function is_top_level( array $field ): bool {
		return null === self::parent_field( $field );
	}

	private static function own( array $field, ?array $parent ): bool {
		$flag = (string) ( $field[ self::SETTING ] ?? '' );

		if ( '1' === $flag ) {
			return true;
		}

		if ( '0' === $flag ) {
			return false;
		}

		if ( $parent ) {
			return self::own( $parent, self::parent_field( $parent ) );
		}

		$group = isset( $field['parent'] ) ? acf_get_field_group( $field['parent'] ) : false;

		return $group && ! empty( $group[ self::SETTING ] );
	}

	private static function parent_field( array $field ): ?array {
		$parent = $field['parent'] ?? 0;

		if ( is_string( $parent ) && str_starts_with( $parent, 'field_' ) ) {
			$found = acf_get_field( $parent );

			return $found ? $found : null;
		}

		if ( is_numeric( $parent ) && $parent && 'acf-field' === get_post_type( (int) $parent ) ) {
			$found = acf_get_field( (int) $parent );

			return $found ? $found : null;
		}

		return null;
	}

	/**
	 * Empty enough to mean "no translation, use the default language". '0' counts as empty only for
	 * true/false fields and inside containers, where a lone "off" cannot be told apart from "untouched".
	 */
	public static function is_empty_value( $value, array $field ): bool {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( ! self::is_empty_value( $item, array( 'type' => 'leaf' ) ) ) {
					return false;
				}
			}

			return true;
		}

		if ( null === $value || '' === $value ) {
			return true;
		}

		return '0' === (string) $value && in_array( $field['type'] ?? '', array( 'true_false', 'leaf' ), true );
	}
}
