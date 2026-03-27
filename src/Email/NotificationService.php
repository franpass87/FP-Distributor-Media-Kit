<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Email;

use DateTimeImmutable;
use FP\DistributorMediaKit\Report\ReportService;
use FP\DistributorMediaKit\User\ApprovalService;
use FP\DistributorMediaKit\User\AudienceService;

/**
 * Invio email wp_mail a distributori approvati e notifiche amministratore.
 *
 * @package FP\DistributorMediaKit\Email
 */
final class NotificationService {

	/**
	 * Wrapper FP Mail SMTP per HTML frammento (salta documenti completi).
	 */
	private static function maybe_fp_mail_brand_html( string $html ): string {
		if ( ! function_exists( 'fp_fpmail_brand_html' ) || $html === '' ) {
			return $html;
		}
		if ( preg_match( '/<\s*!DOCTYPE/i', $html ) || preg_match( '/<\s*html[\s>]/i', $html ) ) {
			return $html;
		}

		return fp_fpmail_brand_html( $html );
	}

	/**
	 * Email destinatario notifiche admin (registrazioni in attesa, report giornaliero).
	 */
	public static function get_admin_notification_email(): string {
		$opts = get_option( 'fp_dmk_settings', [] );
		$opts = is_array( $opts ) ? $opts : [];
		$to   = isset( $opts['admin_notify_email'] ) ? sanitize_email( (string) $opts['admin_notify_email'] ) : '';
		return is_email( $to ) ? $to : (string) get_bloginfo( 'admin_email' );
	}

	/**
	 * Costruisce l'URL di approvazione da email (frontend: token segreto, senza login).
	 */
	public static function build_mail_approval_url( int $user_id, string $plain_token ): string {
		return add_query_arg(
			[
				'fp_dmk_mail_approve' => '1',
				'user_id'             => $user_id,
				'key'                 => $plain_token,
			],
			home_url( '/' )
		);
	}

