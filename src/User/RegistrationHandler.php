<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\User;

use FP\DistributorMediaKit\Email\NotificationService;

/**
 * Gestione form registrazione frontend.
 *
 * @package FP\DistributorMediaKit\User
 */
final class RegistrationHandler {

	public static function init(): void {
		add_action( 'init', [ self::class, 'handle_register_post' ], 20 );
		add_action( 'init', [ self::class, 'handle_login_post' ], 20 );
	}

	public static function handle_login_post(): void {
		if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' || ! isset( $_POST['fp_dmk_login'] ) ) {
			return;
		}
		if ( ! isset( $_POST['fp_dmk_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_dmk_login_nonce'] ) ), 'fp_dmk_login' ) ) {
			return;
		}
		$log = isset( $_POST['log'] ) ? sanitize_user( wp_unslash( $_POST['log'] ), true ) : '';
		$pwd = isset( $_POST['pwd'] ) ? $_POST['pwd'] : '';
		$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );
		if ( $log === '' || $pwd === '' ) {
			do_action(
				'fp_tracking_event',
				'dmk_login_failed',
				[
					'reason'        => 'invalid_credentials',
					'source_plugin' => 'fp-distributor-media-kit',
				]
			);
			wp_safe_redirect( add_query_arg( 'fp_dmk_login_error', 'invalid', $redirect ) );
			exit;
		}
		$user = wp_signon(
			[
				'user_login'    => $log,
				'user_password' => $pwd,
				'remember'      => ! empty( $_POST['rememberme'] ),
			],
			is_ssl()
		);
		if ( is_wp_error( $user ) ) {
			do_action(
				'fp_tracking_event',
				'dmk_login_failed',
				[
					'reason'        => 'invalid_credentials',
					'source_plugin' => 'fp-distributor-media-kit',
				]
			);
			wp_safe_redirect( add_query_arg( 'fp_dmk_login_error', 'invalid', wp_get_referer() ?: $redirect ) );
			exit;
		}
		if ( ! ApprovalService::is_approved( $user->ID ) ) {
			do_action(
				'fp_tracking_event',
				'dmk_login_blocked_not_approved',
				[
					'user_id'       => (int) $user->ID,
					'source_plugin' => 'fp-distributor-media-kit',
				]
			);
			wp_logout();
			wp_safe_redirect( add_query_arg( 'fp_dmk_login_error', 'not_approved', wp_get_referer() ?: $redirect ) );
			exit;
		}

		do_action(
			'fp_tracking_event',
			'dmk_login_success',
			[
				'user_id'       => (int) $user->ID,
				'source_plugin' => 'fp-distributor-media-kit',
			]
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	public static function handle_register_post(): void {
		if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' || ! isset( $_POST['fp_dmk_register'] ) ) {
			return;
		}
		if ( ! isset( $_POST['fp_dmk_register_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_dmk_register_nonce'] ) ), 'fp_dmk_register' ) ) {
			return;
		}

		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password = isset( $_POST['password'] ) ? $_POST['password'] : '';
		$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		if ( ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( 'fp_dmk_error', 'invalid_email', wp_get_referer() ?: home_url() ) );
			exit;
		}
		if ( username_exists( $email ) || email_exists( $email ) ) {
			wp_safe_redirect( add_query_arg( 'fp_dmk_error', 'email_exists', wp_get_referer() ?: home_url() ) );
			exit;
		}
		$pwd_error = self::validate_password( $password );
		if ( $pwd_error !== null ) {
			wp_safe_redirect( add_query_arg( 'fp_dmk_error', $pwd_error, wp_get_referer() ?: home_url() ) );
			exit;
		}

		$segment = isset( $_POST['fp_dmk_segment'] ) ? sanitize_key( wp_unslash( $_POST['fp_dmk_segment'] ) ) : '';
		if ( AudienceService::is_audience_enabled() ) {
			if ( $segment === '' || ! AudienceService::is_valid_segment_slug( $segment ) ) {
				wp_safe_redirect( add_query_arg( 'fp_dmk_error', 'invalid_segment', wp_get_referer() ?: home_url() ) );
				exit;
			}
		} else {
			$segment = '';
		}

		$user_id = wp_create_user( $email, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			wp_safe_redirect( add_query_arg( 'fp_dmk_error', 'create_failed', wp_get_referer() ?: home_url() ) );
			exit;
		}

		if ( $name !== '' ) {
			wp_update_user( [
				'ID'           => $user_id,
				'display_name' => $name,
				'first_name'   => $name,
			] );
		}

		ApprovalService::set_approved( $user_id, false );
		if ( $segment !== '' ) {
			AudienceService::set_user_segment( $user_id, $segment );
		}

		$settings = get_option( 'fp_dmk_settings', [] );
		if ( is_array( $settings ) && ! empty( $settings['notify_pending_registration'] ) ) {
			$token = ApprovalService::issue_approval_email_token( $user_id );
			NotificationService::send_pending_registration_to_admin( $user_id, $token );
		}

		do_action( 'fp_dmk_distributor_pending_registered', $user_id );

		do_action(
			'fp_tracking_event',
			'dmk_registration_submitted',
			[
				'user_id'       => (int) $user_id,
				'segment'       => $segment !== '' ? $segment : null,
				'source_plugin' => 'fp-distributor-media-kit',
			]
		);

		$redirect = wp_get_referer() ?: home_url( '/' );
		wp_safe_redirect( add_query_arg( 'fp_dmk_registered', '1', $redirect ) );
		exit;
	}

	/**
	 * Valida password: min 8 caratteri, maiuscola, minuscola, numero.
	 *
	 * @return string|null Codice errore o null se valida.
	 */
	private static function validate_password( string $password ): ?string {
		if ( strlen( $password ) < 8 ) {
			return 'password_short';
		}
		if ( ! preg_match( '/[A-Z]/', $password ) ) {
			return 'password_no_upper';
		}
		if ( ! preg_match( '/[a-z]/', $password ) ) {
			return 'password_no_lower';
		}
		if ( ! preg_match( '/[0-9]/', $password ) ) {
			return 'password_no_number';
		}
		return null;
	}
}
