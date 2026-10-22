<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'wl_mlt_settings' );

if ( ! is_array( $settings ) || empty( $settings['delete_data'] ) ) {
	return;
}

global $wpdb;

// Translations are _wl_mlt_*; ACF also keeps a reference row per field, named with one more underscore.
foreach ( array( $wpdb->esc_like( '_wl_mlt_' ) . '%', $wpdb->esc_like( '__wl_mlt_' ) . '%' ) as $like ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $like ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $like ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
}
// ACF options pages keep values as options_{name} and _options_{name}.
foreach ( array( 'options_', '_options_' ) as $prefix ) {
	foreach ( array( '_wl_mlt_', '__wl_mlt_' ) as $infix ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix . $infix ) . '%' ) );
	}
}
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'wl_mlt_' ) . '%' ) );
