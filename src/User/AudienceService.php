<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\User;

/**
 * Tipi di utenza (distributore, giornalista, …) e categorie asset visibili per tipo.
 *
 * @package FP\DistributorMediaKit\User
 */
final class AudienceService {

	public const META_SEGMENT = 'fp_dmk_segment';

	private const SETTINGS_KEY = 'fp_dmk_settings';

	/**
	 * Opzioni plugin (sottoinsieme audience).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$opts = get_option( self::SETTINGS_KEY, [] );

		return is_array( $opts ) ? $opts : [];
	}

	/**
	 * Segmentazione attiva (form registrazione + filtri asset).
	 *
	 * Richiede almeno un segmento configurato, così non resta uno stato incoerente (flag on ma lista vuota).
	 */
	public static function is_audience_enabled(): bool {
		if ( empty( self::get_settings()['audience_enabled'] ) ) {
			return false;
		}

		return self::get_segments() !== [];
	}

	/**
	 * Segmenti configurati (slug + etichette IT/EN).
	 *
	 * @return list<array{slug: string, label: string, label_en: string}>
	 */
	public static function get_segments(): array {
		$raw = self::get_settings()['audience_segments'] ?? [];
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$out = [];
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug = isset( $row['slug'] ) ? sanitize_key( (string) $row['slug'] ) : '';
			if ( $slug === '' ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
			if ( $label === '' ) {
				$label = $slug;
			}
			$label_en = isset( $row['label_en'] ) ? sanitize_text_field( (string) $row['label_en'] ) : '';
			$out[]    = [
				'slug'     => $slug,
				'label'    => $label,
				'label_en' => $label_en,
			];
		}

		return $out;
	}

	/**
	 * Etichetta segmento per interfaccia IT o EN.
	 *
	 * @param array{slug?: string, label?: string, label_en?: string} $segment
	 */
	public static function get_segment_display_label( array $segment, bool $english_ui = false ): string {
		$slug = isset( $segment['slug'] ) ? sanitize_key( (string) $segment['slug'] ) : '';
		$label = isset( $segment['label'] ) ? sanitize_text_field( (string) $segment['label'] ) : '';
		if ( $label === '' && $slug !== '' ) {
			$label = $slug;
		}
		if ( ! $english_ui ) {
			return $label;
		}

		$label_en = isset( $segment['label_en'] ) ? sanitize_text_field( (string) $segment['label_en'] ) : '';
		if ( $label_en !== '' ) {
			$translated = $label_en;
		} else {
			$defaults = [
				'distributor'  => 'Distributor',
				'journalist'   => 'Journalist',
				'distributore' => 'Distributor',
				'giornalista'  => 'Journalist',
			];
			$translated = $defaults[ $slug ] ?? $label;
		}

		/**
		 * Personalizza l’etichetta di un segmento audience in interfaccia inglese.
		 *
		 * @param string $translated Etichetta proposta.
		 * @param string $slug       Slug segmento.
		 * @param string $label      Etichetta italiana configurata.
		 * @param string $locale     Codice locale UI (`en`).
		 */
		return (string) apply_filters( 'fp_dmk_audience_segment_label', $translated, $slug, $label, 'en' );
	}

	/**
	 * Slug segmento salvato per l'utente (vuoto se non impostato).
	 */
	public static function get_user_segment_slug( int $user_id ): string {
		if ( $user_id <= 0 ) {
			return '';
		}
		$v = get_user_meta( $user_id, self::META_SEGMENT, true );

		return is_string( $v ) ? sanitize_key( $v ) : '';
	}

	/**
	 * Etichetta leggibile del segmento utente (per email e liste admin).
	 */
	public static function get_user_segment_label( int $user_id ): string {
		$slug = self::get_user_segment_slug( $user_id );
		if ( $slug === '' ) {
			return '';
		}
		foreach ( self::get_segments() as $row ) {
			if ( $row['slug'] === $slug ) {
				return $row['label'];
			}
		}

		return $slug;
	}

