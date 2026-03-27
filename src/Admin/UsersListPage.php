<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Admin;

use FP\DistributorMediaKit\Report\ReportService;
use FP\DistributorMediaKit\User\ApprovalService;
use FP\DistributorMediaKit\User\AudienceService;

/**
 * Pagina admin lista utenti (distributori approvati e in attesa).
 *
 * @package FP\DistributorMediaKit\Admin
 */
final class UsersListPage {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ], 6 );
		add_action( 'admin_init', [ $this, 'handle_segment_save' ], 5 );
		add_action( 'admin_init', [ $this, 'handle_actions' ] );
	}

	/**
	 * Salva il tipo di accesso (segmento) da POST nella lista utenti.
	 */
	public function handle_segment_save(): void {
		if ( ! isset( $_POST['fp_dmk_save_user_segment'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}
		if ( ! isset( $_POST['fp_dmk_segment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_dmk_segment_nonce'] ) ), 'fp_dmk_user_segment' ) ) {
			return;
		}
		if ( ! AudienceService::is_audience_enabled() ) {
			return;
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$segment = isset( $_POST['fp_dmk_segment'] ) ? sanitize_key( wp_unslash( (string) $_POST['fp_dmk_segment'] ) ) : '';

		if ( $user_id <= 0 || ! metadata_exists( 'user', $user_id, ApprovalService::META_KEY ) ) {
			wp_safe_redirect( add_query_arg( 'fp_dmk_updated', 'segment_bad_user', admin_url( 'admin.php?page=fp-dmk-users' ) ) );
			exit;
		}

		if ( $segment !== '' && ! AudienceService::is_valid_segment_slug( $segment ) ) {
			wp_safe_redirect( add_query_arg( 'fp_dmk_updated', 'segment_invalid', admin_url( 'admin.php?page=fp-dmk-users' ) ) );
			exit;
		}

		if ( ! AudienceService::set_user_segment( $user_id, $segment ) ) {
			wp_safe_redirect( add_query_arg( 'fp_dmk_updated', 'segment_invalid', admin_url( 'admin.php?page=fp-dmk-users' ) ) );
			exit;
		}

		$redirect = admin_url( 'admin.php?page=fp-dmk-users' );
		$status   = isset( $_POST['fp_dmk_users_return_status'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['fp_dmk_users_return_status'] ) ) : '';
		if ( in_array( $status, [ 'all', 'approved', 'pending' ], true ) ) {
			$redirect = add_query_arg( 'status', $status, $redirect );
		}
		wp_safe_redirect( add_query_arg( 'fp_dmk_updated', 'segment_ok', $redirect ) );
		exit;
	}

	public function register_menu(): void {
		add_submenu_page(
			'fp-dmk',
			__( 'Lista utenti', 'fp-dmk' ),
			__( 'Lista utenti', 'fp-dmk' ),
			'manage_fp_dmk',
			'fp-dmk-users',
			[ $this, 'render' ]
		);
	}

	public function handle_actions(): void {
		if ( ! isset( $_GET['fp_dmk_user_action'] ) || ! isset( $_GET['user_id'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}

		$action  = sanitize_text_field( wp_unslash( $_GET['fp_dmk_user_action'] ) );
		$user_id = absint( $_GET['user_id'] );

		if ( ! in_array( $action, [ 'approve', 'revoke' ], true ) ) {
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'fp_dmk_user_' . $action . '_' . $user_id ) ) {
			return;
		}

		if ( $action === 'approve' ) {
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
			wp_safe_redirect( add_query_arg( 'fp_dmk_updated', 'approved', remove_query_arg( [ 'fp_dmk_user_action', 'user_id', '_wpnonce' ] ) ) );
		} else {
			ApprovalService::set_approved( $user_id, false );
			wp_safe_redirect( add_query_arg( 'fp_dmk_updated', 'revoked', remove_query_arg( [ 'fp_dmk_user_action', 'user_id', '_wpnonce' ] ) ) );
		}
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}

		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all';
		if ( ! in_array( $status_filter, [ 'all', 'approved', 'pending' ], true ) ) {
			$status_filter = 'all';
		}

		$users = ApprovalService::get_all_distributors( $status_filter, 'user_registered', 'DESC' );
		$user_ids = array_map( fn( $u ) => $u->ID, $users );
		$dl_stats = ReportService::get_download_stats_for_users( $user_ids );
		$audience_on = AudienceService::is_audience_enabled();

		$base_url = admin_url( 'admin.php?page=fp-dmk-users' );
		?>
		<div class="wrap fpdmk-admin-page">
			<h1 class="screen-reader-text"><?php esc_html_e( 'FP Media Kit', 'fp-dmk' ); ?></h1>
			<div class="fpdmk-page-header">
				<div class="fpdmk-page-header-content">
					<h2 class="fpdmk-page-header-title" aria-hidden="true"><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'Lista utenti', 'fp-dmk' ); ?></h2>
					<p><?php esc_html_e( 'Tutti i distributori registrati: approvati e in attesa di approvazione.', 'fp-dmk' ); ?></p>
				</div>
				<span class="fpdmk-page-header-badge">v<?php echo esc_html( FP_DMK_VERSION ); ?></span>
			</div>

			<?php
			$updated = isset( $_GET['fp_dmk_updated'] ) ? sanitize_text_field( wp_unslash( $_GET['fp_dmk_updated'] ) ) : '';
			if ( $updated === 'approved' ) :
				?>
				<div class="fpdmk-alert fpdmk-alert-success">
					<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Utente approvato.', 'fp-dmk' ); ?>
				</div>
			<?php elseif ( $updated === 'revoked' ) : ?>
				<div class="fpdmk-alert fpdmk-alert-info">
					<span class="dashicons dashicons-info"></span> <?php esc_html_e( 'Approvazione revocata.', 'fp-dmk' ); ?>
				</div>
			<?php elseif ( $updated === 'segment_ok' ) : ?>
				<div class="fpdmk-alert fpdmk-alert-success">
					<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Tipo di accesso aggiornato.', 'fp-dmk' ); ?>
				</div>
			<?php elseif ( in_array( $updated, [ 'segment_invalid', 'segment_bad_user' ], true ) ) : ?>
				<div class="fpdmk-alert fpdmk-alert-error">
					<span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Impossibile aggiornare il tipo di accesso.', 'fp-dmk' ); ?>
				</div>
			<?php endif; ?>

			<div class="fpdmk-card">
				<div class="fpdmk-card-header">
					<div class="fpdmk-card-header-left">
						<span class="dashicons dashicons-groups"></span>
						<h2><?php esc_html_e( 'Distributori', 'fp-dmk' ); ?></h2>
					</div>
					<div class="fpdmk-filter-tabs">
						<a href="<?php echo esc_url( $base_url ); ?>" class="fpdmk-tab <?php echo $status_filter === 'all' ? 'is-active' : ''; ?>"><?php esc_html_e( 'Tutti', 'fp-dmk' ); ?></a>
						<a href="<?php echo esc_url( add_query_arg( 'status', 'approved', $base_url ) ); ?>" class="fpdmk-tab <?php echo $status_filter === 'approved' ? 'is-active' : ''; ?>"><?php esc_html_e( 'Approvati', 'fp-dmk' ); ?></a>
						<a href="<?php echo esc_url( add_query_arg( 'status', 'pending', $base_url ) ); ?>" class="fpdmk-tab <?php echo $status_filter === 'pending' ? 'is-active' : ''; ?>"><?php esc_html_e( 'In attesa', 'fp-dmk' ); ?></a>
					</div>
				</div>
				<div class="fpdmk-card-body">
					<?php if ( empty( $users ) ) : ?>
						<p class="description">
							<?php
							if ( $status_filter === 'pending' ) {
								esc_html_e( 'Nessun utente in attesa di approvazione.', 'fp-dmk' );
							} elseif ( $status_filter === 'approved' ) {
								esc_html_e( 'Nessun utente approvato.', 'fp-dmk' );
							} else {
								esc_html_e( 'Nessun distributore registrato.', 'fp-dmk' );
							}
							?>
						</p>
					<?php else : ?>
						<table class="fpdmk-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Nome', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'Email', 'fp-dmk' ); ?></th>
									<?php if ( $audience_on ) : ?>
										<th><?php esc_html_e( 'Tipo di accesso', 'fp-dmk' ); ?></th>
									<?php endif; ?>
									<th><?php esc_html_e( 'Stato', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'Registrato', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'Download', 'fp-dmk' ); ?></th>
									<th><?php esc_html_e( 'Azioni', 'fp-dmk' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $users as $user ) : ?>
									<?php
									$is_approved = ApprovalService::is_approved( $user->ID );
									$stats = $dl_stats[ $user->ID ] ?? [ 'count' => 0, 'last' => null ];
									?>
									<tr>
										<td><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></td>
										<td><?php echo esc_html( $user->user_email ); ?></td>
										<?php if ( $audience_on ) : ?>
											<td>
												<form method="post" class="fpdmk-user-segment-form">
													<?php wp_nonce_field( 'fp_dmk_user_segment', 'fp_dmk_segment_nonce' ); ?>
													<input type="hidden" name="fp_dmk_save_user_segment" value="1">
													<input type="hidden" name="user_id" value="<?php echo (int) $user->ID; ?>">
													<input type="hidden" name="fp_dmk_users_return_status" value="<?php echo esc_attr( $status_filter ); ?>">
													<select name="fp_dmk_segment" class="fpdmk-select-regular">
														<option value=""><?php esc_html_e( '— Default (nessun limite da meta) —', 'fp-dmk' ); ?></option>
														<?php foreach ( AudienceService::get_segments() as $srow ) : ?>
															<option value="<?php echo esc_attr( $srow['slug'] ); ?>" <?php selected( AudienceService::get_user_segment_slug( $user->ID ), $srow['slug'] ); ?>><?php echo esc_html( $srow['label'] ); ?></option>
														<?php endforeach; ?>
													</select>
													<button type="submit" class="button button-small"><?php esc_html_e( 'Salva', 'fp-dmk' ); ?></button>
												</form>
											</td>
										<?php endif; ?>
										<td>
											<?php if ( $is_approved ) : ?>
												<span class="fpdmk-badge fpdmk-badge-success"><?php esc_html_e( 'Approvato', 'fp-dmk' ); ?></span>
											<?php else : ?>
												<span class="fpdmk-badge fpdmk-badge-warning"><?php esc_html_e( 'In attesa', 'fp-dmk' ); ?></span>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( get_date_from_gmt( $user->user_registered, 'd/m/Y H:i' ) ); ?></td>
										<td>
											<?php echo esc_html( (string) $stats['count'] ); ?>
											<?php if ( $stats['last'] ) : ?>
												<br><span class="fpdmk-muted"><?php echo esc_html( $stats['last'] ); ?></span>
											<?php endif; ?>
										</td>
										<td>
											<?php if ( $is_approved ) : ?>
												<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'fp_dmk_user_action' => 'revoke', 'user_id' => $user->ID ], $base_url ), 'fp_dmk_user_revoke_' . $user->ID ) ); ?>" class="fpdmk-btn fpdmk-btn-secondary fpdmk-btn-sm"><?php esc_html_e( 'Revoca', 'fp-dmk' ); ?></a>
											<?php else : ?>
												<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'fp_dmk_user_action' => 'approve', 'user_id' => $user->ID ], $base_url ), 'fp_dmk_user_approve_' . $user->ID ) ); ?>" class="fpdmk-btn fpdmk-btn-success fpdmk-btn-sm"><?php esc_html_e( 'Approva', 'fp-dmk' ); ?></a>
											<?php endif; ?>
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
