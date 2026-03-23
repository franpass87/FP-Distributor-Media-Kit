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

		$asset = get_post( $asset_id );
		do_action(
			'fp_tracking_event',
			'dmk_asset_downloaded',
			[
				'asset_id'      => $asset_id,
				'asset_title'   => $asset ? sanitize_text_field( (string) $asset->post_title ) : '',
				'user_id'       => $user_id,
				'source_plugin' => 'fp-distributor-media-kit',
			]
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

	/**
	 * Restituisce tutti i record di download per export.
	 *
	 * @return array<int, object{id: int, asset_id: int, asset_title: string, user_id: int, user_email: string, downloaded_at: string}>
	 */
	public static function get_all_for_export( int $limit = 10000 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'fp_dmk_downloads';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT d.id, d.asset_id, d.user_id, d.downloaded_at FROM {$table} d ORDER BY d.downloaded_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) ) {
			return [];
		}
		$result = [];
		foreach ( $rows as $r ) {
			$post = get_post( (int) $r['asset_id'] );
			$user = get_userdata( (int) $r['user_id'] );
			$result[] = (object) [
				'id'            => (int) $r['id'],
				'asset_id'      => (int) $r['asset_id'],
				'asset_title'   => $post ? $post->post_title : '',
				'user_id'       => (int) $r['user_id'],
				'user_email'    => $user ? $user->user_email : '',
				'downloaded_at' => $r['downloaded_at'],
			];
		}
		return $result;
	}

	/**
	 * Elimina record più vecchi di X giorni.
	 *
	 * @return int Numero di righe eliminate.
	 */
	public static function purge_older_than_days( int $days ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'fp_dmk_downloads';
		$date  = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE downloaded_at < %s",
				$date
			)
		);
	}
}
