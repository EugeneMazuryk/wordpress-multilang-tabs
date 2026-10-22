<?php
namespace WebLorem\MultilangTabs\Core;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	public static function register( string $prefix, string $base_dir ): void {
		spl_autoload_register(
			static function ( string $class ) use ( $prefix, $base_dir ): void {
				if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
					return;
				}

				$file = $base_dir . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';

				if ( is_readable( $file ) ) {
					require $file;
				}
			}
		);
	}
}
