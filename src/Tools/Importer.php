<?php
namespace WebLorem\MultilangTabs\Tools;

use WebLorem\MultilangTabs\Core\Languages;

defined( 'ABSPATH' ) || exit;

/**
 * Applies an export to this site. Objects that cannot be found are reported, never created.
 */
final class Importer {

	/**
	 * @param array{replace?:bool,dry_run?:bool,langs?:string[]} $opts replace: drop existing translations of a matched object first.
	 * @return array{updated:array<string,int>,missing:string[],skipped_langs:string[],keys:int}
	 */
	public static function run( array $data, array $opts = array() ): array {
		if ( ( $data['format'] ?? 0 ) !== Exporter::FORMAT ) {
			throw new \InvalidArgumentException( __( 'Unsupported or missing export format.', 'weblorem-multilang-tabs' ) );
		}

		$ctx = array(
			'replace' => ! empty( $opts['replace'] ),
			'dry'     => ! empty( $opts['dry_run'] ),
			'langs'   => array_values( array_filter( (array) ( $opts['langs'] ?? array() ) ) ),
			'skipped' => array(),
			'keys'    => 0,
		);

		$report = array(
			'updated'       => array(
				'posts'      => 0,
				'terms'      => 0,
				'menu_items' => 0,
				'options'    => 0,
			),
			'missing'       => array(),
			'skipped_langs' => array(),
			'keys'          => 0,
		);

		foreach ( (array) ( $data['posts'] ?? array() ) as $entry ) {
			$id = self::find_post( (string) ( $entry['type'] ?? '' ), (string) ( $entry['path'] ?? '' ) );

			if ( ! $id ) {
				$report['missing'][] = 'post ' . ( $entry['type'] ?? '?' ) . ':' . ( $entry['path'] ?? '?' );
				continue;
			}

			$report['updated']['posts'] += self::apply( 'post', $id, (array) ( $entry['meta'] ?? array() ), $ctx );
		}

		foreach ( (array) ( $data['terms'] ?? array() ) as $entry ) {
			$term = get_term_by( 'slug', (string) ( $entry['slug'] ?? '' ), (string) ( $entry['taxonomy'] ?? '' ) );

			if ( ! $term instanceof \WP_Term ) {
				$report['missing'][] = 'term ' . ( $entry['taxonomy'] ?? '?' ) . ':' . ( $entry['slug'] ?? '?' );
				continue;
			}

			$report['updated']['terms'] += self::apply( 'term', $term->term_id, (array) ( $entry['meta'] ?? array() ), $ctx );
		}

		$items = (array) ( $data['menu_items'] ?? array() );

		if ( $items ) {
			$index = self::menu_item_index();

			foreach ( $items as $entry ) {
				$id = $index[ (string) ( $entry['key'] ?? '' ) ] ?? 0;

				if ( ! $id ) {
					$report['missing'][] = 'menu item ' . ( $entry['key'] ?? '?' );
					continue;
				}

				$report['updated']['menu_items'] += self::apply( 'post', $id, (array) ( $entry['meta'] ?? array() ), $ctx );
			}
		}

		foreach ( (array) ( $data['options'] ?? array() ) as $name => $raw ) {
			if ( ! self::allowed( (string) $name, $ctx ) || ! is_string( $raw ) ) {
				continue;
			}

			++$ctx['keys'];
			++$report['updated']['options'];

			if ( ! $ctx['dry'] ) {
				update_option( $name, maybe_unserialize( $raw ), false );
			}
		}

		$report['skipped_langs'] = array_values( array_unique( $ctx['skipped'] ) );
		$report['keys']          = $ctx['keys'];

		return $report;
	}

	private static function allowed( string $key, array &$ctx ): bool {
		$lang = Keys::lang_of( $key );

		if ( null === $lang ) {
			return false;
		}

		if ( ! Languages::exists( $lang ) ) {
			$ctx['skipped'][] = $lang;

			return false;
		}

		return ! $ctx['langs'] || in_array( $lang, $ctx['langs'], true );
	}

	/** @return int 1 when the object received any translation key. */
	private static function apply( string $type, int $id, array $meta, array &$ctx ): int {
		$wanted = array();

		foreach ( $meta as $key => $raw ) {
			if ( is_string( $raw ) && self::allowed( (string) $key, $ctx ) ) {
				$wanted[ $key ] = $raw;
			}
		}

		if ( ! $wanted && ! $ctx['replace'] ) {
			return 0;
		}

		$ctx['keys'] += count( $wanted );

		if ( $ctx['dry'] ) {
			return $wanted ? 1 : 0;
		}

		if ( $ctx['replace'] ) {
			foreach ( array_keys( (array) get_metadata( $type, $id ) ) as $existing ) {
				if ( ! isset( $wanted[ $existing ] ) && Keys::matches( (string) $existing, $ctx['langs'] ) && Languages::exists( (string) Keys::lang_of( (string) $existing ) ) ) {
					delete_metadata( $type, $id, $existing );
				}
			}
		}

		foreach ( $wanted as $key => $raw ) {
			update_metadata( $type, $id, $key, wp_slash( maybe_unserialize( $raw ) ) );
		}

		return $wanted ? 1 : 0;
	}

	private static function find_post( string $type, string $path ): int {
		if ( '' === $type || '' === $path || ! post_type_exists( $type ) ) {
			return 0;
		}

		if ( is_post_type_hierarchical( $type ) ) {
			$post = get_page_by_path( $path, OBJECT, $type );

			return $post ? (int) $post->ID : 0;
		}

		$ids = get_posts(
			array(
				'post_type'      => $type,
				'name'           => $path,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		return (int) ( $ids[0] ?? 0 );
	}

	/** @return array<string,int> */
	private static function menu_item_index(): array {
		$index = array();

		foreach ( get_posts( array( 'post_type' => 'nav_menu_item', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) ) as $id ) {
			$key = Exporter::menu_item_key( (int) $id );

			if ( null !== $key && ! isset( $index[ $key ] ) ) {
				$index[ $key ] = (int) $id;
			}
		}

		return $index;
	}
}
