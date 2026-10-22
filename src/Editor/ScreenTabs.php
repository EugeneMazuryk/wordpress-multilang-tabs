<?php
namespace WebLorem\MultilangTabs\Editor;

use WebLorem\MultilangTabs\Core\Languages;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the generic tab bar (assets/js/inputs.js) for a plain admin form.
 */
final class ScreenTabs {

	public static function enqueue( array $screen ): void {
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
		wp_enqueue_script( 'wl-mlt-inputs', WL_MLT_URL . 'assets/js/inputs.js', array( 'jquery' ), WL_MLT_VERSION, true );
		wp_add_inline_script(
			'wl-mlt-inputs',
			'window.wlMlt = ' . wp_json_encode(
				array(
					'defaultLang' => Languages::default_code(),
					'languages'   => $langs,
					'screen'      => $screen,
				)
			) . ';',
			'before'
		);
	}
}
