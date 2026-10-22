<?php
namespace WebLorem\MultilangTabs\Integrations\Acf;

use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Storage\Translator;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the translated copies posted as acf_wl_{lang}. This runs inside "acf/save_post", so ACF has
 * already verified its nonce and the user's permission. ACF itself writes the values, with the field
 * name prefixed (_wl_mlt_{lang}_{name}): repeaters, groups and flexible content keep their native layout.
 */
final class Saver {

	public function register(): void {
		add_action( 'acf/save_post', array( $this, 'save' ), 20 );
	}

	public function save( $post_id ): void {
		foreach ( array_keys( Languages::secondary() ) as $lang ) {
			$values = $_POST[ 'acf_wl_' . $lang ] ?? null; // phpcs:ignore WordPress.Security.NonceVerification -- verified by ACF before acf/save_post.

			if ( ! is_array( $values ) ) {
				continue;
			}

			if ( ! acf_allow_unfiltered_html() ) {
				$values = wp_kses_post_deep( $values );
			}

			foreach ( $values as $field_key => $value ) {
				$field = acf_get_field( $field_key );

				if ( ! $field || ! Rules::is_top_level( $field ) || ! Rules::is_translatable( $field ) ) {
					continue;
				}

				$field['name'] = Translator::key( $lang, $field['name'] );

				// No translation (or a cleared one): remove it, so the default language shows through.
				if ( Rules::is_empty_value( $value, $field ) ) {
					acf_delete_value( $post_id, $field );
				} else {
					acf_update_value( $value, $post_id, $field );
				}
			}
		}
	}
}
