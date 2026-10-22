<?php
namespace WebLorem\MultilangTabs\Storage;

use WebLorem\MultilangTabs\Core\Settings;

defined( 'ABSPATH' ) || exit;

final class TermFields {

	public const FIELDS = array( 'name', 'description' );

	public static function is_translatable_taxonomy( string $taxonomy ): bool {
		return in_array( $taxonomy, Settings::get()['taxonomies'], true ) && taxonomy_exists( $taxonomy );
	}

	public static function sanitize( string $field, string $value ): string {
		switch ( $field ) {
			case 'name':
				return wp_kses( $value, array() );
			default:
				return current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value );
		}
	}
}
