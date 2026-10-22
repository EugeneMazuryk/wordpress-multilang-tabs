<?php
namespace WebLorem\MultilangTabs\Routing;

use WebLorem\MultilangTabs\Core\Context;
use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the current language and removes the language prefix from the request
 * before WordPress parses it, so /en/about/ resolves exactly like /about/.
 */
final class Router {

	public function register(): void {
		$this->resolve();

		add_filter( 'locale', array( $this, 'filter_locale' ) );
		add_filter( 'redirect_canonical', array( $this, 'filter_canonical' ), 10, 2 );
	}

	private function resolve(): void {
		Context::set( Languages::default_code() );

		if ( ( is_admin() && ! wp_doing_ajax() ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		$uri                = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );
		$path               = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$query              = wp_parse_url( $uri, PHP_URL_QUERY );
		[ $stripped, $lang ] = Url::split_path( $path );
		$prefix_lang        = $lang;

		$is_rest = $this->is_rest( $stripped );
		$is_ajax = wp_doing_ajax();

		if ( ( $is_rest || $is_ajax ) && null === $lang ) {
			$lang = $this->lang_from_param() ?? ( $is_ajax ? $this->lang_from_referer() : null );
		}

		if ( null !== $lang ) {
			Context::set( $lang );
		}

		if ( null !== $prefix_lang ) {
			$_SERVER['WL_MLT_ORIGINAL_URI'] = $uri;
			$_SERVER['REQUEST_URI']         = $stripped . ( $query ? '?' . $query : '' );
		}

		if ( $is_rest || $is_ajax || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
			return;
		}

		$this->front_redirects( $lang, $stripped );
		$this->remember( Context::lang() );
	}

	private function is_rest( string $stripped_path ): bool {
		$prefix = Url::home_path() . '/' . rest_get_url_prefix();

		return isset( $_GET['rest_route'] ) || $stripped_path === $prefix || str_starts_with( $stripped_path, $prefix . '/' );
	}

	private function lang_from_param(): ?string {
		$lang = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : ( isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification

		return '' !== $lang && Languages::exists( $lang ) ? $lang : null;
	}

	private function lang_from_referer(): ?string {
		$referer = (string) ( $_SERVER['HTTP_REFERER'] ?? '' );

		if ( '' === $referer ) {
			return null;
		}

		return Url::split_path( (string) wp_parse_url( $referer, PHP_URL_PATH ) )[1];
	}

	private function front_redirects( ?string $prefix_lang, string $stripped ): void {
		$default = Languages::default_code();

		// /uk/page/ -> /page/ : the default language lives without a prefix.
		if ( $default === $prefix_lang ) {
			$this->redirect( Url::for_lang( Url::current(), $default ), 301 );
		}

		// First visit to the home page: send to the browser language once.
		$is_home = rtrim( $stripped, '/' ) === Url::home_path();

		if ( ! $is_home || null !== $prefix_lang || empty( Settings::get()['auto_redirect'] ) ) {
			return;
		}

		// What the unprefixed home page does depends on who asks: caches must not share it between visitors.
		header( 'Vary: Accept-Language, Cookie', false );

		if ( isset( $_COOKIE[ Detector::COOKIE ] ) ) {
			return;
		}

		$ua = (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' );

		if ( '' === $ua || Detector::is_bot( $ua ) || ! empty( $_GET ) ) {
			return;
		}

		$target = Detector::from_accept_language( (string) ( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '' ) );

		if ( $target && $target !== $default ) {
			$this->remember( $target );
			nocache_headers();
			$this->redirect( Url::for_lang( Url::current(), $target ), 302 );
		}
	}

	private function remember( string $lang ): void {
		if ( headers_sent() || ( $_COOKIE[ Detector::COOKIE ] ?? '' ) === $lang ) {
			return;
		}

		setcookie( Detector::COOKIE, $lang, time() + YEAR_IN_SECONDS, Url::home_path() . '/', '', is_ssl(), true );
		$_COOKIE[ Detector::COOKIE ] = $lang;
	}

	private function redirect( string $url, int $status ): void {
		wp_safe_redirect( $url, $status, 'WebLorem Multilang Tabs' );
		exit;
	}

	public function filter_locale( string $locale ): string {
		return is_admin() || Context::is_default() ? $locale : Languages::locale( Context::lang() );
	}

	/**
	 * WordPress builds the canonical URL from permalinks (prefixed) but the request from the
	 * stripped REQUEST_URI. Compare them without the prefix, then re-add it, to avoid a redirect loop.
	 */
	public function filter_canonical( $redirect_url, $requested_url ) {
		if ( ! $redirect_url || Context::is_default() ) {
			return $redirect_url;
		}

		$bare = Url::for_lang( (string) $redirect_url, Languages::default_code() );

		return $bare === (string) $requested_url ? false : Url::for_lang( $bare, Context::lang() );
	}
}
