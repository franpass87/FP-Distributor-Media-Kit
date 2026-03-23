<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Admin;

use FP\DistributorMediaKit\Email\NotificationService;
use FP\DistributorMediaKit\User\ApprovalService;

/**
 * Pagina per invio email notifica a distributori approvati.
 *
 * @package FP\DistributorMediaKit\Admin
 */
final class NotifyUsersPage {

	public function __construct() {
		// Menu registrato da UserApprovalPage
		add_action( 'admin_init', [ $this, 'handle_send' ] );
	}

	public function handle_send(): void {
		if ( ! isset( $_POST['fp_dmk_send_notify'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}
		if ( ! isset( $_POST['fp_dmk_notify_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_dmk_notify_nonce'] ) ), 'fp_dmk_notify' ) ) {
			return;
		}
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : NotificationService::get_default_subject();
		$body = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : NotificationService::get_default_template();
		$result = NotificationService::send_to_all_approved( $subject, $body );
		wp_safe_redirect( add_query_arg( [
			'fp_dmk_sent' => $result['sent'],
			'fp_dmk_errors' => count( $result['errors'] ),
		], wp_get_referer() ?: admin_url( 'admin.php?page=fp-dmk-notify' ) ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}
		$users = ApprovalService::get_approved_users();
		$subject = get_option( 'fp_dmk_notify_subject', NotificationService::get_default_subject() );
		$body = get_option( 'fp_dmk_notify_template', NotificationService::get_default_template() );
		$sent = isset( $_GET['fp_dmk_sent'] ) ? absint( $_GET['fp_dmk_sent'] ) : 0;
		$errors_count = isset( $_GET['fp_dmk_errors'] ) ? absint( $_GET['fp_dmk_errors'] ) : 0;
		?>
		<div class="wrap fpdmk-admin-page">
			<h1 class="screen-reader-text"><?php esc_html_e( 'FP Media Kit', 'fp-dmk' ); ?></h1>
			<div class="fpdmk-page-header">
				<div class="fpdmk-page-header-content">
					<h2 class="fpdmk-page-header-title" aria-hidden="true"><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( 'Notifica utenti', 'fp-dmk' ); ?></h2>
					<p><?php esc_html_e( 'Invia un\'email a tutti i distributori approvati (es. per avvisarli di nuovi file).', 'fp-dmk' ); ?></p>
				</div>
				<span class="fpdmk-page-header-badge">v<?php echo esc_html( FP_DMK_VERSION ); ?></span>
			</div>

			<?php if ( $sent > 0 ) : ?>
				<div class="fpdmk-alert fpdmk-alert-success">
					<span class="dashicons dashicons-yes-alt"></span> <?php echo esc_html( sprintf( _n( 'Email inviata a %d destinatario.', 'Email inviate a %d destinatari.', $sent, 'fp-dmk' ), $sent ) ); ?>
				</div>
			<?php endif; ?>
			<?php if ( $errors_count > 0 ) : ?>
				<div class="fpdmk-alert fpdmk-alert-danger">
					<span class="dashicons dashicons-warning"></span> <?php echo esc_html( sprintf( _n( '%d invio fallito.', '%d invii falliti.', $errors_count, 'fp-dmk' ), $errors_count ) ); ?>
				</div>
			<?php endif; ?>

			<div class="fpdmk-card">
				<div class="fpdmk-card-header">
					<div class="fpdmk-card-header-left">
						<span class="dashicons dashicons-email"></span>
						<h2><?php esc_html_e( 'Invio email', 'fp-dmk' ); ?></h2>
					</div>
					<span class="fpdmk-badge fpdmk-badge-neutral"><?php echo count( $users ); ?> <?php esc_html_e( 'destinatari', 'fp-dmk' ); ?></span>
				</div>
				<div class="fpdmk-card-body">
					<?php if ( empty( $users ) ) : ?>
						<p class="description"><?php esc_html_e( 'Nessun distributore approvato. Approva prima gli utenti in attesa.', 'fp-dmk' ); ?></p>
					<?php else : ?>
						<form method="post" class="fpdmk-fields-grid">
							<?php wp_nonce_field( 'fp_dmk_notify', 'fp_dmk_notify_nonce' ); ?>
							<input type="hidden" name="fp_dmk_send_notify" value="1">
							<div class="fpdmk-field fpdmk-field-fw">
								<label for="fp_dmk_notify_subject"><?php esc_html_e( 'Oggetto', 'fp-dmk' ); ?></label>
								<input type="text" id="fp_dmk_notify_subject" name="subject" class="large-text" value="<?php echo esc_attr( $subject ); ?>">
							</div>
							<div class="fpdmk-field fpdmk-field-fw">
								<label for="fp_dmk_notify_body"><?php esc_html_e( 'Corpo email (HTML, placeholder: {name}, {email})', 'fp-dmk' ); ?></label>
								<textarea id="fp_dmk_notify_body" name="body" rows="12" class="large-text"><?php echo esc_textarea( $body ); ?></textarea>
							</div>
							<div class="fpdmk-field">
								<button type="submit" class="fpdmk-btn fpdmk-btn-primary"><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( 'Invia a tutti', 'fp-dmk' ); ?></button>
							</div>
						</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
