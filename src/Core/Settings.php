<?php
namespace WebLorem\MultilangTabs\Core;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'wl_mlt_settings';

	private static ?array $cache = null;

	public static function defaults(): array {
		return array(
			'languages'     => array(
				array(
					'code'   => 'uk',
					'name'   => 'Українська',
					'locale' => 'uk',
					'flag'   => 'ua',
				),
				array(
					'code'   => 'en',
					'name'   => 'English',
					'locale' => 'en_US',
					'flag'   => 'gb',
				),
			),
			'default'       => 'uk',
			'auto_redirect' => true,
			'post_types'    => array( 'post', 'page' ),
			'taxonomies'    => array( 'category', 'post_tag' ),
			'delete_data'   => false,
		);
	}

	public static function ensure_defaults(): void {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, self::defaults() );
		}
	}

	public static function get(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION );
			self::$cache = is_array( $stored ) ? array_merge( self::defaults(), $stored ) : self::defaults();
		}

		return self::$cache;
	}

	public static function flush_cache(): void {
		self::$cache = null;
	}

	/**
	 * Codes that would collide with WordPress paths.
	 */
	public static function reserved_codes(): array {
		return apply_filters( 'wl_mlt_reserved_codes', array( 'wp', 'app', 'admin', 'feed', 'page', 'embed', 'amp' ) );
	}

	public static function sanitize( $input ): array {
		$previous = self::get();
		$input    = is_array( $input ) ? $input : array();

		$languages = array();
		$row_codes = array();

		foreach ( (array) ( $input['languages'] ?? array() ) as $i => $row ) {
			$code = strtolower( trim( (string) ( $row['code'] ?? '' ) ) );

			if ( ! preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})?$/', $code ) ) {
				continue;
			}

			if ( in_array( $code, self::reserved_codes(), true ) || isset( $languages[ $code ] ) ) {
				add_settings_error( self::OPTION, 'wl_mlt_code_' . $code, sprintf( /* translators: %s: language code */ __( 'Language code "%s" is reserved or duplicated and was skipped.', 'weblorem-multilang-tabs' ), $code ) );
				continue;
			}

			$locale = trim( (string) ( $row['locale'] ?? '' ) );
			$locale = preg_match( '/^[a-z]{2,3}(?:_[A-Za-z0-9]{2,8})?$/', $locale ) ? $locale : '';
			$name   = sanitize_text_field( (string) ( $row['name'] ?? '' ) );

			$languages[ $code ] = array(
				'code'   => $code,
				'name'   => '' !== $name ? $name : $code,
				'locale' => $locale,
				'flag'   => substr( preg_replace( '/[^a-z]/', '', strtolower( (string) ( $row['flag'] ?? '' ) ) ), 0, 2 ),
			);
			$row_codes[ (int) $i ] = $code;
		}

		if ( ! $languages ) {
			add_settings_error( self::OPTION, 'wl_mlt_empty', __( 'At least one valid language is required. Changes were not saved.', 'weblorem-multilang-tabs' ) );

			return $previous;
		}

		$default = $row_codes[ (int) ( $input['default'] ?? -1 ) ] ?? array_key_first( $languages );

		$post_types = array_keys( get_post_types( array( 'public' => true ) ) );
		$taxonomies = array_keys( get_taxonomies( array( 'public' => true ) ) );

		return array(
			'languages'     => array_values( $languages ),
			'default'       => $default,
			'auto_redirect' => ! empty( $input['auto_redirect'] ),
			'post_types'    => array_values( array_intersect( array_map( 'sanitize_key', (array) ( $input['post_types'] ?? array() ) ), $post_types ) ),
			'taxonomies'    => array_values( array_intersect( array_map( 'sanitize_key', (array) ( $input['taxonomies'] ?? array() ) ), $taxonomies ) ),
			'delete_data'   => ! empty( $input['delete_data'] ),
		);
	}
}
