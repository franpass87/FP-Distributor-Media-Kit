<?php
/**
 * Plugin Name:       FP Distributor Media Kit
 * Plugin URI:        https://github.com/franpass87/FP-Distributor-Media-Kit
 * Description:       Area riservata per distributori: registrazione, approvazione admin, download asset protetti e notifiche email.
 * Version:           1.9.2
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

define( 'FP_DMK_VERSION', '1.9.2' );
define( 'FP_DMK_FILE', __FILE__ );
define( 'FP_DMK_DIR', plugin_dir_path( __FILE__ ) );
define( 'FP_DMK_URL', plugin_dir_url( __FILE__ ) );
define( 'FP_DMK_BASENAME', plugin_basename( __FILE__ ) );
define( 'FP_DMK_ROLE_MANAGER', 'fp_dmk_manager' );

require_once FP_DMK_DIR . 'fp-dmk-capabilities.php';

if ( file_exists( FP_DMK_DIR . 'vendor/autoload.php' ) ) {
	require_once FP_DMK_DIR . 'vendor/autoload.php';
}

/**
 * Carica traduzioni per etichette ruolo (activation / bootstrap anticipato).
 */
function fp_dmk_load_textdomain_for_caps(): void {
	load_plugin_textdomain( 'fp-dmk', false, dirname( FP_DMK_BASENAME ) . '/languages' );
}

/**
 * Assegna capability plugin all'Administrator, crea/aggiorna il ruolo Gestore Media Kit.
 *
 * Copre installazioni o aggiornamenti senza nuova esecuzione dell'hook di attivazione
 * (es. deploy via Git/updater).
 */
function fp_dmk_ensure_roles_and_caps(): void {
	if ( ! function_exists( 'get_role' ) ) {
		return;
	}
	fp_dmk_load_textdomain_for_caps();
	$caps = fp_dmk_plugin_capability_names();
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( $caps as $cap ) {
			if ( ! $admin->has_cap( $cap ) ) {
				$admin->add_cap( $cap );
			}
		}
	}
	$role_key = FP_DMK_ROLE_MANAGER;
	$manager  = get_role( $role_key );
	$base     = fp_dmk_manager_role_base_capability_names();
	$all_mgr  = array_merge( $base, $caps );
	if ( ! $manager ) {
		$caps_map = array_fill_keys( $all_mgr, true );
		add_role(
			$role_key,
			__( 'FP Media Kit Manager', 'fp-dmk' ),
			$caps_map
		);
	} else {
		foreach ( $all_mgr as $cap ) {
			if ( ! $manager->has_cap( $cap ) ) {
				$manager->add_cap( $cap );
			}
		}
	}
}

/**
 * Rimuove le capability del plugin da Administrator e dal ruolo Gestore (disattivazione).
 */
function fp_dmk_revoke_plugin_capabilities_from_roles(): void {
	if ( ! function_exists( 'get_role' ) ) {
		return;
	}
	$caps = fp_dmk_plugin_capability_names();
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( $caps as $cap ) {
			$admin->remove_cap( $cap );
		}
	}
	$manager = get_role( FP_DMK_ROLE_MANAGER );
	if ( $manager ) {
		foreach ( array_merge( fp_dmk_manager_role_base_capability_names(), $caps ) as $cap ) {
			$manager->remove_cap( $cap );
		}
	}
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

	fp_dmk_ensure_roles_and_caps();
}
register_activation_hook( __FILE__, 'fp_dmk_activate' );

add_action( 'plugins_loaded', 'fp_dmk_ensure_roles_and_caps', 5 );

/**
 * Disattivazione plugin.
 */
function fp_dmk_deactivate(): void {
	wp_clear_scheduled_hook( \FP\DistributorMediaKit\Cron\PurgeDownloadsCron::HOOK );
	fp_dmk_revoke_plugin_capabilities_from_roles();
}
register_deactivation_hook( __FILE__, 'fp_dmk_deactivate' );

add_action( 'plugins_loaded', static function (): void {
	\FP\DistributorMediaKit\Core\Plugin::instance()->init();
} );
