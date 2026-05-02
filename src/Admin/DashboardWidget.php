<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Admin;

use FP\DistributorMediaKit\Report\ReportService;

/**
 * Widget bacheca WordPress: ultimi download e link rapidi.
 *
 * @package FP\DistributorMediaKit\Admin
 */
final class DashboardWidget {

	public function __construct() {
		add_action( 'wp_dashboard_setup', [ $this, 'register' ] );
	}

	public function register(): void {
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'fp_dmk_dashboard_widget',
			__( 'FP Media Kit — attività recente', 'fp-dmk' ),
			[ $this, 'render' ],
			null,
			null,
			'side',
			'high'
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}
		$stats  = ReportService::get_general_stats();
		$recent = ReportService::get_recent_activity( 12 );
		$reports = admin_url( 'admin.php?page=fp-dmk-reports' );
		$logs    = admin_url( 'admin.php?page=fp-dmk-downloads' );
		?>
		<div class="fp-dmk-dash-widget">
			<p class="fp-dmk-dash-widget-stats">
				<strong><?php echo esc_html( number_format_i18n( $stats['total_downloads'] ) ); ?></strong>
				<?php esc_html_e( 'download totali', 'fp-dmk' ); ?>
				&nbsp;·&nbsp;
				<strong><?php echo esc_html( number_format_i18n( $stats['approved_users'] ) ); ?></strong>
				<?php esc_html_e( 'utenti approvati', 'fp-dmk' ); ?>
			</p>
			<?php if ( $recent === [] ) : ?>
				<p class="description"><?php esc_html_e( 'Nessun download registrato.', 'fp-dmk' ); ?></p>
			<?php else : ?>
				<ul class="fp-dmk-dash-widget-list">
					<?php foreach ( $recent as $row ) : ?>
						<li>
							<span class="fp-dmk-dash-widget-title"><?php echo esc_html( $row->asset_title ); ?></span>
							<span class="fp-dmk-dash-widget-meta">
								<?php echo esc_html( $row->display_name ); ?> · <?php echo esc_html( $row->downloaded_at ); ?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p class="fp-dmk-dash-widget-links">
				<a href="<?php echo esc_url( $reports ); ?>"><?php esc_html_e( 'Report', 'fp-dmk' ); ?></a>
				&nbsp;|&nbsp;
				<a href="<?php echo esc_url( $logs ); ?>"><?php esc_html_e( 'Log download', 'fp-dmk' ); ?></a>
			</p>
		</div>
		<style>
			.fp-dmk-dash-widget-stats { margin: 0 0 12px; font-size: 13px; }
			.fp-dmk-dash-widget-list { margin: 0 0 12px; padding-left: 18px; max-height: 220px; overflow-y: auto; }
			.fp-dmk-dash-widget-list li { margin-bottom: 8px; line-height: 1.35; }
			.fp-dmk-dash-widget-title { display: block; font-weight: 600; }
			.fp-dmk-dash-widget-meta { display: block; font-size: 12px; color: #646970; }
			.fp-dmk-dash-widget-links { margin: 0; font-size: 13px; }
		</style>
		<?php
	}
}
