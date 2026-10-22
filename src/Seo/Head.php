<?php
namespace WebLorem\MultilangTabs\Seo;

use WebLorem\MultilangTabs\Core\Context;
use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Storage\PostFields;

defined( 'ABSPATH' ) || exit;

/**
 * hreflang links and the canonical URL of untranslated pages (which show the default language).
 */
final class Head {

	public function register(): void {
		add_action( 'wp_head', array( $this, 'hreflang' ), 1 );
		add_filter( 'get_canonical_url', array( $this, 'canonical' ), 10, 2 );
	}

	public function hreflang(): void {
		if ( count( Languages::all() ) < 2 ) {
			return;
		}

		$alternates = Alternates::for_current();

		if ( count( $alternates ) < 2 ) {
			return;
		}

		foreach ( $alternates as $lang => $url ) {
			printf( '<link rel="alternate" hreflang="%s" href="%s" />' . "\n", esc_attr( Alternates::hreflang( $lang ) ), esc_url( $url ) );
		}

		$default = Languages::default_code();

		if ( isset( $alternates[ $default ] ) ) {
			printf( '<link rel="alternate" hreflang="x-default" href="%s" />' . "\n", esc_url( $alternates[ $default ] ) );
		}
	}

	/** An untranslated page in another language is a duplicate: point search engines to the original. */
	public function canonical( $url, $post ) {
		if ( Context::is_default() || ! $post instanceof \WP_Post || ! PostFields::is_translatable_type( $post->post_type ) || Alternates::post_translated( $post->ID, Context::lang() ) ) {
			return $url;
		}

		return self::default_url( $post );
	}

	public static function default_url( \WP_Post $post ): string {
		return Alternates::in_lang( Languages::default_code(), static fn() => (string) get_permalink( $post ) );
	}
}
