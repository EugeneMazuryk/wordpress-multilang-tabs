<?php
namespace WebLorem\MultilangTabs\Storage;

use WebLorem\MultilangTabs\Tools\Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Translations live in post meta, which core does not revision. Every revision gets a copy of them,
 * and restoring a revision puts that copy back, so "restore" brings back all languages at once.
 * Revisions made before the plugin was active carry no copy and leave translations alone on restore.
 */
final class Revisions {

	public const MARKER = '_wl_mlt_snapshot';

	public function register(): void {
		add_action( '_wp_put_post_revision', array( $this, 'snapshot' ), 10, 2 );
		add_action( 'wp_restore_post_revision', array( $this, 'restore' ), 10, 2 );

		// The REST API saves meta after the revision of the same request was made: refresh that revision.
		foreach ( \WebLorem\MultilangTabs\Core\Settings::get()['post_types'] as $type ) {
			add_action( 'rest_after_insert_' . $type, array( $this, 'refresh_latest' ), 20 );
		}
	}

	public function snapshot( $revision_id, $parent_id = 0 ): void {
		$parent_id = (int) ( $parent_id ?: wp_get_post_parent_id( (int) $revision_id ) );

		if ( ! $parent_id ) {
			return;
		}

		foreach ( array_keys( (array) get_metadata( 'post', (int) $revision_id ) ) as $key ) {
			if ( Keys::matches( (string) $key ) || self::MARKER === $key ) {
				delete_metadata( 'post', (int) $revision_id, $key );
			}
		}

		foreach ( self::translation_meta( $parent_id ) as $key => $values ) {
			foreach ( $values as $raw ) {
				add_metadata( 'post', (int) $revision_id, $key, wp_slash( maybe_unserialize( $raw ) ) );
			}
		}

		update_metadata( 'post', (int) $revision_id, self::MARKER, 1 );
	}

	public function restore( $post_id, $revision_id ): void {
		if ( ! get_metadata( 'post', (int) $revision_id, self::MARKER, true ) ) {
			return;
		}

		foreach ( array_keys( self::translation_meta( (int) $post_id ) ) as $key ) {
			delete_metadata( 'post', (int) $post_id, $key );
		}

		foreach ( self::translation_meta( (int) $revision_id ) as $key => $values ) {
			foreach ( $values as $raw ) {
				add_metadata( 'post', (int) $post_id, $key, wp_slash( maybe_unserialize( $raw ) ) );
			}
		}
	}

	public function refresh_latest( $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$revisions = wp_get_post_revisions( $post->ID, array( 'posts_per_page' => 1, 'check_enabled' => false ) );
		$latest    = reset( $revisions );

		if ( $latest ) {
			$this->snapshot( $latest->ID, $post->ID );
		}
	}

	/** @return array<string,string[]> meta_key => raw values, translation keys only */
	private static function translation_meta( int $post_id ): array {
		$out = array();

		foreach ( (array) get_metadata( 'post', $post_id ) as $key => $values ) {
			if ( Keys::matches( (string) $key ) ) {
				$out[ $key ] = (array) $values;
			}
		}

		return $out;
	}
}