	/**
	 * Verifica se lo slug è tra i segmenti configurati.
	 */
	public static function is_valid_segment_slug( string $slug ): bool {
		$slug = sanitize_key( $slug );
		if ( $slug === '' ) {
			return false;
		}
		foreach ( self::get_segments() as $row ) {
			if ( $row['slug'] === $slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Salva il segmento utente (stringa vuota = rimuovi meta → tutte le categorie se segmentazione attiva).
	 *
	 * @return bool True se salvato o cancellato correttamente.
	 */
	public static function set_user_segment( int $user_id, string $slug ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		$slug = sanitize_key( $slug );
		if ( $slug === '' ) {
			delete_user_meta( $user_id, self::META_SEGMENT );

			return true;
		}
		if ( ! self::is_valid_segment_slug( $slug ) ) {
			return false;
		}

		update_user_meta( $user_id, self::META_SEGMENT, $slug );

		return true;
	}

	/**
	 * Staff plugin / WP: nessun filtro categorie.
	 */
	public static function user_is_audience_staff( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		return user_can( $user, 'manage_options' )
			|| user_can( $user, 'edit_posts' )
			|| user_can( $user, 'manage_fp_dmk' );
	}

	/**
	 * Slug categorie asset consentite per l'utente.
	 *
	 * - `null` = nessuna restrizione (tutte le categorie).
	 * - `[]` = nessun asset visibile (configurazione esplicita).
	 * - lista = solo quelle categorie (tassonomia fp_dmk_category).
	 *
	 * @return list<string>|null
	 */
	public static function get_allowed_category_slugs_for_user( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		if ( ! self::is_audience_enabled() ) {
			return null;
		}
		if ( self::user_is_audience_staff( $user_id ) ) {
			return null;
		}

		$slug = self::get_user_segment_slug( $user_id );
		if ( $slug === '' ) {
			return null;
		}

		$map = self::get_settings()['audience_segment_categories'] ?? [];
		if ( ! is_array( $map ) ) {
			return null;
		}
		if ( ! array_key_exists( $slug, $map ) ) {
			return null;
		}
		$list = $map[ $slug ];
		if ( ! is_array( $list ) ) {
			return null;
		}
		$clean = array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( $t ): string => sanitize_title( (string) $t ),
						$list
					)
				)
			)
		);

		return apply_filters( 'fp_dmk_allowed_asset_category_slugs', $clean, $user_id, $slug );
	}

	/**
	 * Slug categorie da usare in WP_Query (combinazione filtro UI + restrizioni utente).
	 *
	 * @return list<string>|null `null` = non aggiungere tax_query per restrizione; `[]` = nessun risultato.
	 */
	public static function get_effective_category_slugs_for_query( int $user_id, string $filter_cat_slug ): ?array {
		$filter_cat_slug = sanitize_title( $filter_cat_slug );
		$allowed         = self::get_allowed_category_slugs_for_user( $user_id );

		if ( $allowed !== null && $allowed === [] ) {
			return [];
		}

		if ( $filter_cat_slug !== '' ) {
			if ( $allowed !== null && ! in_array( $filter_cat_slug, $allowed, true ) ) {
				return [];
			}

			return [ $filter_cat_slug ];
		}

		if ( $allowed !== null ) {
			return $allowed;
		}

		return null;
	}

	/**
	 * L'utente può scaricare l'asset (categoria consentita)?
	 */
	public static function user_can_access_asset( int $user_id, int $asset_id ): bool {
		if ( $asset_id <= 0 || ! self::is_audience_enabled() ) {
			return true;
		}
		if ( self::user_is_audience_staff( $user_id ) ) {
			return true;
		}

		$allowed = self::get_allowed_category_slugs_for_user( $user_id );
		if ( $allowed === null ) {
			return true;
		}
		if ( $allowed === [] ) {
			return false;
		}

		$terms = \FP\DistributorMediaKit\Admin\AssetManager::get_asset_category_terms( $asset_id );
		if ( $terms === [] ) {
			return false;
		}
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term && in_array( $term->slug, $allowed, true ) ) {
				return true;
			}
		}

		return false;
	}
}
