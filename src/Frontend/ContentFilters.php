<?php
namespace WebLorem\MultilangTabs\Frontend;

use WebLorem\MultilangTabs\Core\Context;
use WebLorem\MultilangTabs\Storage\FieldRegistry;
use WebLorem\MultilangTabs\Storage\OptionRegistry;
use WebLorem\MultilangTabs\Storage\PostFields;
use WebLorem\MultilangTabs\Storage\TermFields;
use WebLorem\MultilangTabs\Storage\Translator;

defined( 'ABSPATH' ) || exit;

/**
 * Shows translations on the front end. Every filter falls back to the original (default language)
 * value when the translation is empty. The default language is never touched.
 */
final class ContentFilters {

	public function register(): void {
		add_filter( 'the_title', array( $this, 'title' ), 5, 2 );
		add_filter( 'single_post_title', array( $this, 'single_title' ), 5, 2 );
		add_filter( 'the_content', array( $this, 'content' ), 1 );
		add_filter( 'get_the_excerpt', array( $this, 'excerpt' ), 5, 2 );
		add_filter( 'get_term', array( $this, 'term' ), 10, 2 );
		add_filter( 'wp_get_nav_menu_items', array( $this, 'menu_items' ), 10 );
		add_filter( 'get_post_metadata', array( $this, 'post_meta' ), 10, 4 );
		add_action( 'init', array( $this, 'register_option_filters' ), 20 );
	}

	/** The translation of a field, or null when there is none worth showing. */
	private function translation( int $post_id, string $field ): ?string {
		if ( ! PostFields::is_translatable_type( (string) get_post_type( $post_id ) ) ) {
			return null;
		}

		$value = Translator::get( $post_id, Context::lang(), $field );

		return Translator::is_empty( $value ) ? null : $value;
	}

	public function title( $title, $post_id = 0 ) {
		if ( ! Context::translating() || ! $post_id ) {
			return $title;
		}

		$translated = $this->translation( (int) $post_id, 'title' );

		if ( null === $translated ) {
			return $title;
		}

		$original = (string) get_post_field( 'post_title', $post_id );

		// Keep the "Protected: " / "Private: " prefix core adds before this filter.
		return '' !== $original && str_contains( (string) $title, $original ) ? preg_replace( '~' . preg_quote( $original, '~' ) . '~', addcslashes( $translated, '\\$' ), (string) $title, 1 ) : $translated;
	}

	public function single_title( $title, $post = null ) {
		if ( ! Context::translating() || ! $post instanceof \WP_Post ) {
			return $title;
		}

		return $this->translation( $post->ID, 'title' ) ?? $title;
	}

	public function content( $content ) {
		if ( ! Context::translating() || ! is_string( $content ) ) {
			return $content;
		}

		$post = get_post();

		if ( ! $post || ! $this->is_post_content( $content, $post->post_content ) ) {
			return $content;
		}

		return $this->translation( $post->ID, 'content' ) ?? $content;
	}

	/** the_content also runs on arbitrary strings; only the post's own content may be swapped. */
	private function is_post_content( string $content, string $post_content ): bool {
		return $content === $post_content || ( '' !== $content && str_contains( $post_content, $content ) );
	}

	public function excerpt( $excerpt, $post = null ) {
		if ( ! Context::translating() || ! $post instanceof \WP_Post ) {
			return $excerpt;
		}

		return $this->translation( $post->ID, 'excerpt' ) ?? $excerpt;
	}

	public function term( $term, $taxonomy = '' ) {
		if ( ! Context::translating() || ! $term instanceof \WP_Term || ! TermFields::is_translatable_taxonomy( $term->taxonomy ) ) {
			return $term;
		}

		foreach ( array( 'name', 'description' ) as $field ) {
			$value = Translator::get( $term->term_id, Context::lang(), $field, 'term' );

			if ( ! Translator::is_empty( $value ) ) {
				$term->$field = $value;
			}
		}

		return $term;
	}

	public function menu_items( $items ) {
		if ( ! Context::translating() || ! is_array( $items ) ) {
			return $items;
		}

		foreach ( $items as $item ) {
			if ( ! $item instanceof \WP_Post ) {
				continue;
			}

			foreach ( array( 'title', 'description' ) as $field ) {
				$value = Translator::get( $item->ID, Context::lang(), $field );

				if ( ! Translator::is_empty( $value ) ) {
					$item->$field = $value;
				}
			}
		}

		return $items;
	}

	/** get_post_meta() of a field registered with wl_mlt_register_translatable_field(). */
	public function post_meta( $value, $object_id, $meta_key, $single ) {
		if ( ! Context::translating() || ! is_string( $meta_key ) || '' === $meta_key || str_starts_with( $meta_key, Translator::PREFIX ) ) {
			return $value;
		}

		if ( ! in_array( $meta_key, FieldRegistry::for_post_type( (string) get_post_type( (int) $object_id ) ), true ) ) {
			return $value;
		}

		$translated = get_metadata_raw( 'post', (int) $object_id, Translator::key( Context::lang(), $meta_key ), true );

		if ( ! is_string( $translated ) || Translator::is_empty( $translated ) ) {
			return $value;
		}

		return $single ? $translated : array( $translated );
	}

	public function register_option_filters(): void {
		foreach ( OptionRegistry::names() as $name ) {
			add_filter(
				'option_' . $name,
				static function ( $value ) use ( $name ) {
					if ( ! Context::translating() ) {
						return $value;
					}

					$translated = Translator::get_option_value( $name, Context::lang() );

					return Translator::is_empty( $translated ) ? $value : $translated;
				}
			);
		}
	}
}
