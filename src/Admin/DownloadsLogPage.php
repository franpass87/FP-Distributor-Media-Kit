<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Admin;

use FP\DistributorMediaKit\Download\TrackingService;

/**
 * Pagina admin log download con export CSV.
 *
 * @package FP\DistributorMediaKit\Admin
 */
final class DownloadsLogPage {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ], 15 );
		add_action( 'admin_init', [ $this, 'handle_export' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	public function register_menu(): void {
		add_submenu_page(
			'fp-dmk',
			__( 'Log download', 'fp-dmk' ),
			__( 'Log download', 'fp-dmk' ),
			'manage_fp_dmk',
			'fp-dmk-downloads',
			[ $this, 'render' ]
		);
	}

	public function handle_export(): void {
		if ( ! isset( $_GET['fp_dmk_export_csv'] ) || $_GET['fp_dmk_export_csv'] !== '1' ) {
			return;
		}
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fp_dmk_export_csv' ) ) {
			return;
		}
		$rows = TrackingService::get_all_for_export( 50000 );
		$filename = 'fp-dmk-downloads-' . gmdate( 'Y-m-d-His' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'ID', 'Asset ID', 'Asset', 'User ID', 'Email', 'Data download' ], ';' );
		foreach ( $rows as $r ) {
			fputcsv( $out, [
				$r->id,
				$r->asset_id,
				$r->asset_title,
				$r->user_id,
				$r->user_email,
				$r->downloaded_at,
			], ';' );
		}
		fclose( $out );
		exit;
	}

	public function enqueue( string $hook ): void {
		if ( strpos( $hook, 'fp-dmk-downloads' ) === false ) {
			return;
		}
		wp_enqueue_style( 'fp-dmk-admin', FP_DMK_URL . 'assets/css/admin.css', [], FP_DMK_VERSION );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}
		$rows = TrackingService::get_all_for_export( 500 );
		$export_url = wp_nonce_url(
			add_query_arg( [ 'fp_dmk_export_csv' => '1' ], admin_url( 'admin.php?page=fp-dmk-downloads' ) ),
			'fp_dmk_export_csv'
		);
		?>
		<div class="wrap fpdmk-admin-page">
			<h1 class="screen-reader-text"><?php esc_html_e( 'FP Media Kit', 'fp-dmk' ); ?></h1>
			<div class="fpdmk-page-header">
				<div class="fpdmk-page-header-content">
					<h2 class="fpdmk-page-header-title" aria-hidden="true"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Log download', 'fp-dmk' ); ?></h2>
					<p><?php esc_html_e( 'Visualizza ed esporta lo storico dei download.', 'fp-dmk' ); ?></p>
				</div>
				<span class="fpdmk-page-header-badge">v<?php echo esc_html( FP_DMK_VERSION ); ?></span>
			</div>

			<div class="fpdmk-card">
				<div class="fpdmk-card-header">
					<div class="fpdmk-card-header-left">
						<span class="dashicons dashicons-list-view"></span>
						<h2><?php esc_html_e( 'Ultimi download', 'fp-dmk' ); ?></h2>
					</div>
					<a href="<?php echo esc_url( $export_url ); ?>" class="fpdmk-btn fpdmk-btn-primary"><span class="dashicons dashicons-database-export"></span> <?php esc_html_e( 'Esporta CSV', 'fp-dmk' ); ?></a>
				</div>
				<div class="fpdmk-card-body">
					<?php if ( empty( $rows ) ) : ?>
						<p class="description"><?php esc_html_e( 'Nessun download registrato.', 'fp-dmk' ); ?></p>
					<?php else : ?>
						<table class="fpdmk-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Asset', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'Utente', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'Data', 'fp-dmk' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rows as $r ) : ?>
									<tr>
										<td><?php echo esc_html( $r->asset_title ?: '—' ); ?></td>
										<td><?php echo esc_html( $r->user_email ); ?></td>
										<td><?php echo esc_html( $r->downloaded_at ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="description"><?php esc_html_e( 'Mostrati gli ultimi 500. Usa Esporta CSV per l\'elenco completo.', 'fp-dmk' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
