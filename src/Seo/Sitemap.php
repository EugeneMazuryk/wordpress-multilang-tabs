<?php
namespace WebLorem\MultilangTabs\Seo;

use WebLorem\MultilangTabs\Core\Languages;

defined( 'ABSPATH' ) || exit;

/**
 * Adds language versions to the WordPress core sitemaps. SEO plugins that replace them
 * (Yoast, Rank Math) build their own sitemaps and are not touched.
 */
final class Sitemap {

	public function register(): void {
		add_action( 'wp_sitemaps_init', array( $this, 'swap_renderer' ) );
		add_filter( 'wp_sitemaps_posts_entry', array( $this, 'post_entry' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_show_on_front_entry', array( $this, 'home_entry' ) );
		add_filter( 'wp_sitemaps_taxonomies_entry', array( $this, 'term_entry' ), 10, 3 );
	}

	public function swap_renderer( $server ): void {
		if ( count( Languages::all() ) > 1 && $server instanceof \WP_Sitemaps ) {
			$server->renderer = new SitemapRenderer();
		}
	}

	public function post_entry( $entry, $post ) {
		if ( $post instanceof \WP_Post ) {
			$entry['wl_mlt_alternates'] = Alternates::for_post( $post );
		}

		return $entry;
	}

	public function home_entry( $entry ) {
		$alternates = array();

		foreach ( array_keys( Languages::all() ) as $lang ) {
			$alternates[ $lang ] = Alternates::in_lang( $lang, static fn() => home_url( '/' ) );
		}

		$entry['wl_mlt_alternates'] = $alternates;

		return $entry;
	}

	public function term_entry( $entry, $term_id, $taxonomy ) {
		$term = get_term( (int) $term_id, (string) $taxonomy );

		if ( $term instanceof \WP_Term ) {
			$entry['wl_mlt_alternates'] = Alternates::for_term( $term );
		}

		return $entry;
	}
}
