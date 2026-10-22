<?php
namespace WebLorem\MultilangTabs\Editor;

use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Storage\OptionRegistry;
use WebLorem\MultilangTabs\Storage\Translator;

defined( 'ABSPATH' ) || exit;

/**
 * Language tabs on Settings -> General for the translatable options (site title and tagline by default).
 */
final class OptionsEditor {

	private const NONCE_ACTION = 'wl_mlt_save_options';
	private const NONCE_FIELD  = 'wl_mlt_nonce';
	private const DATA_FIELD   = 'wl_mlt_data';

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_init', array( $this, 'save' ) );
	}

	public function assets( string $hook ): void {
		if ( 'options-general.php' !== $hook || count( Languages::all() ) < 2 ) {
			return;
		}

		$fields = array();
		$data   = array();

		foreach ( OptionRegistry::names() as $name ) {
			$fields[] = array(
				'key'      => $name,
				'selector' => '[name="' . $name . '"]',
			);

			foreach ( array_keys( Languages::secondary() ) as $lang ) {
				$data[ $lang ][ $name ] = Translator::get_option_value( $name, $lang );
			}
		}

		ScreenTabs::enqueue(
			array(
				'form'      => 'form[action="options.php"]',
				'mount'     => 'form[action="options.php"] .form-table',
				'fields'    => $fields,
				'data'      => $data,
				'dataField' => self::DATA_FIELD,
				'nonceName' => self::NONCE_FIELD,
				'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/** Runs on options.php, before core saves the posted (default-language) values. */
	public function save(): void {
		if ( ! isset( $_POST[ self::DATA_FIELD ], $_POST['option_page'] ) || 'general' !== $_POST['option_page'] ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ?? '' ) ), self::NONCE_ACTION ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$data = json_decode( wp_unslash( $_POST[ self::DATA_FIELD ] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per value below.

		if ( ! is_array( $data ) ) {
			return;
		}

		foreach ( array_keys( Languages::secondary() ) as $lang ) {
			foreach ( OptionRegistry::names() as $name ) {
				if ( isset( $data[ $lang ][ $name ] ) && is_string( $data[ $lang ][ $name ] ) ) {
					Translator::set_option_value( $name, $lang, sanitize_text_field( $data[ $lang ][ $name ] ) );
				}
			}
		}
	}
}
