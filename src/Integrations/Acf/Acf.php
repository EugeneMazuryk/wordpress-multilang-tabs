<?php
namespace WebLorem\MultilangTabs\Integrations\Acf;

defined( 'ABSPATH' ) || exit;

/**
 * ACF / Secure Custom Fields integration. Does nothing when neither is active.
 */
final class Acf {

	public static function available(): bool {
		return function_exists( 'acf_get_field' ) && function_exists( 'acf_render_field_wrap' );
	}

	public function register(): void {
		if ( ! self::available() ) {
			return;
		}

		( new Frontend() )->register();
		( new Saver() )->register();

		if ( is_admin() ) {
			( new Settings() )->register();
			( new AdminCopies() )->register();
		}
	}
}
