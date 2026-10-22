<?php
namespace WebLorem\MultilangTabs\Integrations\Acf;

use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Storage\Translator;

defined( 'ABSPATH' ) || exit;

/**
 * Renders, next to every translatable top-level field, one extra copy per secondary language.
 * The copies are named acf_wl_{lang}[field_key] so ACF's own save leaves them alone; Saver stores them.
 * They are printed as inert <template>s and put in place by assets/js/acf.js once ACF is ready,
 * because ACF initialises (and would break on moving) anything that is live in the page.
 */
final class AdminCopies {

	/** @var array<int,array{key:string,lang:string,html:string}> */
	private array $copies = array();

	public function register(): void {
		add_action( 'acf/render_fields', array( $this, 'collect' ), 10, 2 );
		add_action( 'acf/input/admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'acf/input/admin_footer', array( $this, 'print_copies' ), 20 );
	}

	/** ACF's own screens (field groups, post types, taxonomies, options pages) edit definitions, not content. */
	private function on_acf_admin_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && in_array( $screen->post_type, array( 'acf-field-group', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page' ), true );
	}

	public function collect( $fields, $post_id ): void {
		if ( ! is_array( $fields ) || count( Languages::all() ) < 2 || $this->on_acf_admin_screen() ) {
			return;
		}

		$screen = function_exists( 'acf_get_form_data' ) ? acf_get_form_data( 'screen' ) : '';
		$el     = in_array( $screen, array( 'taxonomy', 'user' ), true ) ? 'tr' : 'div';

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || ! Rules::is_top_level( $field ) || ! Rules::is_translatable( $field ) ) {
				continue;
			}

			$group       = acf_get_field_group( $field['parent'] );
			$instruction = 'tr' === $el ? 'field' : ( $group['instruction_placement'] ?? 'label' );

			foreach ( array_keys( Languages::secondary() ) as $lang ) {
				$this->copies[] = array(
					'key'  => $field['key'],
					'lang' => $lang,
					'html' => $this->render_copy( $field, $lang, $post_id, $el, $instruction ),
				);
			}
		}
	}

	private function render_copy( array $field, string $lang, $post_id, string $el, string $instruction ): string {
		// The saved translation lives under a prefixed name; ACF loads (and renders) it like any value.
		$load          = $field;
		$load['name']  = Translator::key( $lang, $field['name'] );
		$copy          = $field;
		$copy['value'] = acf_get_value( $post_id, $load );
		$copy['prefix'] = 'acf_wl_' . $lang;

		// Copies are optional, and their keys repeat: no validation, no conditional logic.
		$copy['required']          = 0;
		$copy['conditional_logic'] = 0;

		ob_start();
		acf_render_field_wrap( $copy, $el, $instruction );

		return (string) ob_get_clean();
	}

	public function enqueue(): void {
		if ( count( Languages::all() ) < 2 || $this->on_acf_admin_screen() ) {
			return;
		}

		$langs = array();

		foreach ( Languages::all() as $code => $language ) {
			$langs[] = array(
				'code'      => $code,
				'name'      => $language['name'],
				'flag'      => Languages::flag_emoji( $language['flag'] ),
				'isDefault' => Languages::default_code() === $code,
			);
		}

		wp_enqueue_style( 'wl-mlt-editor', WL_MLT_URL . 'assets/css/editor.css', array(), WL_MLT_VERSION );
		wp_enqueue_script( 'wl-mlt-acf', WL_MLT_URL . 'assets/js/acf.js', array( 'jquery', 'acf-input' ), WL_MLT_VERSION . '.' . filemtime( WL_MLT_DIR . 'assets/js/acf.js' ), true );
		wp_add_inline_script( 'wl-mlt-acf', 'window.wlMltAcf = ' . wp_json_encode( array( 'defaultLang' => Languages::default_code(), 'languages' => $langs ) ) . ';', 'before' );
	}

	public function print_copies(): void {
		if ( ! $this->copies ) {
			return;
		}

		foreach ( $this->copies as $copy ) {
			printf(
				'<template class="wl-mlt-acf-copy" data-key="%s" data-lang="%s">%s</template>',
				esc_attr( $copy['key'] ),
				esc_attr( $copy['lang'] ),
				$copy['html'] // phpcs:ignore WordPress.Security.EscapeOutput -- markup produced and escaped by ACF.
			);
		}

		$this->copies = array();
	}
}
