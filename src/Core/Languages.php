<?php
namespace WebLorem\MultilangTabs\Core;

defined( 'ABSPATH' ) || exit;

final class Languages {

	/** @return array<string,array{code:string,name:string,locale:string,flag:string}> keyed by code, in configured order */
	public static function all(): array {
		$all = array();

		foreach ( Settings::get()['languages'] as $language ) {
			$all[ $language['code'] ] = $language;
		}

		return $all;
	}

	public static function codes(): array {
		return array_keys( self::all() );
	}

	public static function exists( string $code ): bool {
		return isset( self::all()[ $code ] );
	}

	public static function get( string $code ): ?array {
		return self::all()[ $code ] ?? null;
	}

	public static function default_code(): string {
		$default = Settings::get()['default'];

		return self::exists( $default ) ? $default : (string) array_key_first( self::all() );
	}

	/** Languages other than the default one. */
	public static function secondary(): array {
		$all = self::all();
		unset( $all[ self::default_code() ] );

		return $all;
	}

	public static function locale( string $code ): string {
		$language = self::get( $code );

		return $language && '' !== $language['locale'] ? $language['locale'] : $code;
	}

	/** Country code (e.g. "ua") to a flag emoji. */
	public static function flag_emoji( string $country ): string {
		if ( 2 !== strlen( $country ) ) {
			return '';
		}

		$out = '';

		foreach ( str_split( strtoupper( $country ) ) as $char ) {
			$out .= mb_chr( 0x1F1E6 + ord( $char ) - ord( 'A' ), 'UTF-8' );
		}

		return $out;
	}
}
