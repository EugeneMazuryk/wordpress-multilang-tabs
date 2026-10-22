<?php
namespace WebLorem\MultilangTabs\Integrations\Cf7;

use WebLorem\MultilangTabs\Core\Context;
use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Storage\Translator;

defined( 'ABSPATH' ) || exit;

/**
 * Contact Form 7: the form template, both mail templates and the messages can be translated per form.
 * Translations are post meta (_wl_mlt_{lang}_form, _mail_subject, _mail_body, _mail_2_subject, _mail_2_body,
 * _message_{key}); an empty one means "use the default language". The language of a submission travels in a
 * hidden field, because the REST request that sends the mail has no language prefix in its URL.
 */
final class Cf7 {

	public const NONCE_FIELD = 'wl_mlt_cf7';
	public const LANG_FIELD  = '_wl_mlt_lang';

	public function register(): void {
		if ( ! defined( 'WPCF7_VERSION' ) ) {
			return;
		}

		add_filter( 'wpcf7_contact_form_properties', array( $this, 'translate' ), 20, 2 );
		add_filter( 'wpcf7_form_hidden_fields', array( $this, 'hidden_field' ) );

		if ( is_admin() ) {
			add_filter( 'wpcf7_editor_panels', array( $this, 'panel' ) );
			add_action( 'wpcf7_save_contact_form', array( $this, 'save' ), 10, 3 );
		}
	}

