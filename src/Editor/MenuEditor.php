<?php
namespace WebLorem\MultilangTabs\Editor;

use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Storage\Translator;

defined( 'ABSPATH' ) || exit;

/**
 * Language tabs for menu items: the "Navigation Label" and "Description" of every item.
 * Translations live in the menu item's own post meta, like any other post.
 */
final class MenuEditor {

	private const NONCE_ACTION = 'wl_mlt_save_menu';
	private const NONCE_FIELD  = 'wl_mlt_nonce';
	private const DATA_FIELD   = 'wl_mlt_menu';
	private const FIELDS       = array( 'title', 'description' );

	private ?array $posted = null;

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_update_nav_menu_item', array( $this, 'save' ), 10, 2 );
	}

	public function assets( string $hook ): void {
		if ( 'nav-menus.php' !== $hook || count( Languages::all() ) < 2 ) {
			return;
		}

		$menu_id = isset( $_REQUEST['menu'] ) ? absint( $_REQUEST['menu'] ) : (int) get_user_option( 'nav_menu_recently_edited' ); // phpcs:ignore WordPress.Security.NonceVerification
		$data    = array();
		$items   = $menu_id ? wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) ) : array();

		foreach ( $items ? $items : array() as $item ) {
			$data[ $item->ID ] = Translator::all( $item->ID, self::FIELDS, array_keys( Languages::secondary() ) );
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
		wp_enqueue_script( 'wl-mlt-menu', WL_MLT_URL . 'assets/js/menu.js', array( 'jquery', 'nav-menu' ), WL_MLT_VERSION, true );
		wp_add_inline_script(
			'wl-mlt-menu',
			'window.wlMlt = ' . wp_json_encode(
				array(
					'defaultLang' => Languages::default_code(),
					'languages'   => $langs,
					'menu'        => array(
						'data'      => (object) $data,
						'dataField' => self::DATA_FIELD,
						'nonceName' => self::NONCE_FIELD,
						'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
					),
				)
			) . ';',
			'before'
		);
	}

	public function save( $menu_id, $item_id ): void {
		$data = $this->posted();

		if ( null === $data || ! isset( $data[ $item_id ] ) || ! is_array( $data[ $item_id ] ) ) {
			return;
		}

		foreach ( array_keys( Languages::secondary() ) as $lang ) {
			foreach ( self::FIELDS as $field ) {
				if ( isset( $data[ $item_id ][ $lang ][ $field ] ) && is_string( $data[ $item_id ][ $lang ][ $field ] ) ) {
					$value = 'title' === $field ? sanitize_text_field( $data[ $item_id ][ $lang ][ $field ] ) : wp_kses_post( $data[ $item_id ][ $lang ][ $field ] );

					Translator::set( (int) $item_id, $lang, $field, $value );
				}
			}
		}
	}

	/** The posted JSON, verified once per request. */
	private function posted(): ?array {
		if ( null !== $this->posted ) {
			return $this->posted ?: null;
		}

		$this->posted = array();

		if ( isset( $_POST[ self::DATA_FIELD ] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ?? '' ) ), self::NONCE_ACTION )
			&& current_user_can( 'edit_theme_options' ) ) {
			$data = json_decode( wp_unslash( $_POST[ self::DATA_FIELD ] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per value in save().

			$this->posted = is_array( $data ) ? $data : array();
		}

		return $this->posted ?: null;
	}
}
