<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Download;

use FP\DistributorMediaKit\Admin\AssetManager;
use FP\DistributorMediaKit\User\ApprovalService;
use FP\DistributorMediaKit\User\AudienceService;

/**
 * Endpoint proxy per download sicuro degli asset.
 *
 * @package FP\DistributorMediaKit\Download
 */
final class ProxyController {

	public static function register_rewrite(): void {
		// Non serve rewrite per URL con query string.
	}

	public static function add_query_vars( array $vars ): array {
		$vars[] = 'fp_dmk_download';
		$vars[] = 'fp_dmk_asset_id';
		return $vars;
	}

	public static function serve_download(): void {
		if ( ! isset( $_GET['fp_dmk_download'] ) || $_GET['fp_dmk_download'] !== '1' ) {
			return;
		}
		$asset_id = isset( $_GET['asset_id'] ) ? absint( $_GET['asset_id'] ) : 0;
		$nonce    = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( $asset_id <= 0 || $nonce === '' ) {
			wp_die( esc_html__( 'Parametri non validi.', 'fp-dmk' ), 403 );
		}
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Devi effettuare l\'accesso per scaricare.', 'fp-dmk' ), 403 );
		}
		$user_id = get_current_user_id();
		if ( ! ApprovalService::is_approved( $user_id ) ) {
			wp_die( esc_html__( 'Il tuo account non è approvato.', 'fp-dmk' ), 403 );
		}
		if ( ! wp_verify_nonce( $nonce, 'fp_dmk_download_' . $asset_id ) ) {
			wp_die( esc_html__( 'Link non valido o scaduto.', 'fp-dmk' ), 403 );
		}
		$post = get_post( $asset_id );
		if ( ! $post || $post->post_type !== AssetManager::CPT || $post->post_status !== 'publish' ) {
			wp_die( esc_html__( 'Asset non trovato.', 'fp-dmk' ), 404 );
		}
		if ( ! AudienceService::user_can_access_asset( $user_id, $asset_id ) ) {
			wp_die( esc_html__( 'Non hai accesso a questo file.', 'fp-dmk' ), 403 );
		}
		$file_id = (int) get_post_meta( $asset_id, AssetManager::META_FILE_ID, true );
		if ( $file_id <= 0 ) {
			wp_die( esc_html__( 'File non associato.', 'fp-dmk' ), 404 );
		}
		$file_path = get_attached_file( $file_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'File non trovato sul server.', 'fp-dmk' ), 404 );
		}

		TrackingService::log( $asset_id, $user_id );

		$filename = basename( $file_path );
		$mime    = wp_check_filetype( $filename )['type'] ?: 'application/octet-stream';

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		readfile( $file_path );
		exit;
	}
}
