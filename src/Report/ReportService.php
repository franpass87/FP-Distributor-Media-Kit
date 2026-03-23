<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Report;

use FP\DistributorMediaKit\Admin\AssetManager;
use FP\DistributorMediaKit\User\ApprovalService;

/**
 * Servizio report: statistiche generali, download per utente, download per asset.
 *
 * @package FP\DistributorMediaKit\Report
 */
final class ReportService {

	/**
	 * Statistiche generali per il report.
	 *
	 * @return array{total_downloads: int, unique_users: int, total_assets: int, approved_users: int, pending_users: int, assets_with_downloads: int}
	 */
	public static function get_general_stats(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'fp_dmk_downloads';

		$total_downloads = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$unique_users    = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$table}" );
		$unique_assets   = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT asset_id) FROM {$table}" );

		$approved = ApprovalService::get_approved_users();
		$pending  = ApprovalService::get_pending_users();

		$assets_published = wp_count_posts( AssetManager::CPT );
		$total_assets     = (int) ( $assets_published->publish ?? 0 );

		return [
			'total_downloads'      => $total_downloads,
			'unique_users'         => $unique_users,
			'total_assets'         => $total_assets,
			'assets_with_downloads'=> $unique_assets,
			'approved_users'       => count( $approved ),
			'pending_users'        => count( $pending ),
		];
	}

	/**
	 * Top N asset per numero di download.
	 *
	 * @return array<int, object{asset_id: int, asset_title: string, category: string, download_count: int, last_download: string|null}>
	 */
	public static function get_top_assets( int $limit = 10 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'fp_dmk_downloads';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT asset_id, COUNT(*) AS cnt, MAX(downloaded_at) AS last_dl
				FROM {$table} GROUP BY asset_id ORDER BY cnt DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$result = [];
		foreach ( $rows as $r ) {
			$post     = get_post( (int) $r['asset_id'] );
			$terms    = $post ? get_the_terms( $post->ID, AssetManager::TAXONOMY ) : false;
			$category = ( $terms && ! is_wp_error( $terms ) && ! empty( $terms ) )
				? implode( ', ', wp_list_pluck( $terms, 'name' ) )
				: '—';

			$result[] = (object) [
				'asset_id'       => (int) $r['asset_id'],
				'asset_title'    => $post ? $post->post_title : '—',
				'category'       => $category,
				'download_count' => (int) $r['cnt'],
				'last_download'  => $r['last_dl'] ?? null,
			];
		}
		return $result;
	}

	/**
	 * Top N utenti per numero di download.
	 *
	 * @return array<int, object{user_id: int, user_email: string, display_name: string, download_count: int, last_download: string|null, assets: string}>
	 */
	public static function get_top_users( int $limit = 10 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'fp_dmk_downloads';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, COUNT(*) AS cnt, MAX(downloaded_at) AS last_dl
				FROM {$table} GROUP BY user_id ORDER BY cnt DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$result = [];
		foreach ( $rows as $r ) {
			$user_id = (int) $r['user_id'];
			$user    = get_userdata( $user_id );
			$assets  = self::get_asset_names_for_user( $user_id );

			$result[] = (object) [
				'user_id'        => $user_id,
				'user_email'     => $user ? $user->user_email : '—',
				'display_name'   => $user ? ( $user->display_name ?: $user->user_login ) : '—',
				'download_count' => (int) $r['cnt'],
				'last_download'  => $r['last_dl'] ?? null,
				'assets'         => $assets,
			];
		}
		return $result;
	}

	/**
	 * Report per utente: tutti gli utenti che hanno scaricato con dettaglio asset.
	 *
	 * @return array<int, object{user_id: int, user_email: string, display_name: string, download_count: int, last_download: string|null, downloads: array}>
	 */
	public static function get_downloads_by_user( int $limit = 200 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'fp_dmk_downloads';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, COUNT(*) AS cnt, MAX(downloaded_at) AS last_dl
				FROM {$table} GROUP BY user_id ORDER BY cnt DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$result = [];
		foreach ( $rows as $r ) {
			$user_id = (int) $r['user_id'];
			$user    = get_userdata( $user_id );
			$dl_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT asset_id, downloaded_at FROM {$table} WHERE user_id = %d ORDER BY downloaded_at DESC",
					$user_id
				),
				ARRAY_A
			);

			$downloads = [];
			foreach ( is_array( $dl_rows ) ? $dl_rows : [] as $d ) {
				$post = get_post( (int) $d['asset_id'] );
				$downloads[] = (object) [
					'asset_id'      => (int) $d['asset_id'],
					'asset_title'   => $post ? $post->post_title : '—',
					'downloaded_at' => $d['downloaded_at'],
				];
			}

			$result[] = (object) [
				'user_id'        => $user_id,
				'user_email'     => $user ? $user->user_email : '—',
				'display_name'   => $user ? ( $user->display_name ?: $user->user_login ) : '—',
				'download_count' => (int) $r['cnt'],
				'last_download'  => $r['last_dl'] ?? null,
				'downloads'      => $downloads,
			];
		}
		return $result;
	}

	/**
	 * Report per asset: tutti gli asset con chi li ha scaricati.
	 *
	 * @return array<int, object{asset_id: int, asset_title: string, category: string, download_count: int, last_download: string|null, downloads: array}>
	 */
	public static function get_downloads_by_asset( int $limit = 200 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'fp_dmk_downloads';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT asset_id, COUNT(*) AS cnt, MAX(downloaded_at) AS last_dl
				FROM {$table} GROUP BY asset_id ORDER BY cnt DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$result = [];
		foreach ( $rows as $r ) {
			$asset_id = (int) $r['asset_id'];
			$post     = get_post( $asset_id );
			$terms    = $post ? get_the_terms( $post->ID, AssetManager::TAXONOMY ) : false;
			$category = ( $terms && ! is_wp_error( $terms ) && ! empty( $terms ) )
				? implode( ', ', wp_list_pluck( $terms, 'name' ) )
				: '—';

			$dl_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, downloaded_at FROM {$table} WHERE asset_id = %d ORDER BY downloaded_at DESC",
					$asset_id
				),
				ARRAY_A
			);

			$downloads = [];
			foreach ( is_array( $dl_rows ) ? $dl_rows : [] as $d ) {
				$user = get_userdata( (int) $d['user_id'] );
				$downloads[] = (object) [
					'user_id'       => (int) $d['user_id'],
					'user_email'    => $user ? $user->user_email : '—',
					'display_name'  => $user ? ( $user->display_name ?: $user->user_login ) : '—',
					'downloaded_at' => $d['downloaded_at'],
				];
			}

			$result[] = (object) [
				'asset_id'       => $asset_id,
				'asset_title'    => $post ? $post->post_title : '—',
				'category'       => $category,
				'download_count' => (int) $r['cnt'],
				'last_download'  => $r['last_dl'] ?? null,
				'downloads'      => $downloads,
			];
		}
		return $result;
	}

	/**
	 * Attività recente: ultimi N download.
	 *
	 * @return array<int, object{asset_title: string, user_email: string, display_name: string, downloaded_at: string}>
	 */
	public static function get_recent_activity( int $limit = 20 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'fp_dmk_downloads';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT asset_id, user_id, downloaded_at FROM {$table} ORDER BY downloaded_at DESC LIMIT %d",
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
				'asset_title'   => $post ? $post->post_title : '—',
				'user_email'    => $user ? $user->user_email : '—',
				'display_name'  => $user ? ( $user->display_name ?: $user->user_login ) : '—',
				'downloaded_at' => $r['downloaded_at'],
			];
		}
		return $result;
	}

	/**
	 * Elenco nomi asset scaricati da un utente (per tooltip/anteprima).
	 */
	private static function get_asset_names_for_user( int $user_id ): string {
		global $wpdb;
		$table = $wpdb->prefix . 'fp_dmk_downloads';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT asset_id FROM {$table} WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		$names = [];
		foreach ( is_array( $rows ) ? $rows : [] as $r ) {
			$post = get_post( (int) $r['asset_id'] );
			$names[] = $post ? $post->post_title : '—';
		}
		return implode( ', ', $names );
	}
}
