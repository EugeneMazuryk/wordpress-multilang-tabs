<?php
namespace WebLorem\MultilangTabs\Storage;

use WebLorem\MultilangTabs\Core\Settings;

defined( 'ABSPATH' ) || exit;

final class PostFields {

	public static function is_translatable_type( string $post_type ): bool {
		return in_array( $post_type, Settings::get()['post_types'], true ) && post_type_exists( $post_type );
	}

	/**
	 * Core fields of a post type that carry translations.
	 *
	 * @return string[]
	 */
	public static function for_post_type( string $post_type ): array {
		$fields = array();

		foreach ( array(
			'title'   => 'title',
			'content' => 'editor',
			'excerpt' => 'excerpt',
		) as $field => $feature ) {
			if ( post_type_supports( $post_type, $feature ) ) {
				$fields[] = $field;
			}
		}

		return array_merge( $fields, self::meta_fields( $post_type ) );
	}

	/**
	 * Custom meta keys registered as translatable (wl_mlt_register_translatable_field).
	 *
	 * @return string[]
	 */
	public static function meta_fields( string $post_type ): array {
		return FieldRegistry::for_post_type( $post_type );
	}
}
