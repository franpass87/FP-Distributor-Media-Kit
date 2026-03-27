<?php
/**
 * Pulizia alla disinstallazione di FP Distributor Media Kit.
 *
 * @package FP\DistributorMediaKit
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/fp-dmk-capabilities.php';

global $wpdb;

// Drop tabella download
$table = $wpdb->prefix . 'fp_dmk_downloads';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Rimuovi opzioni
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'fp_dmk_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Rimuovi capability plugin dall'Administrator e ruolo dedicato
$admin = get_role( 'administrator' );
if ( $admin ) {
	foreach ( fp_dmk_plugin_capability_names() as $cap ) {
		$admin->remove_cap( $cap );
	}
}
$manager = get_role( 'fp_dmk_manager' );
if ( $manager ) {
	foreach ( array_merge( fp_dmk_manager_role_base_capability_names(), fp_dmk_plugin_capability_names() ) as $cap ) {
		$manager->remove_cap( $cap );
	}
}
remove_role( 'fp_dmk_manager' );

// Opzionale: rimuovi user meta fp_dmk_approved (commentato per preservare dati)
// $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'fp_dmk_approved'" );
