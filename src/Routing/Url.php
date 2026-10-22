<?php
namespace WebLorem\MultilangTabs\Routing;

use WebLorem\MultilangTabs\Core\Languages;

defined( 'ABSPATH' ) || exit;

/**
 * Pure URL helpers: detecting, stripping and adding the language prefix.
 */
final class Url {

	private const STATIC_EXT = '~\.(?:css|js|map|jpe?g|png|gif|webp|avif|svg|ico|woff2?|ttf|eot|pdf|zip|mp[34]|txt)$~i';

	private static ?array $excluded = null;

	public static function home_path(): string {
		return rtrim( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ), '/' );
	}

	public static function home_host(): string {
		return (string) wp_parse_url( get_option( 'home' ), PHP_URL_HOST );
	}

	/**
	 * Removes a leading language prefix from a URL path.
	 *
	 * @return array{0:string,1:?string} path without prefix, detected language code.
	 */
	public static function split_path( string $path ): array {
		$home = self::home_path();

		if ( '' !== $home && $path !== $home && ! str_starts_with( $path, $home . '/' ) ) {
			return array( $path, null );
		}

		$rest = substr( $path, strlen( $home ) );

		if ( preg_match( '#^/([a-z0-9-]+)(/.*)?$#', $rest, $m ) && Languages::exists( $m[1] ) ) {
			return array( $home . ( $m[2] ?? '/' ), $m[1] );
		}

		return array( $path, null );
	}

	/**
	 * Paths (relative to the home path) that never get a language prefix.
	 */
	private static function excluded_prefixes(): array {
		if ( null === self::$excluded ) {
			$home     = self::home_path();
			$prefixes = array( '/wp-admin', '/wp-login.php', '/wp-cron.php', '/xmlrpc.php', '/wp-content', '/wp-includes', '/' . rest_get_url_prefix() );

			foreach ( array( get_option( 'siteurl' ), content_url() ) as $abs ) {
				$path = rtrim( (string) wp_parse_url( $abs, PHP_URL_PATH ), '/' );

				if ( '' !== $home && str_starts_with( $path, $home ) ) {
					$path = substr( $path, strlen( $home ) );
				}

				if ( '' !== $path ) {
					$prefixes[] = $path;
				}
			}

			self::$excluded = array_unique( $prefixes );
		}

		return self::$excluded;
	}

	public static function is_excluded( string $path ): bool {
		$rest = substr( $path, strlen( self::home_path() ) );

		if ( preg_match( self::STATIC_EXT, $rest ) ) {
			return true;
		}

		foreach ( self::excluded_prefixes() as $prefix ) {
			if ( $rest === $prefix || str_starts_with( $rest, $prefix . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Rewrites an absolute site URL so it points to the given language.
	 * Foreign hosts and excluded paths (admin, REST, uploads, static files) are returned untouched.
	 */
	public static function for_lang( string $url, string $lang ): string {
		$parts = wp_parse_url( $url );

		if ( ! $parts || empty( $parts['host'] ) || 0 !== strcasecmp( $parts['host'], self::home_host() ) ) {
			return $url;
		}

		[ $path ] = self::split_path( $parts['path'] ?? '' );

		if ( self::is_excluded( $path ) ) {
			return $url;
		}

		if ( $lang !== Languages::default_code() ) {
			$home = self::home_path();
			$path = $home . '/' . $lang . substr( $path, strlen( $home ) );
		}

		return ( $parts['scheme'] ?? 'http' ) . '://' . $parts['host']
			. ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' )
			. $path
			. ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' )
			. ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
	}

	/** Absolute URL of the current request, as the browser sent it (prefix included). */
	public static function current(): string {
		$home = wp_parse_url( get_option( 'home' ) );
		$base = ( $home['scheme'] ?? 'http' ) . '://' . ( $home['host'] ?? '' ) . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );

		return $base . ( $_SERVER['WL_MLT_ORIGINAL_URI'] ?? $_SERVER['REQUEST_URI'] ?? '/' );
	}
}
