<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\User;

/**
 * Servizio approvazione utenti (user_meta fp_dmk_approved).
 *
 * @package FP\DistributorMediaKit\User
 */
final class ApprovalService {

	public const META_KEY = 'fp_dmk_approved';

	/**
	 * Hash del token per link approvazione da email (plain inviato solo via mail).
	 */
	public const META_APPROVE_TOKEN = 'fp_dmk_approve_token';

	public const STATUS_PENDING = '0';

	public const STATUS_APPROVED = '1';

	/**
	 * Verifica se l'utente è approvato.
	 * Staff che gestisce il Media Kit bypassa il controllo: amministratori, chi ha `edit_posts`,
	 * e il ruolo **FP Media Kit Manager** (`manage_fp_dmk` senza `edit_posts`).
	 */
	public static function is_approved( int $user_id ): bool {
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;
		if ( $user instanceof \WP_User && ( user_can( $user, 'manage_options' ) || user_can( $user, 'edit_posts' ) || user_can( $user, 'manage_fp_dmk' ) ) ) {
			return true;
		}
		return (string) get_user_meta( $user_id, self::META_KEY, true ) === self::STATUS_APPROVED;
	}

	/**
	 * Imposta stato approvazione.
	 */
	public static function set_approved( int $user_id, bool $approved ): bool {
		if ( $approved ) {
			delete_user_meta( $user_id, self::META_APPROVE_TOKEN );
		}
		return update_user_meta( $user_id, self::META_KEY, $approved ? self::STATUS_APPROVED : self::STATUS_PENDING );
	}

	/**
	 * Genera un token per il link di approvazione via email e ne salva l'hash in user meta.
	 *
	 * @return string Token in chiaro da includere nell'URL (solo per l'email).
	 */
	public static function issue_approval_email_token( int $user_id ): string {
		$plain = wp_generate_password( 48, false, false );
		update_user_meta( $user_id, self::META_APPROVE_TOKEN, wp_hash( $plain ) );
		return $plain;
	}

	/**
	 * Verifica il token ricevuto dal link email rispetto all'hash salvato.
	 */
	public static function validate_approval_email_token( int $user_id, string $plain ): bool {
		if ( $user_id <= 0 || $plain === '' ) {
			return false;
		}
		$stored = get_user_meta( $user_id, self::META_APPROVE_TOKEN, true );
		if ( ! is_string( $stored ) || $stored === '' ) {
			return false;
		}
		return hash_equals( $stored, wp_hash( $plain ) );
	}

	/**
	 * Ottiene utenti pending (fp_dmk_approved = 0).
	 *
	 * @return array<int, \WP_User>
	 */
	public static function get_pending_users(): array {
		$users = get_users( [
			'meta_key'   => self::META_KEY,
			'meta_value' => self::STATUS_PENDING,
			'number'     => 500,
		] );
		return $users ?: [];
	}

	/**
	 * Ottiene utenti approvati (fp_dmk_approved = 1).
	 *
	 * @return array<int, \WP_User>
	 */
	public static function get_approved_users(): array {
		$users = get_users( [
			'meta_key'   => self::META_KEY,
			'meta_value' => self::STATUS_APPROVED,
			'number'     => 5000,
		] );
		return $users ?: [];
	}

	/**
	 * Ottiene tutti i distributori (con meta fp_dmk_approved, approved o pending).
	 *
	 * @param string $status 'all'|'approved'|'pending' Filtro per stato.
	 * @param string $orderby Campo ordinamento (user_registered, display_name, user_email).
	 * @param string $order ASC o DESC.
	 * @return array<int, \WP_User>
	 */
	public static function get_all_distributors( string $status = 'all', string $orderby = 'user_registered', string $order = 'DESC' ): array {
		$args = [
			'meta_key' => self::META_KEY,
			'number'   => 5000,
			'orderby'  => $orderby,
			'order'    => strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC',
		];
		if ( $status === 'approved' ) {
			$args['meta_value'] = self::STATUS_APPROVED;
		} elseif ( $status === 'pending' ) {
			$args['meta_value'] = self::STATUS_PENDING;
		} else {
			$args['meta_compare'] = 'EXISTS';
		}
		$users = get_users( $args );
		return $users ?: [];
	}
}
