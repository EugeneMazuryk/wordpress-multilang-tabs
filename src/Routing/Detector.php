<?php
namespace WebLorem\MultilangTabs\Routing;

use WebLorem\MultilangTabs\Core\Languages;

defined( 'ABSPATH' ) || exit;

final class Detector {

	public const COOKIE = 'wl_mlt_lang';

	/** Best configured language for the Accept-Language header, or null when none matches. */
	public static function from_accept_language( string $header ): ?string {
		$candidates = array();

		foreach ( explode( ',', $header ) as $i => $part ) {
			$bits = explode( ';', trim( $part ) );
			$tag  = strtolower( trim( $bits[0] ) );
			$q    = 1.0;

			if ( isset( $bits[1] ) && preg_match( '/q=([0-9.]+)/', $bits[1], $m ) ) {
				$q = (float) $m[1];
			}

			if ( '' !== $tag && '*' !== $tag && $q > 0 ) {
				$candidates[] = array( $tag, $q, $i );
			}
		}

		usort( $candidates, static fn( $a, $b ) => $b[1] <=> $a[1] ?: $a[2] <=> $b[2] );

		foreach ( $candidates as [ $tag ] ) {
			$base = explode( '-', $tag )[0];

			foreach ( Languages::all() as $code => $language ) {
				$locale_base = strtolower( explode( '_', Languages::locale( $code ) )[0] );

				if ( $code === $tag || $code === $base || $locale_base === $base ) {
					return $code;
				}
			}
		}

		return null;
	}

	public static function is_bot( string $user_agent ): bool {
		return (bool) preg_match( '/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor/i', $user_agent );
	}
}
