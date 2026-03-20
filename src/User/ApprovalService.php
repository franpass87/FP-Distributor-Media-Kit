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

	public const STATUS_PENDING = '0';

	public const STATUS_APPROVED = '1';

	/**
	 * Verifica se l'utente è approvato.
	 * Admin e editor bypassano il controllo (gestiscono il Media Kit).
	 */
	public static function is_approved( int $user_id ): bool {
		$user = $user_id > 0 ? get_userdata( $user_id ) : false;
		if ( $user instanceof \WP_User && ( user_can( $user, 'manage_options' ) || user_can( $user, 'edit_posts' ) ) ) {
			return true;
		}
		return (string) get_user_meta( $user_id, self::META_KEY, true ) === self::STATUS_APPROVED;
	}

	/**
	 * Imposta stato approvazione.
	 */
	public static function set_approved( int $user_id, bool $approved ): bool {
		return update_user_meta( $user_id, self::META_KEY, $approved ? self::STATUS_APPROVED : self::STATUS_PENDING );
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
}
