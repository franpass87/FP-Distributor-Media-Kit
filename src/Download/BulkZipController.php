<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Download;

use FP\DistributorMediaKit\Admin\AssetManager;
use FP\DistributorMediaKit\User\ApprovalService;
use FP\DistributorMediaKit\User\AudienceService;

/**
 * Download multiplo come archivio ZIP (POST sicuro, max N asset).
 *
 * @package FP\DistributorMediaKit\Download
 */
final class BulkZipController {

	public const MAX_ASSETS = 25;

	public const NONCE_ACTION = 'fp_dmk_bulk_zip';

	public static function init(): void {
		add_action( 'template_redirect', [ self::class, 'maybe_serve_zip' ], 0 );
	}

	/**
	 * Estensione ZipArchive disponibile sul server.
	 */
	public static function is_supported(): bool {
		return class_exists( \ZipArchive::class );
	}

	public static function maybe_serve_zip(): void {
		if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
			return;
		}
		if ( ! isset( $_POST['fp_dmk_bulk_zip'] ) || (string) wp_unslash( $_POST['fp_dmk_bulk_zip'] ) !== '1' ) {
			return;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Richiesta non valida o scaduta.', 'fp-dmk' ), '', [ 'response' => 403 ] );
		}
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Devi effettuare l\'accesso.', 'fp-dmk' ), '', [ 'response' => 403 ] );
		}
		$user_id = get_current_user_id();
		if ( ! ApprovalService::is_approved( $user_id ) ) {
			wp_die( esc_html__( 'Il tuo account non è approvato.', 'fp-dmk' ), '', [ 'response' => 403 ] );
		}
		if ( ! self::is_supported() ) {
			wp_die( esc_html__( 'Download multiplo non disponibile su questo server.', 'fp-dmk' ), '', [ 'response' => 503 ] );
		}

		$raw_ids = isset( $_POST['asset_ids'] ) ? wp_unslash( $_POST['asset_ids'] ) : [];
		if ( ! is_array( $raw_ids ) ) {
			wp_die( esc_html__( 'Nessun asset selezionato.', 'fp-dmk' ), '', [ 'response' => 400 ] );
		}
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $raw_ids ),
					static fn( int $id ): bool => $id > 0
				)
			)
		);
		if ( $ids === [] ) {
			wp_die( esc_html__( 'Nessun asset selezionato.', 'fp-dmk' ), '', [ 'response' => 400 ] );
		}
		if ( count( $ids ) > self::MAX_ASSETS ) {
			wp_die(
				esc_html(
					sprintf(
						/* translators: %d: maximum number of files */
						__( 'Puoi selezionare al massimo %d file per volta.', 'fp-dmk' ),
						self::MAX_ASSETS
					)
				),
				'',
				[ 'response' => 400 ]
			);
		}

		$zip = new \ZipArchive();
		$tmp   = wp_tempnam( 'fp-dmk-bulk-' );
		if ( ! $tmp || $zip->open( $tmp, \ZipArchive::OVERWRITE ) !== true ) {
			if ( is_string( $tmp ) && file_exists( $tmp ) ) {
				unlink( $tmp );
			}
			wp_die( esc_html__( 'Impossibile creare l\'archivio temporaneo.', 'fp-dmk' ), '', [ 'response' => 500 ] );
		}

		$used_names = [];
		$added      = 0;
		foreach ( $ids as $asset_id ) {
			$post = get_post( $asset_id );
			if ( ! $post || $post->post_type !== AssetManager::CPT || $post->post_status !== 'publish' ) {
				continue;
			}
			if ( ! AudienceService::user_can_access_asset( $user_id, $asset_id ) ) {
				continue;
			}
			$file_id = (int) get_post_meta( $asset_id, AssetManager::META_FILE_ID, true );
			if ( $file_id <= 0 ) {
				continue;
			}
			$file_path = get_attached_file( $file_id );
			if ( ! $file_path || ! is_readable( $file_path ) ) {
				continue;
			}

			$base  = sanitize_file_name( $post->post_title );
			if ( $base === '' ) {
				$base = 'asset-' . $asset_id;
			}
			$ext   = pathinfo( $file_path, PATHINFO_EXTENSION );
			$entry = $ext !== '' ? $base . '.' . strtolower( $ext ) : $base;
			$entry = self::unique_zip_entry( $entry, $used_names );
			if ( $zip->addFile( $file_path, $entry ) ) {
				TrackingService::log( $asset_id, $user_id );
				++$added;
			}
		}

		$zip->close();

		if ( $added === 0 ) {
			if ( file_exists( $tmp ) ) {
				unlink( $tmp );
			}
			wp_die( esc_html__( 'Nessun file valido tra quelli selezionati.', 'fp-dmk' ), '', [ 'response' => 404 ] );
		}

		$size = filesize( $tmp );
		if ( ! $size ) {
			unlink( $tmp );
			wp_die( esc_html__( 'Archivio vuoto.', 'fp-dmk' ), '', [ 'response' => 500 ] );
		}

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="media-kit-' . gmdate( 'Y-m-d' ) . '.zip"' );
		header( 'Content-Length: ' . (string) $size );
		header( 'X-Robots-Tag: noindex' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_read_readfile
		readfile( $tmp );
		unlink( $tmp );
		exit;
	}

	/**
	 * Evita collisioni di nome dentro lo ZIP.
	 *
	 * @param string              $name       Nome file proposto.
	 * @param array<string, true> $used_names Nomi già usati (per riferimento).
	 */
	private static function unique_zip_entry( string $name, array &$used_names ): string {
		$orig = $name;
		$n     = 2;
		while ( isset( $used_names[ $name ] ) ) {
			$pi    = pathinfo( $orig, PATHINFO_FILENAME );
			$ext   = pathinfo( $orig, PATHINFO_EXTENSION );
			$name  = $ext !== '' ? $pi . '-' . $n . '.' . $ext : $orig . '-' . $n;
			++$n;
		}
		$used_names[ $name ] = true;

		return $name;
	}
}
