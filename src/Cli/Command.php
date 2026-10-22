<?php
namespace WebLorem\MultilangTabs\Cli;

use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Storage\PostFields;
use WebLorem\MultilangTabs\Storage\TermFields;
use WebLorem\MultilangTabs\Storage\Translator;
use WebLorem\MultilangTabs\Tools\Exporter;
use WebLorem\MultilangTabs\Tools\Importer;
use WebLorem\MultilangTabs\Tools\Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Manage WebLorem Multilang Tabs translations.
 */
final class Command {

	/**
	 * Shows languages and how much content is translated into each of them.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wl-mlt status
	 *
	 * @when after_wp_load
	 */
	public function status( $args, $assoc_args ): void {
		$rows = array();

		foreach ( Languages::all() as $code => $language ) {
			$row = array(
				'code'    => $code,
				'name'    => $language['name'],
				'locale'  => $language['locale'],
				'default' => Languages::default_code() === $code ? 'yes' : '',
			);

			foreach ( \WebLorem\MultilangTabs\Core\Settings::get()['post_types'] as $type ) {
				$row[ $type ] = Languages::default_code() === $code ? self::count_posts( $type ) : self::translated_posts( $type, $code ) . '/' . self::count_posts( $type );
			}

			foreach ( \WebLorem\MultilangTabs\Core\Settings::get()['taxonomies'] as $taxonomy ) {
				$row[ $taxonomy ] = Languages::default_code() === $code ? self::count_terms( $taxonomy ) : self::translated_terms( $taxonomy, $code ) . '/' . self::count_terms( $taxonomy );
			}

			$rows[] = $row;
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array_keys( $rows[0] ?? array() ) );
	}

	/**
	 * Exports translations to JSON (a file, or stdout without one).
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : Target file. Prints to stdout when omitted.
	 *
	 * [--lang=<codes>]
	 * : Comma separated language codes. All languages by default.
	 *
	 * [--post-type=<types>]
	 * : Comma separated post types (nav_menu_item for menu items). All by default.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wl-mlt export translations.json --lang=en
	 */
	public function export( $args, $assoc_args ): void {
		$data = Exporter::build(
			array(
				'langs'      => self::csv( $assoc_args['lang'] ?? '' ),
				'post_types' => self::csv( $assoc_args['post-type'] ?? '' ),
			)
		);
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( empty( $args[0] ) ) {
			\WP_CLI::line( $json );

			return;
		}

		if ( false === file_put_contents( $args[0], $json ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			\WP_CLI::error( 'Cannot write ' . $args[0] );
		}

		\WP_CLI::success( sprintf( 'Exported %d posts, %d terms, %d menu items, %d options to %s.', count( $data['posts'] ), count( $data['terms'] ), count( $data['menu_items'] ), count( $data['options'] ), $args[0] ) );
	}

	/**
	 * Imports translations from a JSON export. Posts, terms and menu items are matched by
	 * slug/path, not by ID; ones that do not exist here are reported and skipped.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : JSON file made by `wp wl-mlt export`.
	 *
	 * [--lang=<codes>]
	 * : Only import these languages.
	 *
	 * [--replace]
	 * : Remove existing translations of every matched object first, instead of only overwriting the imported keys.
	 *
	 * [--dry-run]
	 * : Report what would change without writing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wl-mlt import translations.json --dry-run
	 */
	public function import( $args, $assoc_args ): void {
		$raw  = is_readable( $args[0] ) ? file_get_contents( $args[0] ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions
		$data = false === $raw ? null : json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			\WP_CLI::error( 'Cannot read a JSON export from ' . $args[0] );
		}

		try {
			$report = Importer::run(
				$data,
				array(
					'replace' => isset( $assoc_args['replace'] ),
					'dry_run' => isset( $assoc_args['dry-run'] ),
					'langs'   => self::csv( $assoc_args['lang'] ?? '' ),
				)
			);
		} catch ( \InvalidArgumentException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}

		foreach ( $report['missing'] as $missing ) {
			\WP_CLI::warning( 'Not found, skipped: ' . $missing );
		}

		if ( $report['skipped_langs'] ) {
			\WP_CLI::warning( 'Languages that are not configured here were skipped: ' . implode( ', ', $report['skipped_langs'] ) );
		}

		\WP_CLI::success(
			sprintf(
				'%s%d keys: %d posts, %d terms, %d menu items, %d options.',
				isset( $assoc_args['dry-run'] ) ? '[dry run] ' : '',
				$report['keys'],
				$report['updated']['posts'],
				$report['updated']['terms'],
				$report['updated']['menu_items'],
				$report['updated']['options']
			)
		);
	}

	/**
	 * Deletes every translation of a language (posts, terms, menu items, options). Use after removing a language.
	 *
	 * ## OPTIONS
	 *
	 * <lang>
	 * : Language code.
	 *
	 * [--yes]
	 * : Do not ask for confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wl-mlt clear en --yes
	 */
	public function clear( $args, $assoc_args ): void {
		$lang = sanitize_key( $args[0] );

		if ( Languages::default_code() === $lang ) {
			\WP_CLI::error( 'The default language is stored in the posts themselves, there is nothing to clear.' );
		}

		\WP_CLI::confirm( sprintf( 'Delete all "%s" translations?', $lang ), $assoc_args );

		global $wpdb;

		$deleted = 0;

		foreach ( array(
			array( $wpdb->postmeta, 'meta_key', Keys::like_patterns( 'meta' ) ),
			array( $wpdb->termmeta, 'meta_key', Keys::like_patterns( 'meta' ) ),
			array( $wpdb->options, 'option_name', Keys::like_patterns( 'option' ) ),
		) as [ $table, $column, $patterns ] ) {
			foreach ( $patterns as $pattern ) {
				$rows = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT {$column} FROM {$table} WHERE {$column} LIKE %s", $pattern ) ); // phpcs:ignore WordPress.DB

				foreach ( $rows as $key ) {
					if ( Keys::matches( $key, array( $lang ) ) ) {
						$deleted += (int) $wpdb->delete( $table, array( $column => $key ) ); // phpcs:ignore WordPress.DB
					}
				}
			}
		}

		wp_cache_flush();
		\WP_CLI::success( sprintf( 'Deleted %d rows.', $deleted ) );
	}

	/** @return string[] */
	private static function csv( string $value ): array {
		return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
	}

	private static function count_posts( string $type ): int {
		return (int) ( wp_count_posts( $type )->publish ?? 0 );
	}

	private static function count_terms( string $taxonomy ): int {
		return taxonomy_exists( $taxonomy ) ? (int) wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) : 0;
	}

	private static function translated_posts( string $type, string $lang ): int {
		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE p.post_type = %s AND p.post_status = 'publish' AND m.meta_key IN (%s, %s) AND m.meta_value <> ''",
				$type,
				Translator::key( $lang, 'title' ),
				Translator::key( $lang, 'content' )
			)
		);
	}

	private static function translated_terms( string $taxonomy, string $lang ): int {
		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT tt.term_id) FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->termmeta} m ON m.term_id = tt.term_id WHERE tt.taxonomy = %s AND m.meta_key = %s AND m.meta_value <> ''",
				$taxonomy,
				Translator::key( $lang, 'name' )
			)
		);
	}
}
