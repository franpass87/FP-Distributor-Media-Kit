<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\User;

/**
 * Approvazione distributore da link email (token segreto, senza login in bacheca).
 *
 * Il link punta al frontend: wp-admin richiederebbe comunque l’autenticazione prima di eseguire la logica.
 *
 * @package FP\DistributorMediaKit\User
 */
final class MailApprovalController {

	public static function init(): void {
		add_action( 'template_redirect', [ self::class, 'maybe_handle' ], 1 );
	}

	/**
	 * Gestisce GET fp_dmk_mail_approve + user_id + key sul sito pubblico.
	 */
	public static function maybe_handle(): void {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! isset( $_GET['fp_dmk_mail_approve'], $_GET['user_id'], $_GET['key'] ) ) {
			return;
		}
		if ( sanitize_text_field( wp_unslash( (string) $_GET['fp_dmk_mail_approve'] ) ) !== '1' ) {
			return;
		}

		$user_id = absint( $_GET['user_id'] );
		$key     = sanitize_text_field( wp_unslash( (string) $_GET['key'] ) );

		if ( ! ApprovalService::validate_approval_email_token( $user_id, $key ) ) {
			wp_die(
				'<p>' . esc_html__( 'Link di approvazione non valido o già utilizzato. Non condividere questo link con altri.', 'fp-dmk' ) . '</p>',
				esc_html__( 'Approvazione non riuscita', 'fp-dmk' ),
				[ 'response' => 400 ]
			);
		}

		ApprovalService::set_approved( $user_id, true );

		do_action(
			'fp_tracking_event',
			'dmk_user_approved',
			[
				'user_id'       => $user_id,
				'operator_id'   => 0,
				'source_plugin' => 'fp-distributor-media-kit',
				'source'        => 'email_link',
			]
		);

		nocache_headers();

		$redirect = apply_filters( 'fp_dmk_mail_approval_success_redirect', '' );
		if ( is_string( $redirect ) && $redirect !== '' ) {
			wp_safe_redirect( $redirect );
			exit;
		}

		$message = '<p><strong>' . esc_html__( 'Distributore approvato con successo.', 'fp-dmk' ) . '</strong></p>';
		$message .= '<p>' . esc_html__( 'L\'utente può ora accedere al Media Kit con le proprie credenziali.', 'fp-dmk' ) . '</p>';
		$message .= '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Torna al sito', 'fp-dmk' ) . '</a></p>';

		wp_die(
			$message,
			esc_html__( 'Approvazione completata', 'fp-dmk' ),
			[ 'response' => 200 ]
		);
	}
}
