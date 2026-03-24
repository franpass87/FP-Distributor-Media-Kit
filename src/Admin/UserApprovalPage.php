<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Admin;

use FP\DistributorMediaKit\User\ApprovalService;

/**
 * Pagina admin per approvare utenti in attesa.
 *
 * @package FP\DistributorMediaKit\Admin
 */
final class UserApprovalPage {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ], 5 );
		add_action( 'admin_init', [ $this, 'handle_approve' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_filter( 'admin_body_class', [ $this, 'admin_body_class' ] );
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'FP Media Kit', 'fp-dmk' ),
			__( 'FP Media Kit', 'fp-dmk' ),
			'manage_fp_dmk',
			'fp-dmk',
			[ $this, 'render_assets_redirect' ],
			'dashicons-media-archive',
			'56.13'
		);
		remove_submenu_page( 'fp-dmk', 'fp-dmk' );
		add_submenu_page(
			'fp-dmk',
			__( 'Categorie', 'fp-dmk' ),
			__( 'Categorie', 'fp-dmk' ),
			'manage_fp_dmk',
			'edit-tags.php?taxonomy=' . AssetManager::TAXONOMY . '&post_type=' . AssetManager::CPT,
			null
		);
		add_submenu_page(
			'fp-dmk',
			__( 'Utenti da approvare', 'fp-dmk' ),
			__( 'Utenti da approvare', 'fp-dmk' ),
			'manage_fp_dmk',
			'fp-dmk-approval',
			[ $this, 'render' ]
		);
		add_submenu_page(
			'fp-dmk',
			__( 'Notifica utenti', 'fp-dmk' ),
			__( 'Notifica utenti', 'fp-dmk' ),
			'manage_fp_dmk',
			'fp-dmk-notify',
			[ \FP\DistributorMediaKit\Admin\NotifyUsersPage::class, 'render' ]
		);
		add_submenu_page(
			'fp-dmk',
			__( 'Impostazioni', 'fp-dmk' ),
			__( 'Impostazioni', 'fp-dmk' ),
			'manage_fp_dmk',
			'fp-dmk-settings',
			[ \FP\DistributorMediaKit\Admin\SettingsPage::class, 'render' ]
		);
	}

	public function render_assets_redirect(): void {
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . AssetManager::CPT ) );
		exit;
	}

	public function handle_approve(): void {
		if ( ! isset( $_GET['fp_dmk_approve'] ) || ! isset( $_GET['user_id'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fp_dmk_approve_' . (int) $_GET['user_id'] ) ) {
			return;
		}
		$user_id = absint( $_GET['user_id'] );
		ApprovalService::set_approved( $user_id, true );

		do_action(
			'fp_tracking_event',
			'dmk_user_approved',
			[
				'user_id'       => $user_id,
				'operator_id'   => get_current_user_id(),
				'source_plugin' => 'fp-distributor-media-kit',
			]
		);

		wp_safe_redirect( add_query_arg( 'fp_dmk_approved', '1', remove_query_arg( [ 'fp_dmk_approve', 'user_id', '_wpnonce' ] ) ) );
		exit;
	}

	/**
	 * Aggiunge classe body per pagine del plugin (CSS scoped).
	 *
	 * @param string $classes Classi esistenti.
	 * @return string
	 */
	public function admin_body_class( string $classes ): string {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return $classes;
		}
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$is_dmk = ( $screen->post_type === AssetManager::CPT )
			|| ( isset( $screen->taxonomy ) && $screen->taxonomy === AssetManager::TAXONOMY )
			|| ( $page !== '' && strpos( $page, 'fp-dmk' ) === 0 );
		if ( $is_dmk ) {
			$classes .= ' fpdmk-admin-shell';
		}
		return $classes;
	}

	/**
	 * Enqueue CSS admin con pattern design system: strpos su hook/page + post_type/taxonomy.
	 */
	public function enqueue( string $hook ): void {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$screen = get_current_screen();
		$is_our_page = ( strpos( $hook, 'fp-dmk' ) !== false )
			|| ( $page !== '' && strpos( $page, 'fp-dmk' ) === 0 )
			|| ( $screen && $screen->post_type === AssetManager::CPT )
			|| ( $screen && isset( $screen->taxonomy ) && $screen->taxonomy === AssetManager::TAXONOMY );
		if ( ! $is_our_page ) {
			return;
		}
		wp_enqueue_style( 'fp-dmk-admin', FP_DMK_URL . 'assets/css/admin.css', [], FP_DMK_VERSION );
		if ( $screen && $screen->post_type === AssetManager::CPT ) {
			wp_enqueue_media();
		}
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}
		$users = ApprovalService::get_pending_users();
		?>
		<div class="wrap fpdmk-admin-page">
			<h1 class="screen-reader-text"><?php esc_html_e( 'FP Media Kit', 'fp-dmk' ); ?></h1>
			<div class="fpdmk-page-header">
				<div class="fpdmk-page-header-content">
					<h2 class="fpdmk-page-header-title" aria-hidden="true"><span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'Utenti da approvare', 'fp-dmk' ); ?></h2>
					<p><?php esc_html_e( 'Approva i distributori che hanno richiesto l\'accesso al Media Kit.', 'fp-dmk' ); ?></p>
				</div>
				<span class="fpdmk-page-header-badge">v<?php echo esc_html( FP_DMK_VERSION ); ?></span>
			</div>

			<?php if ( isset( $_GET['fp_dmk_approved'] ) && sanitize_text_field( wp_unslash( $_GET['fp_dmk_approved'] ) ) === '1' ) : ?>
				<div class="fpdmk-alert fpdmk-alert-success">
					<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Utente approvato con successo.', 'fp-dmk' ); ?>
				</div>
			<?php endif; ?>

			<div class="fpdmk-card">
				<div class="fpdmk-card-header">
					<div class="fpdmk-card-header-left">
						<span class="dashicons dashicons-admin-users"></span>
						<h2><?php esc_html_e( 'Richieste in attesa', 'fp-dmk' ); ?></h2>
					</div>
					<?php if ( ! empty( $users ) ) : ?>
						<span class="fpdmk-badge fpdmk-badge-warning"><?php echo count( $users ); ?></span>
					<?php endif; ?>
				</div>
				<div class="fpdmk-card-body">
					<?php if ( empty( $users ) ) : ?>
						<p class="description"><?php esc_html_e( 'Nessuna richiesta in attesa.', 'fp-dmk' ); ?></p>
					<?php else : ?>
						<table class="fpdmk-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Nome', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'Email', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'Registrato', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'Azioni', 'fp-dmk' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $users as $user ) : ?>
									<tr>
										<td><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></td>
										<td><?php echo esc_html( $user->user_email ); ?></td>
										<td><?php echo esc_html( get_date_from_gmt( $user->user_registered, 'd/m/Y H:i' ) ); ?></td>
										<td>
											<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'fp_dmk_approve' => '1', 'user_id' => $user->ID ], admin_url( 'admin.php?page=fp-dmk-approval' ) ), 'fp_dmk_approve_' . $user->ID ) ); ?>" class="fpdmk-btn fpdmk-btn-success"><?php esc_html_e( 'Approva', 'fp-dmk' ); ?></a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
