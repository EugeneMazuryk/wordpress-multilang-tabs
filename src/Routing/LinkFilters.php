<?php
namespace WebLorem\MultilangTabs\Routing;

use WebLorem\MultilangTabs\Core\Context;

defined( 'ABSPATH' ) || exit;

/**
 * home_url() is the single source for post_link, page_link, term_link, post_type_link,
 * get_permalink, get_pagenum_link (paginate_links) and rest_url, so one filter prefixes them all.
 * Menu custom links are stored as absolute URLs and are handled separately.
 */
final class LinkFilters {

	public function register(): void {
		add_filter( 'home_url', array( $this, 'home_url' ) );
		add_filter( 'wp_nav_menu_objects', array( $this, 'menu_items' ) );
	}

	public function home_url( $url ) {
		if ( ( is_admin() && ! wp_doing_ajax() ) || Context::is_default() ) {
			return $url;
		}

		return Url::for_lang( (string) $url, Context::lang() );
	}

	public function menu_items( $items ) {
		if ( Context::is_default() ) {
			return $items;
		}

		foreach ( $items as $item ) {
			if ( 'custom' === $item->object && ! empty( $item->url ) ) {
				$item->url = Url::for_lang( $item->url, Context::lang() );
			}
		}

		return $items;
	}
}
