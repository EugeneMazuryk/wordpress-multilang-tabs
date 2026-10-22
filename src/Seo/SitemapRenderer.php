<?php
namespace WebLorem\MultilangTabs\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Core sitemap renderer that writes one <url> per language version, each listing all versions
 * as xhtml:link alternates. Entries carry them under the wl_mlt_alternates key.
 */
final class SitemapRenderer extends \WP_Sitemaps_Renderer {

	public function get_sitemap_xml( $url_list ) {
		$urlset = new \SimpleXMLElement(
			sprintf(
				'%1$s%2$s%3$s',
				'<?xml version="1.0" encoding="UTF-8" ?>',
				$this->stylesheet,
				'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml" />'
			)
		);

		foreach ( $url_list as $item ) {
			$alternates = $item['wl_mlt_alternates'] ?? array();
			unset( $item['wl_mlt_alternates'] );

			// Without translations the entry is a plain one.
			$versions = count( $alternates ) > 1 ? $alternates : array( 'default' => $item['loc'] ?? '' );

			foreach ( $versions as $url ) {
				$node = $urlset->addChild( 'url' );
				$node->addChild( 'loc', esc_url( $url ) );

				foreach ( array( 'lastmod', 'changefreq', 'priority' ) as $name ) {
					if ( isset( $item[ $name ] ) ) {
						$node->addChild( $name, esc_xml( $item[ $name ] ) );
					}
				}

				if ( count( $alternates ) > 1 ) {
					foreach ( $alternates as $lang => $href ) {
						$link = $node->addChild( 'xhtml:link', null, 'http://www.w3.org/1999/xhtml' );
						$link->addAttribute( 'rel', 'alternate' );
						$link->addAttribute( 'hreflang', Alternates::hreflang( $lang ) );
						$link->addAttribute( 'href', esc_url( $href ) );
					}
				}
			}
		}

		return $urlset->asXML();
	}
}
