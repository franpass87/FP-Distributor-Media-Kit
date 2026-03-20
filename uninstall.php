<?php
/**
 * Pulizia alla disinstallazione di FP Distributor Media Kit.
 *
 * @package FP\DistributorMediaKit
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop tabella download
$table = $wpdb->prefix . 'fp_dmk_downloads';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Rimuovi opzioni
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'fp_dmk_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Rimuovi capability dagli admin
$admin = get_role( 'administrator' );
if ( $admin ) {
	$admin->remove_cap( 'manage_fp_dmk' );
}

// Opzionale: rimuovi user meta fp_dmk_approved (commentato per preservare dati)
// $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'fp_dmk_approved'" );
