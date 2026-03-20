<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Cron;

use FP\DistributorMediaKit\Download\TrackingService;

/**
 * Cron giornaliero per pulizia log download.
 *
 * @package FP\DistributorMediaKit\Cron
 */
final class PurgeDownloadsCron {

	public const HOOK = 'fp_dmk_purge_downloads';

	public static function init(): void {
		add_action( self::HOOK, [ self::class, 'run' ] );
		add_action( 'init', [ self::class, 'maybe_schedule' ] );
	}

	public static function maybe_schedule(): void {
		$days = (int) get_option( 'fp_dmk_purge_days', 0 );
		if ( $days <= 0 ) {
			wp_clear_scheduled_hook( self::HOOK );
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::HOOK );
		}
	}

	public static function run(): void {
		$days = (int) get_option( 'fp_dmk_purge_days', 0 );
		if ( $days <= 0 ) {
			return;
		}
		TrackingService::purge_older_than_days( $days );
	}
}
