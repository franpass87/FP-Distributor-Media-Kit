<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Core;

use FP\DistributorMediaKit\Admin\AssetManager;
use FP\DistributorMediaKit\Admin\DownloadsLogPage;
use FP\DistributorMediaKit\Admin\NotifyUsersPage;
use FP\DistributorMediaKit\Admin\ReportsPage;
use FP\DistributorMediaKit\Admin\SettingsPage;
use FP\DistributorMediaKit\Admin\UserApprovalPage;
use FP\DistributorMediaKit\Cron\PurgeDownloadsCron;
use FP\DistributorMediaKit\Download\ProxyController;
use FP\DistributorMediaKit\Frontend\RestrictedContent;
use FP\DistributorMediaKit\Frontend\ShortcodeLogin;
use FP\DistributorMediaKit\Frontend\ShortcodeMediaKit;
use FP\DistributorMediaKit\Frontend\ShortcodeRegister;
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

		// Admin
		if ( is_admin() ) {
			AssetManager::init();
			\FP\DistributorMediaKit\Admin\CptBanner::init();
			new UserApprovalPage();
			new ReportsPage();
			new SettingsPage();
			new NotifyUsersPage();
			new DownloadsLogPage();
		}

		// Cron pulizia log
		PurgeDownloadsCron::init();

		// User registration (AJAX / POST)
		RegistrationHandler::init();

		// Frontend shortcodes
		add_shortcode( 'fp_dmk_register', [ ShortcodeRegister::class, 'render' ] );
		add_shortcode( 'fp_dmk_login', [ ShortcodeLogin::class, 'render' ] );
		add_shortcode( 'fp_dmk_media_kit', [ ShortcodeMediaKit::class, 'render' ] );

		// Restrict access to media kit page
		RestrictedContent::init();

		// Auto-notify on asset publish
		add_action( 'fp_dmk_asset_published', [ \FP\DistributorMediaKit\Email\NotificationService::class, 'maybe_send_on_publish' ] );
	}
}
