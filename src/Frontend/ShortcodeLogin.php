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
		\FP\DistributorMediaKit\Frontend\AppearanceService::enqueue_with_custom_styles();

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$approved = ApprovalService::is_approved( $user_id );
			$media_kit_url = get_permalink( (int) get_option( 'fp_dmk_media_kit_page', 0 ) ) ?: home_url( '/' );
			if ( $approved ) {
				return '<div class="fpdmk-login-wrap fpdmk-ui"><p class="fpdmk-message fpdmk-message-success">' .
					esc_html__( 'Sei già collegato.', 'fp-dmk' ) . ' ' .
					'<a href="' . esc_url( $media_kit_url ) . '">' . esc_html__( 'Vai al Media Kit', 'fp-dmk' ) . '</a> | ' .
					'<a href="' . esc_url( wp_logout_url( $media_kit_url ) ) . '">' . esc_html__( 'Esci', 'fp-dmk' ) . '</a></p></div>';
			}
			return '<div class="fpdmk-login-wrap fpdmk-ui"><p class="fpdmk-message fpdmk-message-warning">' .
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

		$html = '<div class="fpdmk-login-wrap fpdmk-ui">';
		if ( $error_msg ) {
			$html .= '<div class="fpdmk-message fpdmk-message-error" role="alert">' . esc_html( $error_msg ) . '</div>';
		}
		$html .= '<form method="post" action="" class="fpdmk-form fpdmk-login-form">';
		$html .= wp_nonce_field( 'fp_dmk_login', 'fp_dmk_login_nonce', true, false );
		$html .= '<input type="hidden" name="fp_dmk_login" value="1">';
		$html .= '<input type="hidden" name="redirect_to" value="' . esc_attr( $redirect ) . '">';
		$html .= '<div class="fpdmk-field">';
		$html .= '<label for="fp_dmk_login_email">' . esc_html__( 'Email o username', 'fp-dmk' ) . '</label>';
		$html .= '<input type="text" id="fp_dmk_login_email" name="log" class="fpdmk-input" required placeholder="' . esc_attr__( 'email@esempio.it o username', 'fp-dmk' ) . '" autocomplete="username" aria-required="true">';
		$html .= '</div>';
		$html .= '<div class="fpdmk-field">';
		$html .= '<label for="fp_dmk_login_password">' . esc_html__( 'Password', 'fp-dmk' ) . '</label>';
		$html .= '<div class="fpdmk-password-wrap">';
		$html .= '<input type="password" id="fp_dmk_login_password" name="pwd" class="fpdmk-input fpdmk-password-input" required autocomplete="current-password" aria-required="true">';
		$html .= '<button type="button" class="fpdmk-btn-password-toggle" data-target="fp_dmk_login_password" aria-expanded="false" aria-label="' . esc_attr__( 'Mostra password', 'fp-dmk' ) . '">' . esc_html__( 'Mostra', 'fp-dmk' ) . '</button>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '<div class="fpdmk-field fpdmk-field-checkbox">';
		$html .= '<label class="fpdmk-checkbox-label"><input type="checkbox" name="rememberme" value="1" class="fpdmk-checkbox"> ' . esc_html__( 'Ricordami', 'fp-dmk' ) . '</label>';
		$html .= '</div>';
		$login_page_id = (int) get_option( 'fp_dmk_login_page', 0 );
		$lost_password_redirect = $login_page_id > 0 ? get_permalink( $login_page_id ) : home_url( '/' );
		$lost_password_url = wp_lostpassword_url( $lost_password_redirect );
		$html .= '<div class="fpdmk-field fpdmk-field-submit">';
		$html .= '<button type="submit" class="fpdmk-btn fpdmk-btn-primary">' . esc_html__( 'Accedi', 'fp-dmk' ) . '</button>';
		$html .= ' <a href="' . esc_url( $lost_password_url ) . '" class="fpdmk-link fpdmk-link-muted">' . esc_html__( 'Password dimenticata?', 'fp-dmk' ) . '</a>';
		$register_page_id = (int) get_option( 'fp_dmk_register_page', 0 );
		if ( $register_page_id > 0 ) {
			$register_url = get_permalink( $register_page_id );
			if ( $register_url ) {
				$html .= ' <a href="' . esc_url( $register_url ) . '" class="fpdmk-link fpdmk-link-muted">' . esc_html__( 'Non hai un account? Registrati', 'fp-dmk' ) . '</a>';
			}
		}
		$html .= '</div>';
		$html .= '<p class="fpdmk-hint fpdmk-hint-reset">' . esc_html__( 'Riceverai un\'email per reimpostare la password. Dopo il reset potrai accedere da questa pagina.', 'fp-dmk' ) . '</p>';
		$html .= '</form>';
		$html .= '<nav class="fpdmk-form-footer" aria-label="' . esc_attr__( 'Collegamenti utili', 'fp-dmk' ) . '">';
		$html .= '<a href="' . esc_url( home_url( '/' ) ) . '" class="fpdmk-link fpdmk-link-muted">' . esc_html__( 'Torna al sito', 'fp-dmk' ) . '</a>';
		$privacy_url = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
		if ( is_string( $privacy_url ) && $privacy_url !== '' ) {
			$html .= ' <span class="fpdmk-form-footer-sep">·</span> <a href="' . esc_url( $privacy_url ) . '" class="fpdmk-link fpdmk-link-muted">' . esc_html__( 'Privacy', 'fp-dmk' ) . '</a>';
		}
		$html .= '</nav>';
		$html .= '</div>';

		return $html;
	}
}
