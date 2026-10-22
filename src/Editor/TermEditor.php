<?php
namespace WebLorem\MultilangTabs\Editor;

use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Storage\TermFields;
use WebLorem\MultilangTabs\Storage\Translator;

defined( 'ABSPATH' ) || exit;

/**
 * Language tabs on the term edit screen (name, description of categories, tags, product attributes, ...).
 */
final class TermEditor {

	private const NONCE_ACTION = 'wl_mlt_save_term';
	private const NONCE_FIELD  = 'wl_mlt_nonce';
	private const DATA_FIELD   = 'wl_mlt_data';

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'edit_term', array( $this, 'save' ), 10, 3 );
	}

	public function assets( string $hook ): void {
		if ( 'term.php' !== $hook || count( Languages::all() ) < 2 ) {
			return;
		}

		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$term_id  = isset( $_GET['tag_ID'] ) ? absint( $_GET['tag_ID'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! $term_id || ! TermFields::is_translatable_taxonomy( $taxonomy ) ) {
			return;
		}

		ScreenTabs::enqueue(
			array(
				'form'      => '#edittag',
				'mount'     => '#edittag .form-table',
				'fields'    => array(
					array( 'key' => 'name', 'selector' => '#name' ),
					array( 'key' => 'description', 'selector' => '#description' ),
				),
				'data'      => Translator::all( $term_id, TermFields::FIELDS, array_keys( Languages::secondary() ), 'term' ),
				'dataField' => self::DATA_FIELD,
				'nonceName' => self::NONCE_FIELD,
				'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	public function save( int $term_id, int $tt_id, string $taxonomy ): void {
		if ( ! isset( $_POST[ self::DATA_FIELD ] ) || ! TermFields::is_translatable_taxonomy( $taxonomy ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ?? '' ) ), self::NONCE_ACTION ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$data = json_decode( wp_unslash( $_POST[ self::DATA_FIELD ] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.

		if ( ! is_array( $data ) ) {
			return;
		}

		foreach ( array_keys( Languages::secondary() ) as $lang ) {
			foreach ( TermFields::FIELDS as $field ) {
				if ( isset( $data[ $lang ][ $field ] ) && is_string( $data[ $lang ][ $field ] ) ) {
					Translator::set( $term_id, $lang, $field, TermFields::sanitize( $field, $data[ $lang ][ $field ] ), 'term' );
				}
			}
		}
	}
}
