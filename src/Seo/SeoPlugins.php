<?php
namespace WebLorem\MultilangTabs\Seo;

use WebLorem\MultilangTabs\Core\Context;
use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Core\Settings;
use WebLorem\MultilangTabs\Storage\FieldRegistry;
use WebLorem\MultilangTabs\Storage\Translator;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps Yoast SEO and Rank Math in step with the current language: og:locale and the canonical
 * of pages that are not translated yet. The Yoast SEO title, description and focus keyword are translatable
 * post fields; Yoast prints them from its indexables, so the output filters swap them for the translation.
 */
final class SeoPlugins {

	public function register(): void {
		add_filter( 'wpseo_locale', array( $this, 'og_locale' ) );
		add_filter( 'rank_math/opengraph/facebook/og_locale', array( $this, 'og_locale' ) );
		add_filter( 'wpseo_canonical', array( $this, 'canonical' ) );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'canonical' ) );

		add_action( 'init', array( $this, 'register_yoast_fields' ), 20 );

		foreach ( array(
			'_yoast_wpseo_title'    => array( 'wpseo_title', 'wpseo_opengraph_title', 'wpseo_twitter_title' ),
			'_yoast_wpseo_metadesc' => array( 'wpseo_metadesc', 'wpseo_opengraph_desc', 'wpseo_twitter_description' ),
		) as $meta_key => $filters ) {
			foreach ( $filters as $filter ) {
				add_filter( $filter, fn( $value ) => $this->yoast_value( $value, $meta_key ) );
			}
		}
	}

	public function register_yoast_fields(): void {
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			return;
		}

		foreach ( Settings::get()['post_types'] as $post_type ) {
			foreach ( array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw' ) as $meta_key ) {
				FieldRegistry::register( $post_type, $meta_key );
			}
		}
	}

	/** The translated Yoast value of the queried post, its %%variables%% replaced; the original when there is none. */
	private function yoast_value( $value, string $meta_key ) {
		$post = get_queried_object();

		if ( Context::is_default() || ! is_singular() || ! $post instanceof \WP_Post ) {
			return $value;
		}

		$translated = Translator::get( $post->ID, Context::lang(), $meta_key );

		if ( Translator::is_empty( $translated ) ) {
			return $value;
		}

		return function_exists( 'wpseo_replace_vars' ) ? wpseo_replace_vars( $translated, $post ) : $translated;
	}

	public function og_locale( $locale ) {
		return Context::is_default() ? $locale : Languages::locale( Context::lang() );
	}

	public function canonical( $url ) {
		$post = get_queried_object();

		if ( ! is_singular() || ! $post instanceof \WP_Post || Alternates::current_translated() ) {
			return $url;
		}

		return Head::default_url( $post );
	}
}
