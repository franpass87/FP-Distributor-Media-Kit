<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Frontend;

use FP\DistributorMediaKit\User\AudienceService;

/**
 * Shortcode [fp_dmk_register] - Form registrazione.
 *
 * @package FP\DistributorMediaKit\Frontend
 */
final class ShortcodeRegister {

	public static function render( array $atts ): string {
		\FP\DistributorMediaKit\Frontend\AppearanceService::enqueue_with_custom_styles();

		if ( is_user_logged_in() ) {
			return '<p class="fpdmk-message fpdmk-message-info">' . esc_html__( 'Sei già registrato.', 'fp-dmk' ) . '</p>';
		}

		$redirect = isset( $atts['redirect'] ) ? esc_url( $atts['redirect'] ) : '';
		if ( $redirect === '' ) {
			$redirect = wp_get_referer() ?: home_url();
		}

		$error = isset( $_GET['fp_dmk_error'] ) ? sanitize_text_field( wp_unslash( $_GET['fp_dmk_error'] ) ) : '';
		$messages = [
			'invalid_email'    => __( 'Indirizzo email non valido.', 'fp-dmk' ),
			'email_exists'     => __( 'Questo indirizzo email è già registrato.', 'fp-dmk' ),
			'password_short'   => __( 'La password deve essere di almeno 8 caratteri.', 'fp-dmk' ),
			'password_no_upper'=> __( 'La password deve contenere almeno una lettera maiuscola.', 'fp-dmk' ),
			'password_no_lower'=> __( 'La password deve contenere almeno una lettera minuscola.', 'fp-dmk' ),
			'password_no_number'=> __( 'La password deve contenere almeno un numero.', 'fp-dmk' ),
			'create_failed'    => __( 'Si è verificato un errore. Riprova.', 'fp-dmk' ),
			'invalid_segment'  => __( 'Seleziona il tipo di accesso richiesto.', 'fp-dmk' ),
		];
		$error_msg = isset( $messages[ $error ] ) ? $messages[ $error ] : '';

		$success_raw = isset( $_GET['fp_dmk_registered'] ) ? sanitize_text_field( wp_unslash( $_GET['fp_dmk_registered'] ) ) : '';
		$success = ( $success_raw === '1' );

		$html = '<div class="fpdmk-register-wrap">';
		if ( $success ) {
			$html .= '<div class="fpdmk-message fpdmk-message-success">' . esc_html__( 'Registrazione completata. La tua richiesta è in attesa di approvazione da parte dell\'amministratore.', 'fp-dmk' ) . '</div>';
			return $html . '</div>';
		}
		if ( $error_msg ) {
			$html .= '<div class="fpdmk-message fpdmk-message-error" role="alert">' . esc_html( $error_msg ) . '</div>';
		}
		$html .= '<form method="post" action="" class="fpdmk-form fpdmk-register-form">';
		$html .= wp_nonce_field( 'fp_dmk_register', 'fp_dmk_register_nonce', true, false );
		$html .= '<input type="hidden" name="fp_dmk_register" value="1">';
		if ( $redirect ) {
			$html .= '<input type="hidden" name="redirect_to" value="' . esc_attr( $redirect ) . '">';
		}
		$html .= '<div class="fpdmk-field">';
		$html .= '<label for="fp_dmk_reg_name">' . esc_html__( 'Nome', 'fp-dmk' ) . '</label>';
		$html .= '<input type="text" id="fp_dmk_reg_name" name="name" class="fpdmk-input" placeholder="' . esc_attr__( 'Il tuo nome', 'fp-dmk' ) . '">';
		$html .= '</div>';
		$html .= '<div class="fpdmk-field">';
		$html .= '<label for="fp_dmk_reg_email">' . esc_html__( 'Email', 'fp-dmk' ) . ' <span class="required">*</span></label>';
		$html .= '<input type="email" id="fp_dmk_reg_email" name="email" class="fpdmk-input" required placeholder="' . esc_attr__( 'email@esempio.it', 'fp-dmk' ) . '">';
		$html .= '</div>';
		$password_hint = __( 'Minimo 8 caratteri, con maiuscola, minuscola e numero.', 'fp-dmk' );
		$html .= '<div class="fpdmk-field">';
		$html .= '<label for="fp_dmk_reg_password">' . esc_html__( 'Password', 'fp-dmk' ) . ' <span class="required">*</span></label>';
		$html .= '<input type="password" id="fp_dmk_reg_password" name="password" class="fpdmk-input" required minlength="8" placeholder="' . esc_attr__( 'Minimo 8 caratteri', 'fp-dmk' ) . '" aria-describedby="fp_dmk_reg_password_hint">';
		$html .= '<span id="fp_dmk_reg_password_hint" class="fpdmk-hint">' . esc_html( $password_hint ) . '</span>';
		$html .= '</div>';
		if ( AudienceService::is_audience_enabled() ) {
			$segments = AudienceService::get_segments();
			if ( $segments !== [] ) {
				$html .= '<div class="fpdmk-field">';
				$html .= '<label for="fp_dmk_reg_segment">' . esc_html__( 'Tipo di accesso', 'fp-dmk' ) . ' <span class="required">*</span></label>';
				$html .= '<select id="fp_dmk_reg_segment" name="fp_dmk_segment" class="fpdmk-select" required>';
				$html .= '<option value="">' . esc_html__( '— Seleziona —', 'fp-dmk' ) . '</option>';
				foreach ( $segments as $row ) {
					$html .= '<option value="' . esc_attr( $row['slug'] ) . '">' . esc_html( $row['label'] ) . '</option>';
				}
				$html .= '</select>';
				$html .= '<span class="fpdmk-hint">' . esc_html__( 'Il materiale visibile dipende dal tipo scelto e dalla configurazione del sito.', 'fp-dmk' ) . '</span>';
				$html .= '</div>';
			}
		}
		$html .= '<div class="fpdmk-field fpdmk-field-submit">';
		$html .= '<button type="submit" class="fpdmk-btn fpdmk-btn-primary">' . esc_html__( 'Registrati', 'fp-dmk' ) . '</button>';
		$login_page_id = (int) get_option( 'fp_dmk_login_page', 0 );
		if ( $login_page_id > 0 ) {
			$login_url = get_permalink( $login_page_id );
			if ( $login_url ) {
				$html .= ' <a href="' . esc_url( $login_url ) . '" class="fpdmk-link fpdmk-link-muted">' . esc_html__( 'Hai già un account? Accedi', 'fp-dmk' ) . '</a>';
			}
		}
		$html .= '</div>';
		$html .= '</form>';
		$html .= '</div>';

		return $html;
	}
}
