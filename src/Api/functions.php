<?php
/**
 * Public API. Everything is prefixed with wl_mlt_.
 */

use WebLorem\MultilangTabs\Core\Context;
use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Routing\Url;
use WebLorem\MultilangTabs\Storage\FieldRegistry;
use WebLorem\MultilangTabs\Storage\OptionRegistry;

defined( 'ABSPATH' ) || exit;

function wl_mlt_current_lang(): string {
	return Context::lang();
}

function wl_mlt_default_lang(): string {
	return Languages::default_code();
}

function wl_mlt_is_default_lang( ?string $lang = null ): bool {
	return ( $lang ?? Context::lang() ) === Languages::default_code();
}

/** @return array<string,array{code:string,name:string,locale:string,flag:string}> */
function wl_mlt_get_languages(): array {
	return Languages::all();
}

/** Rewrites a site URL to the given language (current one by default). */
function wl_mlt_url( string $url, ?string $lang = null ): string {
	return Url::for_lang( $url, $lang ?? Context::lang() );
}

/** Marks a meta key of a post type as translatable (stored as _wl_mlt_{lang}_{key}). */
function wl_mlt_register_translatable_field( string $post_type, string $meta_key ): void {
	FieldRegistry::register( $post_type, $meta_key );
}

/** Marks an option (e.g. a theme option) as translatable; translations live in options named _wl_mlt_{lang}_{name}. */
function wl_mlt_register_translatable_option( string $option_name ): void {
	OptionRegistry::register( $option_name );
}

/**
 * Prints (or returns) the front-end language switcher.
 *
 * @param array $args style (list|dropdown), show_flags, show_names, hide_untranslated.
 */
function wl_mlt_language_switcher( array $args = array(), bool $echo = true ): string {
	$html = \WebLorem\MultilangTabs\Frontend\Switcher::render( $args );

	if ( $echo ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in Switcher::render().
	}

	return $html;
}

/** URLs of the current page in every language it exists in: [lang => url]. */
function wl_mlt_get_alternates(): array {
	return \WebLorem\MultilangTabs\Seo\Alternates::for_current();
}

/**
 * Data for a custom language switcher, current language first is up to the caller.
 *
 * @return array<int,array{code:string,name:string,flag:string,url:string,current:bool,translated:bool}>
 */
function wl_mlt_get_switcher_items( bool $hide_untranslated = false ): array {
	return \WebLorem\MultilangTabs\Frontend\Switcher::items( $hide_untranslated );
}
