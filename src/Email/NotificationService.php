<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Email;

use FP\DistributorMediaKit\User\ApprovalService;

/**
 * Invio email wp_mail a distributori approvati.
 *
 * @package FP\DistributorMediaKit\Email
 */
final class NotificationService {

	/**
	 * Invia email a tutti i distributori approvati.
	 *
	 * @return array{success: bool, sent: int, errors: array<string>}
	 */
	public static function send_to_all_approved( string $subject, string $body ): array {
		$users = ApprovalService::get_approved_users();
		$sent = 0;
		$errors = [];
		$from_email = get_option( 'fp_dmk_email_from', get_bloginfo( 'admin_email' ) );
		$from_name = get_option( 'fp_dmk_email_from_name', get_bloginfo( 'name' ) );
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		];

		$subject = apply_filters( 'fp_dmk_email_subject', $subject );

		foreach ( $users as $user ) {
			if ( empty( $user->user_email ) ) {
				continue;
			}
			$body_personalized = str_replace( [ '{name}', '{email}' ], [ $user->display_name ?: $user->user_login, $user->user_email ], $body );
			$result = wp_mail( $user->user_email, $subject, $body_personalized, $headers );
			if ( $result ) {
				++$sent;
			} else {
				$errors[] = sprintf( __( 'Impossibile inviare a %s', 'fp-dmk' ), $user->user_email );
			}
		}

		return [
			'success' => empty( $errors ),
			'sent'    => $sent,
			'errors'  => $errors,
		];
	}

	/**
	 * Template di default per notifica nuovi file.
	 */
	public static function get_default_template(): string {
		return '<p>' . __( 'Ciao {name},', 'fp-dmk' ) . '</p>' .
			'<p>' . __( 'Sono stati aggiunti nuovi file al Media Kit. Accedi all\'area riservata per scaricarli.', 'fp-dmk' ) . '</p>' .
			'<p><a href="' . esc_url( get_permalink( (int) get_option( 'fp_dmk_media_kit_page', 0 ) ) ?: home_url() ) . '">' . __( 'Vai al Media Kit', 'fp-dmk' ) . '</a></p>' .
			'<p>' . __( 'Cordiali saluti', 'fp-dmk' ) . '</p>';
	}

	/**
	 * Subject di default.
	 */
	public static function get_default_subject(): string {
		return sprintf( __( '[%s] Nuovi file nel Media Kit', 'fp-dmk' ), get_bloginfo( 'name' ) );
	}

	/**
	 * Invio automatico quando un asset viene pubblicato (se abilitato in impostazioni).
	 */
	public static function maybe_send_on_publish( int $asset_id ): void {
		$opts = get_option( 'fp_dmk_settings', [] );
		if ( empty( $opts['auto_notify'] ) ) {
			return;
		}
		self::send_to_all_approved(
			self::get_default_subject(),
			self::get_default_template()
		);
	}
}