	/** Language to translate to: the submitted one in the REST request, else the language of the page. */
	private function lang(): string {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_POST[ self::LANG_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$lang = sanitize_key( wp_unslash( $_POST[ self::LANG_FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification

			if ( Languages::exists( $lang ) ) {
				return $lang;
			}
		}

		return Context::translating() ? Context::lang() : Languages::default_code();
	}

	public function hidden_field( $fields ) {
		$fields = is_array( $fields ) ? $fields : array();

		$fields[ self::LANG_FIELD ] = Context::lang();

		return $fields;
	}

	public function translate( $properties, $form ) {
		$lang = $this->lang();

		if ( ! is_array( $properties ) || $lang === Languages::default_code() || ! is_object( $form ) || ! $form->id() ) {
			return $properties;
		}

		$id = (int) $form->id();

		$form_html = Translator::get( $id, $lang, 'form' );

		if ( ! Translator::is_empty( $form_html ) ) {
			$properties['form'] = $form_html;
		}

		foreach ( array( 'mail', 'mail_2' ) as $mail ) {
			foreach ( array( 'subject', 'body' ) as $part ) {
				$value = Translator::get( $id, $lang, $mail . '_' . $part );

				if ( ! Translator::is_empty( $value ) && isset( $properties[ $mail ] ) && is_array( $properties[ $mail ] ) ) {
					$properties[ $mail ][ $part ] = $value;
				}
			}
		}

		foreach ( $this->message_keys() as $key ) {
			$value = Translator::get( $id, $lang, 'message_' . $key );

			if ( ! Translator::is_empty( $value ) ) {
				$properties['messages'][ $key ] = $value;
			}
		}

		return $properties;
	}

	/** @return string[] */
	private function message_keys(): array {
		return function_exists( 'wpcf7_messages' ) ? array_keys( wpcf7_messages() ) : array();
	}

	public function panel( $panels ) {
		if ( count( Languages::all() ) < 2 || ! is_array( $panels ) || ! current_user_can( 'wpcf7_edit_contact_forms' ) ) {
			return $panels;
		}

		$panels['wl-mlt-panel'] = array(
			'title'    => __( 'Translations', 'weblorem-multilang-tabs' ),
			'callback' => array( $this, 'render_panel' ),
		);

		return $panels;
	}

	public function render_panel( $form ): void {
		$id       = (int) $form->id();
		$messages = function_exists( 'wpcf7_messages' ) ? wpcf7_messages() : array();
		$current  = (array) $form->prop( 'messages' );
		$langs    = Languages::secondary();

		echo '<h2>' . esc_html__( 'Translations', 'weblorem-multilang-tabs' ) . '</h2>';
		echo '<p class="description">' . esc_html(
			sprintf(
				/* translators: %s: default language name */
				__( 'Leave a field empty to use the text of the default language (%s).', 'weblorem-multilang-tabs' ),
				Languages::all()[ Languages::default_code() ]['name'] ?? ''
			)
		) . '</p>';

		if ( ! $id ) {
			echo '<p>' . esc_html__( 'Save the form first, then add the translations.', 'weblorem-multilang-tabs' ) . '</p>';

			return;
		}

		foreach ( $langs as $code => $language ) {
			$name = static function ( string $field ) use ( $code ): string {
				return self::NONCE_FIELD . '[' . $code . '][' . $field . ']';
			};
			$val  = static function ( string $field ) use ( $id, $code ): string {
				return Translator::get( $id, $code, $field );
			};

			echo '<fieldset class="wl-mlt-cf7" style="margin:0 0 30px;padding:0 0 20px;border-bottom:1px solid #dcdcde">';
			echo '<h3>' . esc_html( Languages::flag_emoji( $language['flag'] ) . ' ' . $language['name'] ) . '</h3>';

			printf(
				'<p><label for="wl-mlt-%1$s-form"><strong>%2$s</strong></label><br><textarea id="wl-mlt-%1$s-form" name="%3$s" class="large-text code" rows="12">%4$s</textarea></p>',
				esc_attr( $code ),
				esc_html__( 'Form', 'weblorem-multilang-tabs' ),
				esc_attr( $name( 'form' ) ),
				esc_textarea( $val( 'form' ) )
			);

			foreach ( array( 'mail' => __( 'Mail', 'weblorem-multilang-tabs' ), 'mail_2' => __( 'Mail (2)', 'weblorem-multilang-tabs' ) ) as $mail => $title ) {
				$props = (array) $form->prop( $mail );

				printf(
					'<p><label for="wl-mlt-%1$s-%2$s-subject"><strong>%3$s: %4$s</strong></label><br><input type="text" id="wl-mlt-%1$s-%2$s-subject" name="%5$s" value="%6$s" class="large-text" placeholder="%7$s"></p>',
					esc_attr( $code ),
					esc_attr( $mail ),
					esc_html( $title ),
					esc_html__( 'Subject', 'weblorem-multilang-tabs' ),
					esc_attr( $name( $mail . '_subject' ) ),
					esc_attr( $val( $mail . '_subject' ) ),
					esc_attr( (string) ( $props['subject'] ?? '' ) )
				);
				printf(
					'<p><label for="wl-mlt-%1$s-%2$s-body"><strong>%3$s: %4$s</strong></label><br><textarea id="wl-mlt-%1$s-%2$s-body" name="%5$s" class="large-text code" rows="8" placeholder="%7$s">%6$s</textarea></p>',
					esc_attr( $code ),
					esc_attr( $mail ),
					esc_html( $title ),
					esc_html__( 'Message body', 'weblorem-multilang-tabs' ),
					esc_attr( $name( $mail . '_body' ) ),
					esc_textarea( $val( $mail . '_body' ) ),
					esc_attr( (string) ( $props['body'] ?? '' ) )
				);
			}

			echo '<p><strong>' . esc_html__( 'Messages', 'weblorem-multilang-tabs' ) . '</strong></p>';

			foreach ( $messages as $key => $message ) {
				printf(
					'<p><label for="wl-mlt-%1$s-msg-%2$s">%3$s</label><br><input type="text" id="wl-mlt-%1$s-msg-%2$s" name="%4$s" value="%5$s" class="large-text" placeholder="%6$s"></p>',
					esc_attr( $code ),
					esc_attr( $key ),
					wp_kses_post( $message['description'] ?? $key ),
					esc_attr( $name( 'message_' . $key ) ),
					esc_attr( $val( 'message_' . $key ) ),
					esc_attr( (string) ( $current[ $key ] ?? '' ) )
				);
			}

			echo '</fieldset>';
		}
	}

	public function save( $form, $args = array(), $context = 'save' ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! is_array( $_POST[ self::NONCE_FIELD ] ) || ! is_object( $form ) || ! $form->id() ) { // phpcs:ignore WordPress.Security.NonceVerification -- CF7 verifies its own nonce before saving.
			return;
		}

		$id = (int) $form->id();

		if ( ! current_user_can( 'wpcf7_edit_contact_form', $id ) ) {
			return;
		}

		$fields = array( 'form', 'mail_subject', 'mail_body', 'mail_2_subject', 'mail_2_body' );

		foreach ( $this->message_keys() as $key ) {
			$fields[] = 'message_' . $key;
		}

		$posted = wp_unslash( $_POST[ self::NONCE_FIELD ] ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput

		foreach ( array_keys( Languages::secondary() ) as $lang ) {
			foreach ( $fields as $field ) {
				if ( isset( $posted[ $lang ][ $field ] ) && is_string( $posted[ $lang ][ $field ] ) ) {
					Translator::set( $id, $lang, $field, trim( $posted[ $lang ][ $field ] ) );
				}
			}
		}
	}
}
