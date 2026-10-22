<?php
namespace WebLorem\MultilangTabs\Editor;

use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Storage\PostFields;
use WebLorem\MultilangTabs\Storage\Translator;

defined( 'ABSPATH' ) || exit;

/**
 * Saves the translations posted by the classic editor as one JSON field.
 */
final class PostSaver {

	public const NONCE_ACTION = 'wl_mlt_save_post';
	public const NONCE_FIELD  = 'wl_mlt_nonce';
	public const DATA_FIELD   = 'wl_mlt_data';

	public function register(): void {
		add_action( 'save_post', array( $this, 'save' ), 20, 2 );
	}

	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::DATA_FIELD ] ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ?? '' ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) || ! PostFields::is_translatable_type( $post->post_type ) ) {
			return;
		}

		$data = json_decode( wp_unslash( $_POST[ self::DATA_FIELD ] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.

		if ( ! is_array( $data ) ) {
			return;
		}

		$fields = PostFields::for_post_type( $post->post_type );

		foreach ( array_keys( Languages::secondary() ) as $lang ) {
			if ( ! is_array( $data[ $lang ] ?? null ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				if ( isset( $data[ $lang ][ $field ] ) && is_string( $data[ $lang ][ $field ] ) ) {
					Translator::set( $post_id, $lang, $field, self::sanitize( $field, $data[ $lang ][ $field ] ) );
				}
			}
		}
	}

	public static function sanitize( string $field, string $value ): string {
		switch ( $field ) {
			case 'title':
				return wp_kses( $value, array() );
			default:
				return current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( $value );
		}
	}
}
