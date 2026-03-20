<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Frontend;

use FP\DistributorMediaKit\User\ApprovalService;

/**
 * Redirect su pagine protette se utente non autorizzato.
 *
 * @package FP\DistributorMediaKit\Frontend
 */
final class RestrictedContent {

	public static function init(): void {
		add_action( 'template_redirect', [ self::class, 'maybe_redirect' ], 5 );
	}

	public static function maybe_redirect(): void {
		$media_kit_page = (int) get_option( 'fp_dmk_media_kit_page', 0 );
		if ( $media_kit_page <= 0 || ! is_singular( 'page' ) ) {
			return;
		}
		if ( (int) get_queried_object_id() !== $media_kit_page ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			$login_page = (int) get_option( 'fp_dmk_login_page', 0 );
			$redirect = $login_page > 0 ? get_permalink( $login_page ) : home_url( '/' );
			$redirect = add_query_arg( 'redirect_to', urlencode( get_permalink( $media_kit_page ) ), $redirect );
			wp_safe_redirect( $redirect );
			exit;
		}
		$user_id = get_current_user_id();
		if ( ! ApprovalService::is_approved( $user_id ) ) {
			$login_page = (int) get_option( 'fp_dmk_login_page', 0 );
			$redirect = $login_page > 0 ? get_permalink( $login_page ) : home_url( '/' );
			wp_safe_redirect( add_query_arg( 'fp_dmk_login_error', 'not_approved', $redirect ) );
			exit;
		}
	}
}
