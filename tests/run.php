<?php
/**
 * Integration tests. They run against a real WordPress and clean up after themselves.
 *
 *   wp eval-file web/app/plugins/weblorem-multilang-tabs/tests/run.php
 *   php  web/app/plugins/weblorem-multilang-tabs/tests/run.php          (loads wp-load.php itself)
 *
 * Requires the languages "uk" (default) and "en" to be configured.
 */

use WebLorem\MultilangTabs\Core\Context;
use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Core\Settings;
use WebLorem\MultilangTabs\Routing\Url;
use WebLorem\MultilangTabs\Seo\Alternates;
use WebLorem\MultilangTabs\Storage\Revisions;
use WebLorem\MultilangTabs\Storage\Translator;
use WebLorem\MultilangTabs\Tools\Exporter;
use WebLorem\MultilangTabs\Tools\Importer;
use WebLorem\MultilangTabs\Tools\Keys;

if ( ! defined( 'ABSPATH' ) ) {
	$_SERVER['HTTP_HOST'] = 'localhost';
	define( 'WP_USE_THEMES', false );
	$dir = __DIR__;

	while ( $dir !== dirname( $dir ) && ! file_exists( $dir . '/wp/wp-load.php' ) && ! file_exists( $dir . '/wp-load.php' ) ) {
		$dir = dirname( $dir );
	}

	require file_exists( $dir . '/wp/wp-load.php' ) ? $dir . '/wp/wp-load.php' : $dir . '/wp-load.php';
}

require_once ABSPATH . 'wp-admin/includes/template.php'; // add_settings_error() for Settings::sanitize

final class WlMltTest {
	public static int $passed = 0;
	public static array $failed = array();

	public static function ok( bool $cond, string $name ): void {
		if ( $cond ) {
			++self::$passed;
		} else {
			self::$failed[] = $name;
			echo "FAIL  $name\n";
		}
	}

	public static function same( $expected, $actual, string $name ): void {
		self::ok( $expected === $actual, $name . ( $expected === $actual ? '' : ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')' ) );
	}
}

function t_ok( bool $c, string $n ): void {
	WlMltTest::ok( $c, $n );
}

function t_same( $e, $a, string $n ): void {
	WlMltTest::same( $e, $a, $n );
}

// --- fixtures ---------------------------------------------------------------------------------------------
$created = array( 'posts' => array(), 'terms' => array() );
$options = array();

if ( ! Languages::exists( 'en' ) || 'uk' !== Languages::default_code() ) {
	echo "Languages uk (default) and en must be configured.\n";
	exit( 1 );
}

$parent = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'WLMLT parent', 'post_name' => 'wlmlt-parent', 'post_status' => 'publish', 'post_content' => 'p' ) );
$child  = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'WLMLT child', 'post_name' => 'wlmlt-child', 'post_status' => 'publish', 'post_parent' => $parent, 'post_content' => 'c' ) );
$post   = wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'WLMLT post', 'post_name' => 'wlmlt-post', 'post_status' => 'publish', 'post_content' => 'x' ) );
$term   = wp_insert_term( 'WLMLT term', 'category', array( 'slug' => 'wlmlt-term' ) );
$created['posts'] = array( $parent, $child, $post );
$created['terms'] = array( (int) $term['term_id'] );

$menu = wp_create_nav_menu( 'WLMLT menu' );
$item = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Home link', 'menu-item-url' => 'http://localhost/wlmlt-x/', 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ) );

$serialized = array( 'row' => array( 'a' => "EN \"quoted\" \\ back", 'n' => 3 ) );

