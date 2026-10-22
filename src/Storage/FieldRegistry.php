<?php
namespace WebLorem\MultilangTabs\Storage;

defined( 'ABSPATH' ) || exit;

final class FieldRegistry {

	/** @var array<string,string[]> */
	private static array $fields = array();

	public static function register( string $post_type, string $meta_key ): void {
		if ( ! in_array( $meta_key, self::$fields[ $post_type ] ?? array(), true ) ) {
			self::$fields[ $post_type ][] = $meta_key;
		}
	}

	/** @return string[] */
	public static function for_post_type( string $post_type ): array {
		return apply_filters( 'wl_mlt_translatable_fields', self::$fields[ $post_type ] ?? array(), $post_type );
	}
}
