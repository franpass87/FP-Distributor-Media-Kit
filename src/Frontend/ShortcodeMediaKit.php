<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Frontend;

use FP\DistributorMediaKit\Admin\AssetManager;
use FP\DistributorMediaKit\User\ApprovalService;
use FP\DistributorMediaKit\User\AudienceService;

/**
 * Shortcode [fp_dmk_media_kit] - Griglia asset per cartella e categoria.
 *
 * @package FP\DistributorMediaKit\Frontend
 */
final class ShortcodeMediaKit {

	public static function render( array $atts ): string {
		\FP\DistributorMediaKit\Frontend\AppearanceService::enqueue_with_custom_styles();

		if ( ! is_user_logged_in() ) {
			return '<div class="fpdmk-media-kit fpdmk-ui"><p class="fpdmk-message fpdmk-message-error">' . esc_html__( 'Effettua l\'accesso per visualizzare il Media Kit.', 'fp-dmk' ) . '</p></div>';
		}
		$user_id = get_current_user_id();
		if ( ! ApprovalService::is_approved( $user_id ) ) {
			return '<div class="fpdmk-media-kit fpdmk-ui"><p class="fpdmk-message fpdmk-message-warning">' . esc_html__( 'Il tuo account è in attesa di approvazione.', 'fp-dmk' ) . '</p></div>';
		}

		$filter_cat    = isset( $atts['category'] ) ? sanitize_text_field( $atts['category'] ) : '';
		$filter_lang   = isset( $atts['language'] ) ? sanitize_text_field( $atts['language'] ) : '';
		$filter_folder = isset( $atts['folder'] ) ? sanitize_title( $atts['folder'] ) : '';
		if ( $filter_cat === '' && isset( $_GET['fp_dmk_cat'] ) ) {
			$filter_cat = sanitize_text_field( wp_unslash( $_GET['fp_dmk_cat'] ) );
		}
		if ( $filter_lang === '' && isset( $_GET['fp_dmk_lang'] ) ) {
			$filter_lang = sanitize_text_field( wp_unslash( $_GET['fp_dmk_lang'] ) );
		}
		if ( $filter_folder === '' && isset( $_GET['fp_dmk_folder'] ) ) {
			$filter_folder = sanitize_title( wp_unslash( (string) $_GET['fp_dmk_folder'] ) );
		}

		$query_args = [
			'post_type'      => AssetManager::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		$effective_cats = AudienceService::get_effective_category_slugs_for_query( $user_id, $filter_cat );
		if ( $effective_cats !== null && $effective_cats === [] ) {
			$query_args['post__in'] = [ 0 ];
		} else {
			$tax_queries = [];
			if ( $effective_cats !== null ) {
				$tax_queries[] = [
					'taxonomy' => AssetManager::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $effective_cats,
					'operator' => 'IN',
				];
			}
			if ( $filter_folder !== '' ) {
				$tax_queries[] = [
					'taxonomy' => AssetManager::TAXONOMY_FOLDER,
					'field'    => 'slug',
					'terms'    => [ $filter_folder ],
				];
			}
			if ( count( $tax_queries ) > 1 ) {
				$tax_queries['relation'] = 'AND';
			}
			if ( $tax_queries !== [] ) {
				$query_args['tax_query'] = $tax_queries;
			}
		}

		if ( $filter_lang !== '' ) {
			$query_args['meta_query'] = [
				[
					'key'   => AssetManager::META_LANGUAGE,
					'value' => $filter_lang,
				],
			];
		}

		$query = new \WP_Query( $query_args );
		$posts = $query->posts ?? [];

		$by_folder = [];
		foreach ( $posts as $post ) {
			$fterm      = AssetManager::get_primary_folder_term_for_post( $post->ID );
			$folder_key = $fterm instanceof \WP_Term ? (int) $fterm->term_id : 0;
			if ( ! isset( $by_folder[ $folder_key ] ) ) {
				$by_folder[ $folder_key ] = [
					'term'        => $fterm,
					'by_category' => [],
				];
			}
			$terms = get_the_terms( $post->ID, AssetManager::TAXONOMY );
			$cat_slug = 'uncategorized';
			$cat_name = __( 'Altro', 'fp-dmk' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$t = reset( $terms );
				$cat_slug = $t->slug;
				$cat_name = $t->name;
			}
			if ( ! isset( $by_folder[ $folder_key ]['by_category'][ $cat_slug ] ) ) {
				$by_folder[ $folder_key ]['by_category'][ $cat_slug ] = [ 'name' => $cat_name, 'items' => [] ];
			}
			$by_folder[ $folder_key ]['by_category'][ $cat_slug ]['items'][] = $post;
		}

		$folder_order = self::ordered_folder_keys( array_keys( $by_folder ) );

		$current_url = get_permalink();

		// Opzioni select: non usare get_terms( hide_empty => true ) — il count WP è solo sulle
		// assegnazioni dirette; cartelle padre e categorie non collegate agli asset risultano «vuote».
		$ids_for_cat_options   = self::query_visible_asset_ids_for_filters( $user_id, '' );
		$ids_for_folder_options = ( $filter_cat !== '' )
			? self::query_visible_asset_ids_for_filters( $user_id, $filter_cat )
			: $ids_for_cat_options;

		$folder_ids_for_select = self::collect_folder_term_ids_from_post_ids( $ids_for_folder_options );
		$folder_for_select     = [];
		foreach ( AssetManager::get_folder_terms_hierarchical_options() as $row ) {
			$t = $row['term'];
			if ( ! $t instanceof \WP_Term || ! in_array( (int) $t->term_id, $folder_ids_for_select, true ) ) {
				continue;
			}
			$folder_for_select[] = $row;
		}

		$cat_slugs_seen = self::collect_category_slugs_from_post_ids( $ids_for_cat_options );
		$terms          = [];
		if ( $cat_slugs_seen !== [] ) {
			$raw_terms = get_terms(
				[
					'taxonomy'   => AssetManager::TAXONOMY,
					'hide_empty' => false,
					'slug'       => $cat_slugs_seen,
					'orderby'    => 'name',
					'order'      => 'ASC',
				]
			);
			$terms = is_array( $raw_terms ) ? $raw_terms : [];
		}
		$allowed = AudienceService::get_allowed_category_slugs_for_user( $user_id );
		if ( $allowed !== null && $allowed !== [] ) {
			$terms = array_values(
				array_filter(
					$terms,
					static fn( $t ): bool => $t instanceof \WP_Term && in_array( $t->slug, $allowed, true )
				)
			);
		} elseif ( $allowed !== null && $allowed === [] ) {
			$terms = [];
		}

		$has_active_filters = $filter_folder !== '' || $filter_cat !== '' || $filter_lang !== '';

		$html = '<div class="fpdmk-media-kit fpdmk-ui">';
		$html .= '<header class="fpdmk-media-kit-header">';
		$html .= '<div class="fpdmk-media-kit-hero">';
		$html .= '<div class="fpdmk-media-kit-hero-text">';
		$html .= '<h2 class="fpdmk-media-kit-title">' . esc_html__( 'Media Kit', 'fp-dmk' ) . '</h2>';
		$html .= '<p class="fpdmk-media-kit-desc">' . esc_html__( 'Scarica gli asset e i materiali disponibili.', 'fp-dmk' ) . '</p>';
		$html .= '</div>';
		$html .= '<div class="fpdmk-media-kit-actions">';
		$html .= '<a href="' . esc_url( wp_logout_url( get_permalink() ) ) . '" class="fpdmk-btn fpdmk-btn-secondary fpdmk-btn-logout">' . esc_html__( 'Esci', 'fp-dmk' ) . '</a>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</header>';

		$html .= '<div class="fpdmk-filters-card">';
		$html .= '<form method="get" action="' . esc_url( $current_url ) . '" class="fpdmk-filters">';
		$html .= '<p class="fpdmk-filters-heading">' . esc_html__( 'Filtra i materiali', 'fp-dmk' ) . '</p>';
		$html .= '<div class="fpdmk-filters-grid">';
		$html .= '<div class="fpdmk-filter-field">';
		$html .= '<label for="fp_dmk_filter_folder" class="fpdmk-filter-label">' . esc_html__( 'Cartella', 'fp-dmk' ) . '</label>';
		$html .= '<select id="fp_dmk_filter_folder" name="fp_dmk_folder" class="fpdmk-select">';
		$html .= '<option value="">' . esc_html__( 'Tutte le cartelle', 'fp-dmk' ) . '</option>';
		foreach ( $folder_for_select as $row ) {
			$t = $row['term'];
			if ( ! $t instanceof \WP_Term ) {
				continue;
			}
			$pad = str_repeat( '— ', (int) $row['depth'] );
			$html .= '<option value="' . esc_attr( $t->slug ) . '"' . selected( $filter_folder, $t->slug, false ) . '>' . esc_html( $pad . $t->name ) . '</option>';
		}
		$html .= '</select>';
		$html .= '</div>';
		$html .= '<div class="fpdmk-filter-field">';
		$html .= '<label for="fp_dmk_filter_cat" class="fpdmk-filter-label">' . esc_html__( 'Categoria', 'fp-dmk' ) . '</label>';
		$html .= '<select id="fp_dmk_filter_cat" name="fp_dmk_cat" class="fpdmk-select">';
		$html .= '<option value="">' . esc_html__( 'Tutte le categorie', 'fp-dmk' ) . '</option>';
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$html .= '<option value="' . esc_attr( $term->slug ) . '"' . selected( $filter_cat, $term->slug, false ) . '>' . esc_html( $term->name ) . '</option>';
			}
		}
		$html .= '</select>';
		$html .= '</div>';
		$html .= '<div class="fpdmk-filter-field">';
		$html .= '<label for="fp_dmk_filter_lang" class="fpdmk-filter-label">' . esc_html__( 'Lingua', 'fp-dmk' ) . '</label>';
		$html .= '<select id="fp_dmk_filter_lang" name="fp_dmk_lang" class="fpdmk-select">';
		$html .= '<option value="">' . esc_html__( 'Tutte le lingue', 'fp-dmk' ) . '</option>';
		foreach ( AssetManager::LANGUAGES as $code => $label ) {
			$html .= '<option value="' . esc_attr( $code ) . '"' . selected( $filter_lang, $code, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '<div class="fpdmk-filters-actions">';
		$html .= '<button type="submit" class="fpdmk-btn fpdmk-btn-primary">' . esc_html__( 'Applica filtri', 'fp-dmk' ) . '</button>';
		if ( $has_active_filters ) {
			$html .= '<a class="fpdmk-link fpdmk-link-reset" href="' . esc_url( $current_url ) . '">' . esc_html__( 'Reimposta', 'fp-dmk' ) . '</a>';
		}
		$html .= '</div>';
		$html .= '</form>';
		$html .= '</div>';

		foreach ( $folder_order as $folder_key ) {
			if ( ! isset( $by_folder[ $folder_key ] ) ) {
				continue;
			}
			$block             = $by_folder[ $folder_key ];
			$is_uncategorized  = ( $folder_key === 0 || ! $block['term'] instanceof \WP_Term );
			$folder_title      = __( 'Materiali generali', 'fp-dmk' );
			if ( $block['term'] instanceof \WP_Term ) {
				$folder_title = AssetManager::get_folder_breadcrumb_label( $block['term'] );
			}
			$html .= '<div class="fpdmk-folder-block' . ( $is_uncategorized ? ' fpdmk-folder-block--uncategorized' : '' ) . '">';
			$html .= '<div class="fpdmk-folder-head">';
			$html .= '<h3 class="fpdmk-folder-title">' . esc_html( $folder_title ) . '</h3>';
			if ( $is_uncategorized ) {
				$html .= '<p class="fpdmk-folder-hint">' . esc_html__( 'Materiali non assegnati a una cartella nel catalogo: sono comunque disponibili per il download.', 'fp-dmk' ) . '</p>';
			}
			$html .= '</div>';
			foreach ( $block['by_category'] as $cat_data ) {
				$html .= '<section class="fpdmk-section fpdmk-section-nested">';
				$html .= '<h4 class="fpdmk-section-title">' . esc_html( $cat_data['name'] ) . '</h4>';
				$html .= '<div class="fpdmk-cards">';
				foreach ( $cat_data['items'] as $post ) {
					$html .= self::render_card( $post );
				}
				$html .= '</div>';
				$html .= '</section>';
			}
			$html .= '</div>';
		}

		if ( empty( $by_folder ) ) {
			$html .= '<div class="fpdmk-empty-state">';
			$html .= '<p class="fpdmk-message fpdmk-message-info">' . esc_html__( 'Nessun asset disponibile al momento. Torna più tardi o contatta l\'amministratore.', 'fp-dmk' ) . '</p>';
			$html .= '</div>';
		}

		$html .= '</div>';

		wp_reset_postdata();

		return $html;
	}

	/**
	 * ID degli asset pubblicati da considerare per le opzioni dei filtri (nessun filtro cartella/lingua).
	 *
	 * @param string $filter_cat_slug Slug categoria da combinare con le regole audience (vuoto = tutte le categorie consentite).
	 * @return list<int>
	 */
	private static function query_visible_asset_ids_for_filters( int $user_id, string $filter_cat_slug ): array {
		$effective = AudienceService::get_effective_category_slugs_for_query( $user_id, $filter_cat_slug );
		if ( $effective !== null && $effective === [] ) {
			return [];
		}
		$args = [
			'post_type'        => AssetManager::CPT,
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'orderby'          => 'title',
			'order'            => 'ASC',
			'no_found_rows'    => true,
		];
		if ( $effective !== null ) {
			$args['tax_query'] = [
				[
					'taxonomy' => AssetManager::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $effective,
					'operator' => 'IN',
				],
			];
		}
		$q = new \WP_Query( $args );
		$ids = $q->posts ?? [];
		if ( ! is_array( $ids ) ) {
			return [];
		}

		return array_values( array_map( 'intval', array_filter( $ids, static fn( $x ): bool => (int) $x > 0 ) ) );
	}

	/**
	 * ID termini cartella (e antenati) usati dagli asset indicati.
	 *
	 * @param list<int> $post_ids
	 * @return list<int>
	 */
	private static function collect_folder_term_ids_from_post_ids( array $post_ids ): array {
		$out = [];
		foreach ( $post_ids as $pid ) {
			$fterm = AssetManager::get_primary_folder_term_for_post( $pid );
			if ( ! $fterm instanceof \WP_Term ) {
				continue;
			}
			$tid = (int) $fterm->term_id;
			$out[] = $tid;
			foreach ( get_ancestors( $tid, AssetManager::TAXONOMY_FOLDER, 'taxonomy' ) as $aid ) {
				$out[] = (int) $aid;
			}
		}

		return array_values( array_unique( array_filter( $out, static fn( int $x ): bool => $x > 0 ) ) );
	}

	/**
	 * Slug categorie asset presenti sugli ID indicati.
	 *
	 * @param list<int> $post_ids
	 * @return list<string>
	 */
	private static function collect_category_slugs_from_post_ids( array $post_ids ): array {
		$slugs = [];
		foreach ( $post_ids as $pid ) {
			$ts = get_the_terms( $pid, AssetManager::TAXONOMY );
			if ( ! $ts || is_wp_error( $ts ) ) {
				continue;
			}
			foreach ( $ts as $t ) {
				if ( $t instanceof \WP_Term && $t->slug !== '' ) {
					$slugs[] = $t->slug;
				}
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Ordine di visualizzazione blocchi cartella (alfabetico per percorso; materiali senza cartella, chiave 0, in coda).
	 *
	 * @param list<int> $keys Chiavi folder (0 = nessuna cartella).
	 * @return list<int>
	 */
	private static function ordered_folder_keys( array $keys ): array {
		$keys = array_values( array_unique( array_map( 'intval', $keys ) ) );
		usort(
			$keys,
			static function ( int $a, int $b ): int {
				if ( $a === $b ) {
					return 0;
				}
				if ( $a === 0 ) {
					return 1;
				}
				if ( $b === 0 ) {
					return -1;
				}
				$ta = get_term( $a, AssetManager::TAXONOMY_FOLDER );
				$tb = get_term( $b, AssetManager::TAXONOMY_FOLDER );
				$sa = ( $ta instanceof \WP_Term && ! is_wp_error( $ta ) )
					? AssetManager::get_folder_breadcrumb_label( $ta )
					: '';
				$sb = ( $tb instanceof \WP_Term && ! is_wp_error( $tb ) )
					? AssetManager::get_folder_breadcrumb_label( $tb )
					: '';
				return strcasecmp( $sa, $sb );
			}
		);
		return $keys;
	}

	private static function render_card( \WP_Post $post ): string {
		$file_id    = (int) get_post_meta( $post->ID, AssetManager::META_FILE_ID, true );
		$desc       = (string) get_post_meta( $post->ID, AssetManager::META_DESCRIPTION, true );
		$lang       = (string) get_post_meta( $post->ID, AssetManager::META_LANGUAGE, true );
		$lang_label = AssetManager::LANGUAGES[ $lang ] ?? $lang;

		$html = '<article class="fpdmk-card fpdmk-card-asset">';
		$html .= '<div class="fpdmk-card-body">';
		$html .= '<h4 class="fpdmk-card-title">' . esc_html( $post->post_title ) . '</h4>';
		if ( $desc !== '' ) {
			$html .= '<p class="fpdmk-card-desc">' . esc_html( $desc ) . '</p>';
		}
		$html .= '<span class="fpdmk-card-meta">' . esc_html( $lang_label ) . '</span>';
		if ( $file_id > 0 ) {
			$nonce = wp_create_nonce( 'fp_dmk_download_' . $post->ID );
			$url   = add_query_arg(
				[ 'fp_dmk_download' => '1', 'asset_id' => $post->ID, 'nonce' => $nonce ],
				home_url( '/' )
			);
			$html .= '<a href="' . esc_url( $url ) . '" class="fpdmk-btn fpdmk-btn-primary fpdmk-btn-download">' . esc_html__( 'Scarica', 'fp-dmk' ) . '</a>';
		} else {
			$html .= '<span class="fpdmk-card-no-file">' . esc_html__( 'Nessun file associato', 'fp-dmk' ) . '</span>';
		}
		$html .= '</div>';
		$html .= '</article>';

		return $html;
	}
}