try {
	// --- Url ----------------------------------------------------------------------------------------------
	t_same( array( '/about/', 'en' ), Url::split_path( '/en/about/' ), 'Url::split_path strips a language prefix' );
	t_same( array( '/enough/', null ), Url::split_path( '/enough/' ), 'Url::split_path ignores look-alike segments' );
	t_same( 'http://localhost/en/about/?a=1', Url::for_lang( 'http://localhost/about/?a=1', 'en' ), 'Url::for_lang adds the prefix' );
	t_same( 'http://localhost/about/', Url::for_lang( 'http://localhost/en/about/', 'uk' ), 'Url::for_lang drops the default prefix' );
	t_same( 'http://localhost/wp-admin/x', Url::for_lang( 'http://localhost/wp-admin/x', 'en' ), 'Url::for_lang leaves admin URLs alone' );
	t_same( 'http://other.test/a/', Url::for_lang( 'http://other.test/a/', 'en' ), 'Url::for_lang leaves foreign hosts alone' );

	// --- Translator / Keys / Settings ---------------------------------------------------------------------
	foreach ( array( '', ' ', '&nbsp;', '<p></p>', '<br>', "<p>\n</p>" ) as $empty ) {
		t_ok( Translator::is_empty( $empty ), 'is_empty: ' . json_encode( $empty ) );
	}
	t_ok( ! Translator::is_empty( '0' ), 'is_empty: "0" is a value' );
	t_ok( ! Translator::is_empty( '<p>x</p>' ), 'is_empty: text' );

	t_same( 'en', Keys::lang_of( '_wl_mlt_en_title' ), 'Keys: post meta' );
	t_same( 'en', Keys::lang_of( '__wl_mlt_en_field' ), 'Keys: ACF reference row' );
	t_same( 'en', Keys::lang_of( 'options__wl_mlt_en_x' ), 'Keys: ACF options value' );
	t_same( 'pt-br', Keys::lang_of( '_wl_mlt_pt-br_title' ), 'Keys: hyphenated code' );
	t_same( null, Keys::lang_of( 'wl_mlt_settings' ), 'Keys: settings option is not a translation' );
	t_same( null, Keys::lang_of( '_wl_mlt_snapshot' ), 'Keys: revision marker is not a translation' );
	t_ok( ! Keys::matches( '_wl_mlt_en_x', array( 'uk' ) ), 'Keys::matches honours the language filter' );

	$clean = Settings::sanitize( array( 'languages' => array( array( 'code' => 'uk', 'name' => 'U' ), array( 'code' => 'wp', 'name' => 'Reserved' ), array( 'code' => 'EN ', 'name' => 'E' ), array( 'code' => 'x', 'name' => 'Bad' ) ), 'default' => 'uk' ) );
	t_same( array( 'uk', 'en' ), array_column( $clean['languages'], 'code' ), 'Settings::sanitize drops reserved and malformed codes' );

	// --- Alternates / hreflang -----------------------------------------------------------------------------
	t_same( 'en-US', Alternates::hreflang( 'en' ), 'hreflang from locale' );
	t_ok( Alternates::post_translated( $post, 'uk' ), 'default language always exists' );
	t_ok( ! Alternates::post_translated( $post, 'en' ), 'untranslated post is not listed' );
	update_post_meta( $post, '_wl_mlt_en_title', 'EN post' );
	t_ok( Alternates::post_translated( $post, 'en' ), 'translated post is listed' );
	$alt = Alternates::for_post( get_post( $post ) );
	t_same( array( 'uk', 'en' ), array_keys( $alt ), 'for_post lists both languages' );
	t_ok( str_contains( $alt['en'], '/en/' ) && str_contains( $alt['en'], 'wlmlt-post' ), 'for_post keeps the original slug under the prefix' );
	t_same( basename( untrailingslashit( $alt['uk'] ) ), basename( untrailingslashit( $alt['en'] ) ), 'the slug is the same in every language' );
	t_same( 'uk', Context::lang(), 'in_lang restores the language' );

	// --- Export / import round trip -------------------------------------------------------------------------
	update_post_meta( $parent, '_wl_mlt_en_title', 'EN parent' );
	update_post_meta( $child, '_wl_mlt_en_title', 'EN child' );
	update_post_meta( $child, '_wl_mlt_en_acf_row', wp_slash( $serialized ) );
	update_post_meta( $child, '__wl_mlt_en_acf_row', 'field_abc' );
	update_term_meta( $term['term_id'], '_wl_mlt_en_name', 'EN term' );
	update_post_meta( $item, '_wl_mlt_en_title', 'EN home link' );
	update_option( '_wl_mlt_en_wlmlt_opt', 'EN option', false );
	$options[] = '_wl_mlt_en_wlmlt_opt';

	$export = Exporter::build();
	$types  = array_column( $export['posts'], 'type', 'path' );
	t_ok( isset( $types['wlmlt-parent/wlmlt-child'] ), 'export: hierarchical posts are identified by path' );
	t_ok( isset( $export['options']['_wl_mlt_en_wlmlt_opt'] ), 'export: options' );
	t_ok( (bool) array_filter( $export['terms'], static fn( $t ) => 'wlmlt-term' === $t['slug'] ), 'export: terms by slug' );
	t_ok( (bool) array_filter( $export['menu_items'], static fn( $m ) => str_ends_with( $m['key'], '|custom|custom|http://localhost/wlmlt-x/' ) ), 'export: menu items by menu + target' );
	t_same( array( 'uk', 'en' ), Exporter::build( array( 'langs' => array( 'en' ) ) )['languages'], 'export: language list is the site list' );
	t_ok( ! array_filter( Exporter::build( array( 'langs' => array( 'uk' ) ) )['posts'], static fn( $p ) => 'wlmlt-parent' === $p['path'] ), 'export: --lang filter' );

	$json   = json_decode( wp_json_encode( $export, JSON_UNESCAPED_UNICODE ), true );
	$before = get_post_meta( $child, '_wl_mlt_en_acf_row', true );

	// wipe, then restore
	foreach ( array( $parent, $child, $post ) as $id ) {
		foreach ( array_keys( (array) get_post_meta( $id ) ) as $k ) {
			if ( Keys::matches( $k ) ) {
				delete_post_meta( $id, $k );
			}
		}
	}
	delete_term_meta( $term['term_id'], '_wl_mlt_en_name' );
	delete_post_meta( $item, '_wl_mlt_en_title' );
	delete_option( '_wl_mlt_en_wlmlt_opt' );

	$dry = Importer::run( $json, array( 'dry_run' => true ) );
	t_ok( $dry['keys'] > 0, 'import dry run counts keys' );
	t_same( '', (string) get_post_meta( $parent, '_wl_mlt_en_title', true ), 'import dry run writes nothing' );

	$report = Importer::run( $json );
	t_same( 'EN parent', get_post_meta( $parent, '_wl_mlt_en_title', true ), 'import restores a post' );
	t_same( 'EN child', get_post_meta( $child, '_wl_mlt_en_title', true ), 'import restores a child page by path' );
	t_same( $before, get_post_meta( $child, '_wl_mlt_en_acf_row', true ), 'import round-trips serialized values byte for byte' );
	t_same( 'field_abc', get_post_meta( $child, '__wl_mlt_en_acf_row', true ), 'import restores ACF reference rows' );
	t_same( 'EN term', get_term_meta( $term['term_id'], '_wl_mlt_en_name', true ), 'import restores a term' );
	t_same( 'EN home link', get_post_meta( $item, '_wl_mlt_en_title', true ), 'import restores a menu item' );
	t_same( 'EN option', get_option( '_wl_mlt_en_wlmlt_opt' ), 'import restores an option' );
	t_same( array(), $report['missing'], 'import finds everything it exported' );

	$json2                = $json;
	$json2['posts'][]     = array( 'type' => 'page', 'path' => 'wlmlt-does-not-exist', 'meta' => array( '_wl_mlt_en_title' => 'x' ) );
	$json2['posts'][0]['meta']['_wl_mlt_zz_title'] = 'unknown language';
	$report2              = Importer::run( $json2 );
	t_same( array( 'page:wlmlt-does-not-exist' ), array_map( static fn( $m ) => substr( $m, 5 ), $report2['missing'] ), 'import reports missing objects' );
	t_same( array( 'zz' ), $report2['skipped_langs'], 'import reports unknown languages' );

	update_post_meta( $parent, '_wl_mlt_en_excerpt', 'stale' );
	Importer::run( $json, array( 'replace' => true ) );
	t_same( '', (string) get_post_meta( $parent, '_wl_mlt_en_excerpt', true ), 'import --replace removes translations that are not in the file' );
	t_same( 'EN parent', get_post_meta( $parent, '_wl_mlt_en_title', true ), 'import --replace keeps the imported ones' );

	try {
		Importer::run( array( 'format' => 99 ) );
		t_ok( false, 'import rejects an unknown format' );
	} catch ( InvalidArgumentException $e ) {
		t_ok( true, 'import rejects an unknown format' );
	}

	// --- Revisions ------------------------------------------------------------------------------------------
	update_post_meta( $parent, '_wl_mlt_en_title', 'EN v1' );
	wp_update_post( array( 'ID' => $parent, 'post_content' => 'content v1' ) );
	$revisions = wp_get_post_revisions( $parent );
	t_ok( (bool) $revisions, 'a revision was created' );
	$revision = reset( $revisions );
	t_same( 'EN v1', get_post_meta( $revision->ID, '_wl_mlt_en_title', true ), 'revision carries the translations' );
	update_post_meta( $parent, '_wl_mlt_en_title', 'EN v2' );
	update_post_meta( $parent, '_wl_mlt_en_excerpt', 'only in v2' );
	wp_restore_post_revision( $revision->ID );
	t_same( 'EN v1', get_post_meta( $parent, '_wl_mlt_en_title', true ), 'restoring a revision restores translations' );
	t_same( '', (string) get_post_meta( $parent, '_wl_mlt_en_excerpt', true ), 'restoring removes translations added later' );

	// --- Switcher ---------------------------------------------------------------------------------------------
	$html = wl_mlt_language_switcher( array( 'style' => 'dropdown' ), false );
	t_ok( str_contains( $html, '<details' ) && substr_count( $html, 'data-lang=' ) === 2, 'switcher renders both languages' );
	t_ok( str_contains( do_shortcode( '[wl_mlt_switcher show_flags="0"]' ), 'wl-mlt-switcher--list' ), 'shortcode renders' );
	t_ok( ! str_contains( do_shortcode( '[wl_mlt_switcher show_flags="0"]' ), 'wl-mlt-switcher__flag' ), 'shortcode honours show_flags=0' );
} finally {
	foreach ( $created['posts'] as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( $created['terms'] as $id ) {
		wp_delete_term( $id, 'category' );
	}
	wp_delete_post( $item, true );
	wp_delete_nav_menu( $menu );
	foreach ( $options as $name ) {
		delete_option( $name );
	}
}

echo sprintf( "%d passed, %d failed\n", WlMltTest::$passed, count( WlMltTest::$failed ) );
exit( WlMltTest::$failed ? 1 : 0 );
