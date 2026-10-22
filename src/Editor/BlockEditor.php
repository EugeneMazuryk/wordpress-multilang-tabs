<?php
namespace WebLorem\MultilangTabs\Editor;

use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Storage\PostFields;
use WebLorem\MultilangTabs\Storage\Translator;

defined( 'ABSPATH' ) || exit;

/**
 * Gutenberg support. Translations travel through the REST API as registered post meta
 * (_wl_mlt_{lang}_{field}); the JS side swaps what the editor shows and rewrites the save request.
 */
final class BlockEditor {

	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'assets' ) );
	}

	/** @return string[] translatable post types that are edited through REST. */
	private function post_types(): array {
		$types = array();

		foreach ( \WebLorem\MultilangTabs\Core\Settings::get()['post_types'] as $type ) {
			$object = get_post_type_object( $type );

			if ( $object && $object->show_in_rest ) {
				$types[] = $type;
			}
		}

		return $types;
	}

	public function register_meta(): void {
		$secondary = array_keys( Languages::secondary() );

		foreach ( $this->post_types() as $type ) {
			// REST only exposes meta for post types that declare custom-fields support.
			add_post_type_support( $type, 'custom-fields' );

			foreach ( $secondary as $lang ) {
				foreach ( PostFields::for_post_type( $type ) as $field ) {
					register_post_meta(
						$type,
						Translator::key( $lang, $field ),
						array(
							'type'              => 'string',
							'single'            => true,
							'show_in_rest'      => true,
							'auth_callback'     => static fn( $allowed, $key, $post_id ) => current_user_can( 'edit_post', $post_id ),
							'sanitize_callback' => static fn( $value ) => PostSaver::sanitize( $field, (string) $value ),
						)
					);
				}
			}
		}
	}

	public function assets(): void {
		$post = get_post();

		if ( ! $post || ! in_array( $post->post_type, $this->post_types(), true ) || count( Languages::all() ) < 2 ) {
			return;
		}

		$object = get_post_type_object( $post->post_type );
		$fields = PostFields::for_post_type( $post->post_type );
		$langs  = array();

		foreach ( Languages::all() as $code => $language ) {
			$langs[] = array(
				'code'      => $code,
				'name'      => $language['name'],
				'flag'      => Languages::flag_emoji( $language['flag'] ),
				'isDefault' => Languages::default_code() === $code,
			);
		}

		wp_enqueue_style( 'wl-mlt-editor', WL_MLT_URL . 'assets/css/editor.css', array(), WL_MLT_VERSION );
		wp_enqueue_script(
			'wl-mlt-gutenberg',
			WL_MLT_URL . 'assets/js/gutenberg.js',
			array( 'wp-plugins', 'wp-editor', 'wp-data', 'wp-element', 'wp-i18n', 'wp-blocks', 'wp-api-fetch', 'wp-notices' ),
			WL_MLT_VERSION,
			true
		);
		wp_add_inline_script(
			'wl-mlt-gutenberg',
			'window.wlMlt = ' . wp_json_encode(
				array(
					'postType'    => $post->post_type,
					'restBase'    => $object->rest_base ?: $object->name,
					'defaultLang' => Languages::default_code(),
					'languages'   => $langs,
					'fields'      => array_values( array_diff( $fields, PostFields::meta_fields( $post->post_type ) ) ),
				'meta'        => PostFields::meta_fields( $post->post_type ),
					'data'        => Translator::all( $post->ID, $fields, array_keys( Languages::secondary() ) ),
					'i18n'        => array(
						'language' => __( 'Language', 'weblorem-multilang-tabs' ),
						'saved'    => __( 'Saved. Showing the default language again.', 'weblorem-multilang-tabs' ),
					),
				)
			) . ';',
			'before'
		);
	}
}
