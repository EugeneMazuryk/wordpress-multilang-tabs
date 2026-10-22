<?php
namespace WebLorem\MultilangTabs\Storage;

defined( 'ABSPATH' ) || exit;

final class OptionRegistry {

	/** @var string[] */
	private static array $options = array( 'blogname', 'blogdescription' );

	public static function register( string $name ): void {
		if ( ! in_array( $name, self::$options, true ) ) {
			self::$options[] = $name;
		}
	}

	/** @return string[] */
	public static function names(): array {
		return array_values( array_unique( apply_filters( 'wl_mlt_translatable_options', self::$options ) ) );
	}
}
