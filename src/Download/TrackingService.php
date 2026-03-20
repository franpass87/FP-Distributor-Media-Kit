<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Download;

/**
 * Servizio tracking download per asset.
 *
 * @package FP\DistributorMediaKit\Download
 */
final class TrackingService {

	/**
	 * Registra un download nel database.
	 */
	public static function log( int $asset_id, int $user_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'fp_dmk_downloads';
		$wpdb->insert(
			$table,
			[
				'asset_id'      => $asset_id,
				'user_id'       => $user_id,
				'downloaded_at' => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s' ]
		);
	}

	/**
	 * Restituisce il numero di download per un asset.
	 */
	public static function get_count_for_asset( int $asset_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'fp_dmk_downloads';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE asset_id = %d",
				$asset_id
			)
		);
	}
}
