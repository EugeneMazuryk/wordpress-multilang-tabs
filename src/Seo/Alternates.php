<?php
namespace WebLorem\MultilangTabs\Seo;

use WebLorem\MultilangTabs\Core\Context;
use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Routing\Url;
use WebLorem\MultilangTabs\Storage\PostFields;
use WebLorem\MultilangTabs\Storage\TermFields;
use WebLorem\MultilangTabs\Storage\Translator;

defined( 'ABSPATH' ) || exit;

/**
 * Language versions of the current page, shared by hreflang, canonical, sitemaps and the switcher.
 * A version exists when the default language does, or when the translation has a title or content.
 */
final class Alternates {

	/** Runs $fn with another current language, then restores the previous one. */
	public static function in_lang( string $lang, callable $fn ) {
		$previous = Context::lang();
		Context::set( $lang );

		try {
			return $fn();
		} finally {
			Context::set( $previous );
		}
	}

	public static function post_translated( int $post_id, string $lang ): bool {
		if ( Languages::default_code() === $lang ) {
			return true;
		}

		foreach ( array( 'title', 'content' ) as $field ) {
			if ( ! Translator::is_empty( Translator::get( $post_id, $lang, $field ) ) ) {
				return true;
			}
		}

		return false;
	}

	public static function term_translated( int $term_id, string $lang ): bool {
		return Languages::default_code() === $lang || ! Translator::is_empty( Translator::get( $term_id, $lang, 'name', 'term' ) );
	}

	/** @return array<string,string> [lang => url] of the post in every language it exists in. */
	public static function for_post( \WP_Post $post ): array {
		$out = array();

		foreach ( array_keys( Languages::all() ) as $lang ) {
			if ( PostFields::is_translatable_type( $post->post_type ) && ! self::post_translated( $post->ID, $lang ) ) {
				continue;
			}

			$out[ $lang ] = self::in_lang( $lang, static fn() => (string) get_permalink( $post ) );
		}

		return $out;
	}

	/** @return array<string,string> */
	public static function for_term( \WP_Term $term ): array {
		$out = array();

		foreach ( array_keys( Languages::all() ) as $lang ) {
			if ( TermFields::is_translatable_taxonomy( $term->taxonomy ) && ! self::term_translated( $term->term_id, $lang ) ) {
				continue;
			}

			$link = self::in_lang( $lang, static fn() => get_term_link( $term ) );

			if ( is_string( $link ) ) {
				$out[ $lang ] = $link;
			}
		}

		return $out;
	}

	/** Same page, only the prefix changes: home, archives, paged lists. */
	private static function by_prefix( array $langs ): array {
		$out = array();

		foreach ( $langs as $lang ) {
			$out[ $lang ] = Url::for_lang( Url::current(), $lang );
		}

		return $out;
	}

	/**
	 * Versions of the request being served. Empty for pages that have no counterpart (search, 404, feeds).
	 *
	 * @return array<string,string>
	 */
	public static function for_current(): array {
		if ( is_404() || is_search() || is_feed() || is_embed() ) {
			return array();
		}

		$object = get_queried_object();

		if ( is_singular() && $object instanceof \WP_Post ) {
			return self::for_post( $object );
		}

		if ( ( is_category() || is_tag() || is_tax() ) && $object instanceof \WP_Term && ! is_paged() ) {
			return self::for_term( $object );
		}

		return self::by_prefix( Languages::codes() );
	}

	/** False when the current language only shows fallback content of the default one. */
	public static function current_translated(): bool {
		$lang   = Context::lang();
		$object = get_queried_object();

		if ( is_singular() && $object instanceof \WP_Post && PostFields::is_translatable_type( $object->post_type ) ) {
			return self::post_translated( $object->ID, $lang );
		}

		if ( $object instanceof \WP_Term && ( is_category() || is_tag() || is_tax() ) && TermFields::is_translatable_taxonomy( $object->taxonomy ) ) {
			return self::term_translated( $object->term_id, $lang );
		}

		return true;
	}

	/** BCP 47 tag for hreflang and lang attributes: en_US becomes en-US. */
	public static function hreflang( string $lang ): string {
		return (string) apply_filters( 'wl_mlt_hreflang', str_replace( '_', '-', Languages::locale( $lang ) ), $lang );
	}
}
