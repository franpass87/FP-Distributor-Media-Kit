<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Core;

use FP\DistributorMediaKit\Admin\AssetManager;
use FP\DistributorMediaKit\Admin\BulkUploadPage;
use FP\DistributorMediaKit\Admin\DashboardWidget;
use FP\DistributorMediaKit\Admin\DownloadsLogPage;
use FP\DistributorMediaKit\Admin\NotifyUsersPage;
use FP\DistributorMediaKit\Admin\ReportsPage;
use FP\DistributorMediaKit\Admin\SettingsPage;
use FP\DistributorMediaKit\Admin\UserApprovalPage;
use FP\DistributorMediaKit\Admin\UsersListPage;
use FP\DistributorMediaKit\Cron\DailyDownloadReportCron;
use FP\DistributorMediaKit\Cron\PurgeDownloadsCron;
use FP\DistributorMediaKit\Download\BulkZipController;
use FP\DistributorMediaKit\Download\ProxyController;
use FP\DistributorMediaKit\Frontend\RestrictedContent;
use FP\DistributorMediaKit\Frontend\ShortcodeLogin;
use FP\DistributorMediaKit\Frontend\ShortcodeMediaKit;
use FP\DistributorMediaKit\Frontend\ShortcodeRegister;
use FP\DistributorMediaKit\Frontend\ShortcodeUiLang;
use FP\DistributorMediaKit\User\MailApprovalController;
use FP\DistributorMediaKit\User\RegistrationHandler;

/**
 * Bootstrap principale del plugin FP Distributor Media Kit.
 *
 * @see fp-dmk
 */
final class Plugin {

	private static ?self $instance = null;

	public function __construct() {
		// Singleton.
	}

	public static function instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Inizializza tutti i moduli del plugin.
	 */
	public function init(): void {
		load_plugin_textdomain( 'fp-dmk', false, dirname( FP_DMK_BASENAME ) . '/languages' );

		// Download proxy (prima di template_redirect per catch early)
		add_action( 'init', [ ProxyController::class, 'register_rewrite' ], 5 );
		add_filter( 'query_vars', [ ProxyController::class, 'add_query_vars' ] );
		add_action( 'template_redirect', [ ProxyController::class, 'serve_download' ], 1 );
		BulkZipController::init();

		// Admin
		if ( is_admin() ) {
			AssetManager::init();
			\FP\DistributorMediaKit\Admin\CptBanner::init();
			new UserApprovalPage();
			new UsersListPage();
			new ReportsPage();
			new SettingsPage();
			new NotifyUsersPage();
			new DownloadsLogPage();
			new BulkUploadPage();
			new DashboardWidget();
		}

		// Cron pulizia log e report giornaliero download
		PurgeDownloadsCron::init();
		DailyDownloadReportCron::init();

		// User registration (AJAX / POST) e approvazione da link email (frontend)
		RegistrationHandler::init();
		MailApprovalController::init();

		// Frontend shortcodes (default = italiano; suffissi _it / _en per pagine bilingue)
		add_shortcode( 'fp_dmk_register', [ ShortcodeRegister::class, 'render' ] );
		add_shortcode( 'fp_dmk_login', [ ShortcodeLogin::class, 'render' ] );
		add_shortcode( 'fp_dmk_media_kit', [ ShortcodeMediaKit::class, 'render' ] );
		add_shortcode(
			'fp_dmk_register_it',
			static function ( $atts = [] ): string {
				return ShortcodeRegister::render( ShortcodeUiLang::normalize_atts( $atts ) );
			}
		);
		add_shortcode(
			'fp_dmk_register_en',
			static function ( $atts = [] ): string {
				return ShortcodeUiLang::with_english_ui(
					static fn(): string => ShortcodeRegister::render( ShortcodeUiLang::normalize_atts( $atts ) )
				);
			}
		);
		add_shortcode(
			'fp_dmk_login_it',
			static function ( $atts = [] ): string {
				return ShortcodeLogin::render( ShortcodeUiLang::normalize_atts( $atts ) );
			}
		);
		add_shortcode(
			'fp_dmk_login_en',
			static function ( $atts = [] ): string {
				return ShortcodeUiLang::with_english_ui(
					static fn(): string => ShortcodeLogin::render( ShortcodeUiLang::normalize_atts( $atts ) )
				);
			}
		);
		add_shortcode(
			'fp_dmk_media_kit_it',
			static function ( $atts = [] ): string {
				return ShortcodeMediaKit::render( ShortcodeUiLang::normalize_atts( $atts ) );
			}
		);
		add_shortcode(
			'fp_dmk_media_kit_en',
			static function ( $atts = [] ): string {
				return ShortcodeUiLang::with_english_ui(
					static fn(): string => ShortcodeMediaKit::render( ShortcodeUiLang::normalize_atts( $atts ) )
				);
			}
		);

		// Restrict access to media kit page
		RestrictedContent::init();

		// Auto-notify on asset publish
		add_action( 'fp_dmk_asset_published', [ \FP\DistributorMediaKit\Email\NotificationService::class, 'maybe_send_on_publish' ] );
	}
}