	/**
	 * Invia email all'amministratore per nuova registrazione in attesa di approvazione.
	 */
	public static function send_pending_registration_to_admin( int $user_id, string $plain_token ): void {
		$opts = get_option( 'fp_dmk_settings', [] );
		if ( ! is_array( $opts ) || empty( $opts['notify_pending_registration'] ) ) {
			return;
		}
		$to = self::get_admin_notification_email();
		if ( ! is_email( $to ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$approve_url = self::build_mail_approval_url( $user_id, $plain_token );
		$list_url    = admin_url( 'admin.php?page=fp-dmk-approval' );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Nuovo distributore in attesa di approvazione', 'fp-dmk' ),
			get_bloginfo( 'name' )
		);
		$subject = str_replace( [ "\r", "\n" ], '', $subject );
		$subject = apply_filters( 'fp_dmk_admin_pending_registration_subject', $subject, $user_id );

		$body = '<p>' . sprintf(
			/* translators: %s: user display name or login */
			esc_html__( 'È stata inviata una nuova richiesta di accesso al Media Kit da: %s', 'fp-dmk' ),
			esc_html( $user->display_name ?: $user->user_login )
		) . '</p>';
		$body .= '<p>' . esc_html__( 'Email:', 'fp-dmk' ) . ' ' . esc_html( $user->user_email ) . '</p>';
		$seg_label = AudienceService::get_user_segment_label( $user_id );
		if ( $seg_label !== '' ) {
			$body .= '<p>' . esc_html__( 'Tipo di accesso:', 'fp-dmk' ) . ' ' . esc_html( $seg_label ) . '</p>';
		}
		$body .= '<p><strong>' . esc_html__( 'Approva con un clic (non serve accedere alla bacheca WordPress):', 'fp-dmk' ) . '</strong><br>';
		$body .= '<a href="' . esc_url( $approve_url ) . '">' . esc_html__( 'Approva distributore', 'fp-dmk' ) . '</a></p>';
		$body .= '<p>' . esc_html__( 'Il link è personale e monouso: non inoltrarlo.', 'fp-dmk' ) . '</p>';
		$body .= '<p>' . esc_html__( 'In alternativa, dalla bacheca:', 'fp-dmk' ) . ' <a href="' . esc_url( $list_url ) . '">' . esc_html__( 'Utenti da approvare', 'fp-dmk' ) . '</a></p>';

		$body = apply_filters( 'fp_dmk_admin_pending_registration_body', $body, $user_id, $approve_url );
		$body = self::maybe_fp_mail_brand_html( $body );

		wp_mail( $to, $subject, $body, self::build_mail_headers() );
	}

	/**
	 * Invia il report giornaliero download (giorno solare precedente, timezone sito).
	 */
	public static function maybe_send_daily_download_report(): void {
		$opts = get_option( 'fp_dmk_settings', [] );
		if ( ! is_array( $opts ) || empty( $opts['daily_download_report'] ) ) {
			return;
		}
		$to = self::get_admin_notification_email();
		if ( ! is_email( $to ) ) {
			return;
		}

		$tz = wp_timezone();
		$yesterday = ( new DateTimeImmutable( 'now', $tz ) )->modify( '-1 day' )->format( 'Y-m-d' );
		$start     = $yesterday . ' 00:00:00';
		$end_excl  = ( new DateTimeImmutable( $yesterday . ' 00:00:00', $tz ) )->modify( '+1 day' )->format( 'Y-m-d H:i:s' );

		$rows = ReportService::get_download_counts_by_asset_between( $start, $end_excl );
		$total = 0;
		foreach ( $rows as $r ) {
			$total += $r->download_count;
		}

		$subject = sprintf(
			/* translators: 1: site name, 2: date (Y-m-d) */
			__( '[%1$s] Media Kit — report download %2$s', 'fp-dmk' ),
			get_bloginfo( 'name' ),
			$yesterday
		);
		$subject = str_replace( [ "\r", "\n" ], '', $subject );
		$subject = apply_filters( 'fp_dmk_daily_download_report_subject', $subject, $yesterday );

		if ( $total === 0 ) {
			$body = '<p>' . sprintf(
				/* translators: %s: date */
				esc_html__( 'Nessun download registrato il %s.', 'fp-dmk' ),
				esc_html( $yesterday )
			) . '</p>';
		} else {
			$body = '<p>' . sprintf(
				/* translators: 1: date, 2: total downloads */
				esc_html__( 'Download totali il %1$s: %2$d.', 'fp-dmk' ),
				esc_html( $yesterday ),
				$total
			) . '</p>';
			$body .= '<table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse"><thead><tr>';
			$body .= '<th>' . esc_html__( 'File / asset', 'fp-dmk' ) . '</th><th>' . esc_html__( 'Download', 'fp-dmk' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$body .= '<tr><td>' . esc_html( $r->asset_title ) . '</td><td>' . (int) $r->download_count . '</td></tr>';
			}
			$body .= '</tbody></table>';
			$reports_url = admin_url( 'admin.php?page=fp-dmk-reports' );
			$body       .= '<p><a href="' . esc_url( $reports_url ) . '">' . esc_html__( 'Apri report completi nel pannello', 'fp-dmk' ) . '</a></p>';
		}

		$body = apply_filters( 'fp_dmk_daily_download_report_body', $body, $yesterday, $rows );
		$body = self::maybe_fp_mail_brand_html( $body );

		wp_mail( $to, $subject, $body, self::build_mail_headers() );
	}

	/**
	 * Intestazioni comuni per email HTML del plugin.
	 *
	 * @return array<int, string>
	 */
	private static function build_mail_headers(): array {
		$opts = get_option( 'fp_dmk_settings', [] );
		$opts = is_array( $opts ) ? $opts : [];
		$use_fpmail_from = ! empty( $opts['use_fpmail_from'] ) && defined( 'FP_FPMAIL_VERSION' );

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		if ( ! $use_fpmail_from ) {
			$from_email = get_option( 'fp_dmk_email_from', get_bloginfo( 'admin_email' ) );
			$from_name  = get_option( 'fp_dmk_email_from_name', get_bloginfo( 'name' ) );
			$headers[]  = 'From: ' . $from_name . ' <' . $from_email . '>';
		}
		return $headers;
	}

	/**
	 * Invia email a tutti i distributori approvati.
	 *
	 * @return array{success: bool, sent: int, errors: array<string>}
	 */
	public static function send_to_all_approved( string $subject, string $body ): array {
		$users = ApprovalService::get_approved_users();
		$sent = 0;
		$errors = [];

		$headers = self::build_mail_headers();

		$subject = apply_filters( 'fp_dmk_email_subject', $subject );

		foreach ( $users as $user ) {
			if ( empty( $user->user_email ) ) {
				continue;
			}
			$body_personalized = str_replace( [ '{name}', '{email}' ], [ $user->display_name ?: $user->user_login, $user->user_email ], $body );
			$body_personalized = self::maybe_fp_mail_brand_html( $body_personalized );
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
