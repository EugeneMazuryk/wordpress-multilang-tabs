<?php
namespace WebLorem\MultilangTabs\Core;

defined( 'ABSPATH' ) || exit;

final class Context {

	public static function set( string $lang ): void {
		$GLOBALS['wl_mlt_current_lang'] = $lang;
	}

	public static function lang(): string {
		$lang = $GLOBALS['wl_mlt_current_lang'] ?? '';

		return is_string( $lang ) && Languages::exists( $lang ) ? $lang : Languages::default_code();
	}

	public static function is_default(): bool {
		return self::lang() === Languages::default_code();
	}

	/** True when output should be translated: a secondary language on the front end (AJAX included), never in the admin. */
	public static function translating(): bool {
		return ! self::is_default() && ( ! is_admin() || wp_doing_ajax() );
	}
}
