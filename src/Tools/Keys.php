<?php
namespace WebLorem\MultilangTabs\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Recognises translation storage keys: _wl_mlt_{lang}_{field}, the ACF reference row __wl_mlt_{lang}_{field},
 * and both again with the options_ / _options_ prefix ACF uses on options pages.
 */
final class Keys {

	private const PATTERN = '~^(?:_?options_)?_{1,2}wl_mlt_([a-z0-9-]+)_~';

	/** @return ?string Language code the key belongs to, null when it is not a translation key. */
	public static function lang_of( string $key ): ?string {
		return preg_match( self::PATTERN, $key, $m ) ? $m[1] : null;
	}

	/** @param string[] $langs Empty means every language. */
	public static function matches( string $key, array $langs = array() ): bool {
		$lang = self::lang_of( $key );

		return null !== $lang && ( ! $langs || in_array( $lang, $langs, true ) );
	}

	/** LIKE patterns that select translation rows of the meta and options tables. */
	public static function like_patterns( string $table_type ): array {
		global $wpdb;

		$patterns = array( $wpdb->esc_like( '_wl_mlt_' ) . '%', $wpdb->esc_like( '__wl_mlt_' ) . '%' );

		if ( 'option' === $table_type ) {
			foreach ( array( 'options_', '_options_' ) as $prefix ) {
				foreach ( array( '_wl_mlt_', '__wl_mlt_' ) as $infix ) {
					$patterns[] = $wpdb->esc_like( $prefix . $infix ) . '%';
				}
			}
		}

		return $patterns;
	}
}
