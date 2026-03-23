<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Admin;

use FP\DistributorMediaKit\Report\ReportService;

/**
 * Pagina admin Report: statistiche generali, attività per utente e per asset.
 *
 * @package FP\DistributorMediaKit\Admin
 */
final class ReportsPage {

	private const EXPORT_ACTION = 'fp_dmk_export_report';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ], 14 );
		add_action( 'admin_init', [ $this, 'handle_export' ] );
	}

	public function register_menu(): void {
		add_submenu_page(
			'fp-dmk',
			__( 'Report', 'fp-dmk' ),
			__( 'Report', 'fp-dmk' ),
			'manage_fp_dmk',
			'fp-dmk-reports',
			[ $this, 'render' ]
		);
	}

	public function handle_export(): void {
		if ( ! isset( $_GET[ self::EXPORT_ACTION ] ) || ! isset( $_GET['type'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fp_dmk_export_report' ) ) {
			return;
		}

		$type = sanitize_text_field( wp_unslash( $_GET['type'] ) );
		$filename = 'fp-dmk-report-' . $type . '-' . gmdate( 'Y-m-d-His' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		$out = fopen( 'php://output', 'w' );

		if ( $type === 'by_user' ) {
			fputcsv( $out, [ 'Utente', 'Email', 'N. download', 'Ultimo download', 'Asset scaricati' ], ';' );
			$rows = ReportService::get_downloads_by_user( 5000 );
			foreach ( $rows as $r ) {
				$assets = implode( ' | ', array_map( fn( $d ) => $d->asset_title, $r->downloads ) );
				fputcsv( $out, [ $r->display_name, $r->user_email, $r->download_count, $r->last_download ?? '', $assets ], ';' );
			}
		} elseif ( $type === 'by_asset' ) {
			fputcsv( $out, [ 'Asset', 'Categoria', 'N. download', 'Ultimo download', 'Utenti (email)' ], ';' );
			$rows = ReportService::get_downloads_by_asset( 5000 );
			foreach ( $rows as $r ) {
				$users = implode( ' | ', array_map( fn( $d ) => $d->user_email, $r->downloads ) );
				fputcsv( $out, [ $r->asset_title, $r->category, $r->download_count, $r->last_download ?? '', $users ], ';' );
			}
		}

		fclose( $out );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}

		$stats   = ReportService::get_general_stats();
		$top_assets = ReportService::get_top_assets( 10 );
		$top_users  = ReportService::get_top_users( 10 );
		$recent     = ReportService::get_recent_activity( 15 );
		$by_user    = ReportService::get_downloads_by_user( 50 );
		$by_asset   = ReportService::get_downloads_by_asset( 50 );

		$export_user_url = wp_nonce_url(
			add_query_arg( [ self::EXPORT_ACTION => '1', 'type' => 'by_user' ], admin_url( 'admin.php?page=fp-dmk-reports' ) ),
			'fp_dmk_export_report'
		);
		$export_asset_url = wp_nonce_url(
			add_query_arg( [ self::EXPORT_ACTION => '1', 'type' => 'by_asset' ], admin_url( 'admin.php?page=fp-dmk-reports' ) ),
			'fp_dmk_export_report'
		);
		?>
		<div class="wrap fpdmk-admin-page">
			<h1 class="screen-reader-text"><?php esc_html_e( 'FP Media Kit', 'fp-dmk' ); ?></h1>
			<div class="fpdmk-page-header">
				<div class="fpdmk-page-header-content">
					<h2 class="fpdmk-page-header-title" aria-hidden="true"><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Report', 'fp-dmk' ); ?></h2>
					<p><?php esc_html_e( 'Statistiche generali, attività degli utenti e download per asset.', 'fp-dmk' ); ?></p>
				</div>
				<span class="fpdmk-page-header-badge">v<?php echo esc_html( FP_DMK_VERSION ); ?></span>
			</div>

			<?php /* KPI cards */ ?>
			<div class="fpdmk-report-kpi-grid">
				<div class="fpdmk-report-kpi">
					<span class="fpdmk-report-kpi-value"><?php echo esc_html( (string) $stats['total_downloads'] ); ?></span>
					<span class="fpdmk-report-kpi-label"><?php esc_html_e( 'Totale download', 'fp-dmk' ); ?></span>
				</div>
				<div class="fpdmk-report-kpi">
					<span class="fpdmk-report-kpi-value"><?php echo esc_html( (string) $stats['unique_users'] ); ?></span>
					<span class="fpdmk-report-kpi-label"><?php esc_html_e( 'Utenti attivi', 'fp-dmk' ); ?></span>
				</div>
				<div class="fpdmk-report-kpi">
					<span class="fpdmk-report-kpi-value"><?php echo esc_html( (string) $stats['total_assets'] ); ?></span>
					<span class="fpdmk-report-kpi-label"><?php esc_html_e( 'Asset pubblicati', 'fp-dmk' ); ?></span>
				</div>
				<div class="fpdmk-report-kpi">
					<span class="fpdmk-report-kpi-value"><?php echo esc_html( (string) $stats['approved_users'] ); ?></span>
					<span class="fpdmk-report-kpi-label"><?php esc_html_e( 'Distributori approvati', 'fp-dmk' ); ?></span>
				</div>
				<div class="fpdmk-report-kpi">
					<span class="fpdmk-report-kpi-value"><?php echo esc_html( (string) $stats['pending_users'] ); ?></span>
					<span class="fpdmk-report-kpi-label"><?php esc_html_e( 'In attesa', 'fp-dmk' ); ?></span>
				</div>
			</div>

			<?php /* Attività recente */ ?>
			<div class="fpdmk-card">
				<div class="fpdmk-card-header">
					<div class="fpdmk-card-header-left">
						<span class="dashicons dashicons-backup"></span>
						<h2><?php esc_html_e( 'Attività recente', 'fp-dmk' ); ?></h2>
					</div>
				</div>
				<div class="fpdmk-card-body">
					<?php if ( empty( $recent ) ) : ?>
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
								<?php foreach ( $recent as $r ) : ?>
									<tr>
										<td><?php echo esc_html( $r->asset_title ); ?></td>
										<td><?php echo esc_html( $r->display_name . ' (' . $r->user_email . ')' ); ?></td>
										<td><?php echo esc_html( $r->downloaded_at ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>

			<div class="fpdmk-report-two-cols">
				<?php /* Top asset */ ?>
				<div class="fpdmk-card">
					<div class="fpdmk-card-header">
						<div class="fpdmk-card-header-left">
							<span class="dashicons dashicons-portfolio"></span>
							<h2><?php esc_html_e( 'Top 10 asset scaricati', 'fp-dmk' ); ?></h2>
						</div>
					</div>
					<div class="fpdmk-card-body">
						<?php if ( empty( $top_assets ) ) : ?>
							<p class="description"><?php esc_html_e( 'Nessun download.', 'fp-dmk' ); ?></p>
						<?php else : ?>
							<table class="fpdmk-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Asset', 'fp-dmk' ); ?></th>
										<th><?php esc_html_e( 'Categoria', 'fp-dmk' ); ?></th>
										<th><?php esc_html_e( 'Download', 'fp-dmk' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $top_assets as $a ) : ?>
										<tr>
											<td><?php echo esc_html( $a->asset_title ); ?></td>
											<td><?php echo esc_html( $a->category ); ?></td>
											<td><?php echo esc_html( (string) $a->download_count ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>

				<?php /* Top utenti */ ?>
				<div class="fpdmk-card">
					<div class="fpdmk-card-header">
						<div class="fpdmk-card-header-left">
							<span class="dashicons dashicons-groups"></span>
							<h2><?php esc_html_e( 'Top 10 utenti per download', 'fp-dmk' ); ?></h2>
						</div>
					</div>
					<div class="fpdmk-card-body">
						<?php if ( empty( $top_users ) ) : ?>
							<p class="description"><?php esc_html_e( 'Nessun download.', 'fp-dmk' ); ?></p>
						<?php else : ?>
							<table class="fpdmk-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Utente', 'fp-dmk' ); ?></th>
										<th><?php esc_html_e( 'Email', 'fp-dmk' ); ?></th>
										<th><?php esc_html_e( 'Download', 'fp-dmk' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $top_users as $u ) : ?>
										<tr title="<?php echo esc_attr( $u->assets ); ?>">
											<td><?php echo esc_html( $u->display_name ); ?></td>
											<td><?php echo esc_html( $u->user_email ); ?></td>
											<td><?php echo esc_html( (string) $u->download_count ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<?php /* Report per utente: cosa scarica ogni utente */ ?>
			<div class="fpdmk-card">
				<div class="fpdmk-card-header">
					<div class="fpdmk-card-header-left">
						<span class="dashicons dashicons-admin-users"></span>
						<h2><?php esc_html_e( 'Cosa scarica ogni utente', 'fp-dmk' ); ?></h2>
					</div>
					<a href="<?php echo esc_url( $export_user_url ); ?>" class="fpdmk-btn fpdmk-btn-secondary"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Esporta CSV', 'fp-dmk' ); ?></a>
				</div>
				<div class="fpdmk-card-body">
					<?php if ( empty( $by_user ) ) : ?>
						<p class="description"><?php esc_html_e( 'Nessun download registrato.', 'fp-dmk' ); ?></p>
					<?php else : ?>
						<table class="fpdmk-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Utente', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'Email', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'N. download', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'Ultimo download', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'Asset scaricati', 'fp-dmk' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $by_user as $u ) : ?>
									<tr>
										<td><?php echo esc_html( $u->display_name ); ?></td>
										<td><?php echo esc_html( $u->user_email ); ?></td>
										<td><?php echo esc_html( (string) $u->download_count ); ?></td>
										<td><?php echo esc_html( $u->last_download ?? '—' ); ?></td>
										<td class="fpdmk-cell-assets">
											<?php
											$asset_titles = array_map( fn( $d ) => $d->asset_title, $u->downloads );
											echo esc_html( implode( ', ', array_slice( $asset_titles, 0, 5 ) ) );
											if ( count( $asset_titles ) > 5 ) {
												echo ' <span class="fpdmk-more">+' . ( count( $asset_titles ) - 5 ) . '</span>';
											}
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="description"><?php esc_html_e( 'Mostrati i primi 50 utenti. Usa Esporta CSV per l\'elenco completo.', 'fp-dmk' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php /* Report per asset: chi scarica ogni asset */ ?>
			<div class="fpdmk-card">
				<div class="fpdmk-card-header">
					<div class="fpdmk-card-header-left">
						<span class="dashicons dashicons-media-document"></span>
						<h2><?php esc_html_e( 'Chi scarica ogni asset', 'fp-dmk' ); ?></h2>
					</div>
					<a href="<?php echo esc_url( $export_asset_url ); ?>" class="fpdmk-btn fpdmk-btn-secondary"><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Esporta CSV', 'fp-dmk' ); ?></a>
				</div>
				<div class="fpdmk-card-body">
					<?php if ( empty( $by_asset ) ) : ?>
						<p class="description"><?php esc_html_e( 'Nessun download registrato.', 'fp-dmk' ); ?></p>
					<?php else : ?>
						<table class="fpdmk-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Asset', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'Categoria', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'N. download', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'Ultimo download', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'Scaricato da', 'fp-dmk' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $by_asset as $a ) : ?>
									<tr>
										<td><?php echo esc_html( $a->asset_title ); ?></td>
										<td><?php echo esc_html( $a->category ); ?></td>
										<td><?php echo esc_html( (string) $a->download_count ); ?></td>
										<td><?php echo esc_html( $a->last_download ?? '—' ); ?></td>
										<td class="fpdmk-cell-assets">
											<?php
											$emails = array_map( fn( $d ) => $d->user_email, $a->downloads );
											echo esc_html( implode( ', ', array_slice( array_unique( $emails ), 0, 5 ) ) );
											if ( count( array_unique( $emails ) ) > 5 ) {
												echo ' <span class="fpdmk-more">+' . ( count( array_unique( $emails ) ) - 5 ) . '</span>';
											}
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="description"><?php esc_html_e( 'Mostrati i primi 50 asset. Usa Esporta CSV per l\'elenco completo.', 'fp-dmk' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
