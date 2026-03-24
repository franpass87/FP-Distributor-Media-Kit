<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Cron;

use FP\DistributorMediaKit\Email\NotificationService;

/**
 * Cron giornaliero: invia report download del giorno precedente.
 *
 * @package FP\DistributorMediaKit\Cron
 */
final class DailyDownloadReportCron {

	public const HOOK = 'fp_dmk_daily_download_report';

	public static function init(): void {
		add_action( self::HOOK, [ self::class, 'run' ] );
		add_action( 'init', [ self::class, 'maybe_schedule' ] );
	}

	public static function maybe_schedule(): void {
		$opts = get_option( 'fp_dmk_settings', [] );
		$opts = is_array( $opts ) ? $opts : [];
		if ( empty( $opts['daily_download_report'] ) ) {
			wp_clear_scheduled_hook( self::HOOK );
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	public static function run(): void {
		try {
			NotificationService::maybe_send_daily_download_report();
		} catch ( \Throwable $e ) {
			error_log( '[FP-DMK] Daily download report cron failed: ' . $e->getMessage() );
		}
	}
}
