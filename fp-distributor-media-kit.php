<?php
/**
 * Plugin Name:       FP Distributor Media Kit
 * Plugin URI:        https://github.com/franpass87/FP-Distributor-Media-Kit
 * Description:       Area riservata per distributori: registrazione, approvazione admin, download asset protetti e notifiche email.
 * Version:           1.4.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Francesco Passeri
 * Author URI:        https://francescopasseri.com
 * License:           Proprietary
 * Text Domain:       fp-dmk
 * GitHub Plugin URI: franpass87/FP-Distributor-Media-Kit
 * Primary Branch:    main
 */

defined( 'ABSPATH' ) || exit;

define( 'FP_DMK_VERSION', '1.4.0' );
define( 'FP_DMK_FILE', __FILE__ );
define( 'FP_DMK_DIR', plugin_dir_path( __FILE__ ) );
define( 'FP_DMK_URL', plugin_dir_url( __FILE__ ) );
define( 'FP_DMK_BASENAME', plugin_basename( __FILE__ ) );

if ( file_exists( FP_DMK_DIR . 'vendor/autoload.php' ) ) {
	require_once FP_DMK_DIR . 'vendor/autoload.php';
}

/**
 * Attivazione plugin: crea tabella downloads, capability, termini taxonomy.
 */
function fp_dmk_activate(): void {
	global $wpdb;
	$table = $wpdb->prefix . 'fp_dmk_downloads';
	$charset = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE IF NOT EXISTS {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		asset_id BIGINT UNSIGNED NOT NULL,
		user_id BIGINT UNSIGNED NOT NULL,
		downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY asset_id (asset_id),
		KEY user_id (user_id)
	) {$charset};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	$admin = get_role( 'administrator' );
	if ( $admin && ! $admin->has_cap( 'manage_fp_dmk' ) ) {
		$admin->add_cap( 'manage_fp_dmk' );
	}
}
register_activation_hook( __FILE__, 'fp_dmk_activate' );

/**
 * Disattivazione plugin.
 */
function fp_dmk_deactivate(): void {
	wp_clear_scheduled_hook( \FP\DistributorMediaKit\Cron\PurgeDownloadsCron::HOOK );
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->remove_cap( 'manage_fp_dmk' );
	}
}
register_deactivation_hook( __FILE__, 'fp_dmk_deactivate' );

add_action( 'plugins_loaded', static function (): void {
	\FP\DistributorMediaKit\Core\Plugin::instance()->init();
} );
