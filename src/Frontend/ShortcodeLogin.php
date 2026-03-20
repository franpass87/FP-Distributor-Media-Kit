<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Frontend;

use FP\DistributorMediaKit\User\ApprovalService;

/**
 * Shortcode [fp_dmk_login] - Form login.
 *
 * @package FP\DistributorMediaKit\Frontend
 */
final class ShortcodeLogin {

	public static function render( array $atts ): string {
		wp_enqueue_style( 'fp-dmk-frontend', FP_DMK_URL . 'assets/css/frontend.css', [], FP_DMK_VERSION );

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$approved = ApprovalService::is_approved( $user_id );
			$media_kit_url = get_permalink( (int) get_option( 'fp_dmk_media_kit_page', 0 ) ) ?: home_url( '/' );
			if ( $approved ) {
				return '<div class="fpdmk-login-wrap"><p class="fpdmk-message fpdmk-message-success">' .
					esc_html__( 'Sei già collegato.', 'fp-dmk' ) . ' ' .
					'<a href="' . esc_url( $media_kit_url ) . '">' . esc_html__( 'Vai al Media Kit', 'fp-dmk' ) . '</a> | ' .
					'<a href="' . esc_url( wp_logout_url( $media_kit_url ) ) . '">' . esc_html__( 'Esci', 'fp-dmk' ) . '</a></p></div>';
			}
			return '<div class="fpdmk-login-wrap"><p class="fpdmk-message fpdmk-message-warning">' .
				esc_html__( 'Il tuo account è in attesa di approvazione.', 'fp-dmk' ) . ' ' .
				'<a href="' . esc_url( wp_logout_url( home_url() ) ) . '">' . esc_html__( 'Esci', 'fp-dmk' ) . '</a></p></div>';
		}

		$redirect = isset( $atts['redirect'] ) ? esc_url( $atts['redirect'] ) : '';
		if ( $redirect === '' ) {
			$redirect = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : ( get_permalink( (int) get_option( 'fp_dmk_media_kit_page', 0 ) ) ?: home_url( '/' ) );
		}

		$error = isset( $_GET['fp_dmk_login_error'] ) ? sanitize_text_field( wp_unslash( $_GET['fp_dmk_login_error'] ) ) : '';
		$messages = [
			'invalid'      => __( 'Email o password non corretti.', 'fp-dmk' ),
			'not_approved' => __( 'Il tuo account non è ancora stato approvato.', 'fp-dmk' ),
		];
		$error_msg = isset( $messages[ $error ] ) ? $messages[ $error ] : '';

		$html = '<div class="fpdmk-login-wrap">';
		if ( $error_msg ) {
			$html .= '<div class="fpdmk-message fpdmk-message-error">' . esc_html( $error_msg ) . '</div>';
		}
		$html .= '<form method="post" action="" class="fpdmk-form fpdmk-login-form">';
		$html .= wp_nonce_field( 'fp_dmk_login', 'fp_dmk_login_nonce', true, false );
		$html .= '<input type="hidden" name="fp_dmk_login" value="1">';
		$html .= '<input type="hidden" name="redirect_to" value="' . esc_attr( $redirect ) . '">';
		$html .= '<div class="fpdmk-field">';
		$html .= '<label for="fp_dmk_login_email">' . esc_html__( 'Email', 'fp-dmk' ) . '</label>';
		$html .= '<input type="email" id="fp_dmk_login_email" name="log" class="fpdmk-input" required placeholder="' . esc_attr__( 'email@esempio.it', 'fp-dmk' ) . '">';
		$html .= '</div>';
		$html .= '<div class="fpdmk-field">';
		$html .= '<label for="fp_dmk_login_password">' . esc_html__( 'Password', 'fp-dmk' ) . '</label>';
		$html .= '<input type="password" id="fp_dmk_login_password" name="pwd" class="fpdmk-input" required>';
		$html .= '</div>';
		$html .= '<div class="fpdmk-field fpdmk-field-submit">';
		$html .= '<button type="submit" class="fpdmk-btn fpdmk-btn-primary">' . esc_html__( 'Accedi', 'fp-dmk' ) . '</button>';
		$html .= '</div>';
		$html .= '</form>';
		$html .= '</div>';

		return $html;
	}
}
