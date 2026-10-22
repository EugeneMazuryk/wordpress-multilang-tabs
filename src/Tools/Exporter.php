<?php
namespace WebLorem\MultilangTabs\Tools;

use WebLorem\MultilangTabs\Core\Languages;

defined( 'ABSPATH' ) || exit;

/**
 * Dumps translations to a portable array. Objects are identified by what survives a move between
 * sites (post type + path, taxonomy + slug, menu + item target), never by numeric IDs.
 * Values are the raw database strings, so serialized ACF data round-trips untouched.
 */
final class Exporter {

	public const FORMAT = 1;

	/**
	 * @param array{langs?:string[],post_types?:string[]} $args
	 */
	public static function build( array $args = array() ): array {
		$langs      = array_values( array_filter( (array) ( $args['langs'] ?? array() ) ) );
		$post_types = array_values( array_filter( (array) ( $args['post_types'] ?? array() ) ) );

		return array(
			'format'     => self::FORMAT,
			'site'       => home_url(),
			'default'    => Languages::default_code(),
			'languages'  => Languages::codes(),
			'posts'      => self::posts( $langs, $post_types ),
			'terms'      => self::terms( $langs ),
			'menu_items' => in_array( 'nav_menu_item', $post_types, true ) || ! $post_types ? self::menu_items( $langs ) : array(),
			'options'    => self::options( $langs ),
		);
	}

	/** @return array<int,array<string,string>> post_id => [meta_key => raw value] */
	private static function rows( string $table, string $id_col, string $key_col, string $val_col, array $langs, string $join = '' ): array {
		global $wpdb;

		$like  = array();
		$args  = array();

		foreach ( Keys::like_patterns( 'meta' ) as $pattern ) {
			$like[] = "m.{$key_col} LIKE %s";
			$args[] = $pattern;
		}

		$sql  = "SELECT m.{$id_col} AS id, m.{$key_col} AS k, m.{$val_col} AS v FROM {$table} m {$join} WHERE (" . implode( ' OR ', $like ) . ') ORDER BY m.' . $id_col . ', m.' . $key_col; // phpcs:ignore WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$out  = array();

		foreach ( (array) $rows as $row ) {
			if ( Keys::matches( $row['k'], $langs ) ) {
				$out[ (int) $row['id'] ][ $row['k'] ] = (string) $row['v'];
			}
		}

		return $out;
	}

	private static function posts( array $langs, array $post_types ): array {
		global $wpdb;

		$out = array();

		foreach ( self::rows( $wpdb->postmeta, 'post_id', 'meta_key', 'meta_value', $langs ) as $id => $meta ) {
			$post = get_post( $id );

			if ( ! $post || in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) || 'nav_menu_item' === $post->post_type ) {
				continue;
			}

			if ( $post_types && ! in_array( $post->post_type, $post_types, true ) ) {
				continue;
			}

			$out[] = array(
				'type' => $post->post_type,
				'path' => is_post_type_hierarchical( $post->post_type ) ? get_page_uri( $post ) : $post->post_name,
				'meta' => $meta,
			);
		}

		return $out;
	}

	private static function terms( array $langs ): array {
		global $wpdb;

		$out = array();

		foreach ( self::rows( $wpdb->termmeta, 'term_id', 'meta_key', 'meta_value', $langs ) as $id => $meta ) {
			$term = get_term( $id );

			if ( $term instanceof \WP_Term ) {
				$out[] = array(
					'taxonomy' => $term->taxonomy,
					'slug'     => $term->slug,
					'meta'     => $meta,
				);
			}
		}

		return $out;
	}

	/** Stable identity of a menu item: menu slug plus what it points to. */
	public static function menu_item_key( int $item_id ): ?string {
		$menus = wp_get_object_terms( $item_id, 'nav_menu', array( 'fields' => 'slugs' ) );

		if ( is_wp_error( $menus ) || ! $menus ) {
			return null;
		}

		$type   = (string) get_post_meta( $item_id, '_menu_item_type', true );
		$object = (string) get_post_meta( $item_id, '_menu_item_object', true );
		$target = 'custom' === $type ? (string) get_post_meta( $item_id, '_menu_item_url', true ) : (string) get_post_meta( $item_id, '_menu_item_object_id', true );

		return implode( '|', array( $menus[0], $type, $object, $target ) );
	}

	private static function menu_items( array $langs ): array {
		global $wpdb;

		$out = array();

		foreach ( self::rows( $wpdb->postmeta, 'post_id', 'meta_key', 'meta_value', $langs ) as $id => $meta ) {
			$post = get_post( $id );

			if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
				continue;
			}

			$key = self::menu_item_key( $id );

			if ( null !== $key ) {
				$out[] = array(
					'key'  => $key,
					'meta' => $meta,
				);
			}
		}

		return $out;
	}

	private static function options( array $langs ): array {
		global $wpdb;

		$like = array();
		$args = array();

		foreach ( Keys::like_patterns( 'option' ) as $pattern ) {
			$like[] = 'option_name LIKE %s';
			$args[] = $pattern;
		}

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name AS k, option_value AS v FROM {$wpdb->options} WHERE " . implode( ' OR ', $like ) . ' ORDER BY option_name', $args ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$out  = array();

		foreach ( (array) $rows as $row ) {
			if ( Keys::matches( $row['k'], $langs ) ) {
				$out[ $row['k'] ] = (string) $row['v'];
			}
		}

		return $out;
	}
}
