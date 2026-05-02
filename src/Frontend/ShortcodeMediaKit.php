<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Frontend;

use FP\DistributorMediaKit\Admin\AssetManager;
use FP\DistributorMediaKit\Download\BulkZipController;
use FP\DistributorMediaKit\User\ApprovalService;
use FP\DistributorMediaKit\User\AudienceService;

/**
 * Shortcode [fp_dmk_media_kit] / [fp_dmk_media_kit_it] / [fp_dmk_media_kit_en] — asset per cartella e categoria.
 *
 * @package FP\DistributorMediaKit\Frontend
 */
final class ShortcodeMediaKit {

	/** @var ?string Pattern LIKE per ricerca titolo/descrizione (solo query principale). */
	private static ?string $media_kit_search_like = null;

	/** Contatore per ID univoci accordion nel markup dello shortcode. */
	private static int $accordion_seq = 0;

	/**
	 * ID HTML univoco per controlli accordion (prefisso letterale + contatore).
	 */
	private static function accordion_el_id( string $prefix ): string {
		self::$accordion_seq++;

		return $prefix . self::$accordion_seq;
	}

	public static function render( array $atts ): string {
		\FP\DistributorMediaKit\Frontend\AppearanceService::enqueue_with_custom_styles();

		if ( ! is_user_logged_in() ) {
			return '<div class="fpdmk-media-kit fpdmk-ui"><p class="fpdmk-message fpdmk-message-error">' . esc_html__( 'Effettua l\'accesso per visualizzare il Media Kit.', 'fp-dmk' ) . '</p></div>';
		}
		$user_id = get_current_user_id();
		if ( ! ApprovalService::is_approved( $user_id ) ) {
			return '<div class="fpdmk-media-kit fpdmk-ui"><p class="fpdmk-message fpdmk-message-warning">' . esc_html__( 'Il tuo account è in attesa di approvazione.', 'fp-dmk' ) . '</p></div>';
		}

		$filter_cat     = isset( $atts['category'] ) ? sanitize_title( (string) $atts['category'] ) : '';
		$filter_lang    = isset( $atts['language'] ) ? sanitize_text_field( $atts['language'] ) : '';
		$filter_folder  = isset( $atts['folder'] ) ? sanitize_title( $atts['folder'] ) : '';
		$filter_search  = '';
		$filter_sort    = 'title';
		if ( $filter_cat === '' && isset( $_GET['fp_dmk_cat'] ) ) {
			$filter_cat = sanitize_title( wp_unslash( (string) $_GET['fp_dmk_cat'] ) );
		}
		if ( $filter_lang === '' && isset( $_GET['fp_dmk_lang'] ) ) {
			$filter_lang = sanitize_text_field( wp_unslash( $_GET['fp_dmk_lang'] ) );
		}
		if ( $filter_folder === '' && isset( $_GET['fp_dmk_folder'] ) ) {
			$filter_folder = sanitize_title( wp_unslash( (string) $_GET['fp_dmk_folder'] ) );
		}
		if ( isset( $_GET['fp_dmk_q'] ) ) {
			$filter_search = sanitize_text_field( wp_unslash( $_GET['fp_dmk_q'] ) );
		}
		if ( isset( $_GET['fp_dmk_sort'] ) ) {
			$filter_sort = sanitize_key( wp_unslash( (string) $_GET['fp_dmk_sort'] ) );
		}
		if ( ! in_array( $filter_sort, [ 'title', 'date', 'lang' ], true ) ) {
			$filter_sort = 'title';
		}

		$query_args = [
			'post_type'        => AssetManager::CPT,
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'fp_dmk_main_list' => true,
			'fp_dmk_search_q'  => $filter_search,
		];
		self::apply_sort_to_query_args( $query_args, $filter_sort );

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

		self::$media_kit_search_like = null;
		$search_filter_active = false;
		if ( $filter_search !== '' ) {
			global $wpdb;
			self::$media_kit_search_like = '%' . $wpdb->esc_like( $filter_search ) . '%';
			add_filter( 'posts_where', [ self::class, 'filter_posts_where_search' ], 10, 2 );
			$search_filter_active = true;
		}

		try {
			$query = new \WP_Query( $query_args );
			$posts = $query->posts ?? [];
		} finally {
			if ( $search_filter_active ) {
				remove_filter( 'posts_where', [ self::class, 'filter_posts_where_search' ], 10 );
				self::$media_kit_search_like = null;
			}
		}

		if ( $filter_sort === 'lang' && is_array( $posts ) && $posts !== [] ) {
			usort(
				$posts,
				static function ( $a, $b ): int {
					if ( ! $a instanceof \WP_Post || ! $b instanceof \WP_Post ) {
						return 0;
					}
					$la = (string) get_post_meta( $a->ID, AssetManager::META_LANGUAGE, true );
					$lb = (string) get_post_meta( $b->ID, AssetManager::META_LANGUAGE, true );

					return strcasecmp( $la, $lb );
				}
			);
		}

		$by_folder = [];
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$fterm      = AssetManager::get_primary_folder_term_for_post( $post->ID );
			$folder_key = $fterm instanceof \WP_Term ? (int) $fterm->term_id : 0;
			if ( ! isset( $by_folder[ $folder_key ] ) ) {
				$by_folder[ $folder_key ] = [
					'term'          => $fterm,
					'by_category'   => [],
				];
			}
			$terms    = AssetManager::get_asset_category_terms( $post->ID );
			$cat_slug = 'uncategorized';
			$cat_name = __( 'Altro', 'fp-dmk' );
			if ( $terms !== [] ) {
				$t = reset( $terms );
				if ( $t instanceof \WP_Term ) {
					$cat_slug = $t->slug;
					$cat_name = $t->name;
				}
			}
			if ( ! isset( $by_folder[ $folder_key ]['by_category'][ $cat_slug ] ) ) {
				$by_folder[ $folder_key ]['by_category'][ $cat_slug ] = [ 'name' => $cat_name, 'items' => [] ];
			}
			$by_folder[ $folder_key ]['by_category'][ $cat_slug ]['items'][] = $post;
		}

		$folder_order = self::ordered_folder_keys( array_keys( $by_folder ) );

		$current_url = get_permalink();

		$ids_for_cat_options    = self::query_visible_asset_ids_for_filters( $user_id, '' );
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

		$terms = self::get_category_terms_for_visible_assets( $ids_for_cat_options );
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

		$has_active_filters = $filter_folder !== '' || $filter_cat !== '' || $filter_lang !== ''
			|| $filter_search !== '' || $filter_sort !== 'title';

		$post_count = count( $posts );
		$zip_ok     = BulkZipController::is_supported();
		$bulk_nonce = wp_create_nonce( BulkZipController::NONCE_ACTION );

		$html = '<div class="fpdmk-media-kit fpdmk-ui" data-fpdmk-bulk-action="' . esc_url( home_url( '/' ) ) . '" data-fpdmk-bulk-nonce="' . esc_attr( $bulk_nonce ) . '" data-fpdmk-bulk-max="' . (int) BulkZipController::MAX_ASSETS . '" data-fpdmk-bulk-enabled="' . ( $zip_ok ? '1' : '0' ) . '">';
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

		$html .= '<div class="fpdmk-toolbar">';
		$html .= '<p class="fpdmk-results-count" role="status">';
		$html .= esc_html( self::format_results_count_label( $post_count ) );
		$html .= '</p>';
		if ( $zip_ok && $post_count > 0 ) {
			$html .= '<div class="fpdmk-bulk-bar">';
			$html .= '<label class="fpdmk-bulk-select-all"><input type="checkbox" id="fpdmk-select-all" /> ' . esc_html__( 'Seleziona tutti', 'fp-dmk' ) . '</label>';
			$html .= '<button type="button" class="fpdmk-btn fpdmk-btn-secondary" id="fpdmk-bulk-zip" disabled>';
			$html .= esc_html__( 'Scarica ZIP selezione', 'fp-dmk' );
			$html .= '</button>';
			$html .= '</div>';
		} elseif ( ! $zip_ok && $post_count > 0 ) {
			$html .= '<p class="fpdmk-bulk-unavailable">' . esc_html__( 'Download multiplo ZIP non disponibile su questo server.', 'fp-dmk' ) . '</p>';
		}
		$html .= '</div>';

		$html .= '<div class="fpdmk-filters-card">';
		$html .= '<form method="get" action="' . esc_url( $current_url ) . '" class="fpdmk-filters">';
		$html .= '<p class="fpdmk-filters-heading">' . esc_html__( 'Filtra e ordina', 'fp-dmk' ) . '</p>';
		$html .= '<div class="fpdmk-filters-grid">';
		$html .= '<div class="fpdmk-filter-field fpdmk-filter-field-wide">';
		$html .= '<label for="fp_dmk_filter_q" class="fpdmk-filter-label">' . esc_html__( 'Cerca', 'fp-dmk' ) . '</label>';
		$html .= '<input type="search" id="fp_dmk_filter_q" name="fp_dmk_q" class="fpdmk-input" value="' . esc_attr( $filter_search ) . '" placeholder="' . esc_attr__( 'Titolo o descrizione…', 'fp-dmk' ) . '" autocomplete="off" />';
		$html .= '</div>';
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
		$html .= '<div class="fpdmk-filter-field">';
		$html .= '<label for="fp_dmk_filter_sort" class="fpdmk-filter-label">' . esc_html__( 'Ordina per', 'fp-dmk' ) . '</label>';
		$html .= '<select id="fp_dmk_filter_sort" name="fp_dmk_sort" class="fpdmk-select">';
		$html .= '<option value="title"' . selected( $filter_sort, 'title', false ) . '>' . esc_html__( 'Titolo (A-Z)', 'fp-dmk' ) . '</option>';
		$html .= '<option value="date"' . selected( $filter_sort, 'date', false ) . '>' . esc_html__( 'Data aggiornamento', 'fp-dmk' ) . '</option>';
		$html .= '<option value="lang"' . selected( $filter_sort, 'lang', false ) . '>' . esc_html__( 'Lingua', 'fp-dmk' ) . '</option>';
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

		self::$accordion_seq = 0;
		$folder_index        = 0;
		foreach ( $folder_order as $folder_key ) {
			if ( ! isset( $by_folder[ $folder_key ] ) ) {
				continue;
			}
			$block            = $by_folder[ $folder_key ];
			$is_uncategorized = ( $folder_key === 0 || ! $block['term'] instanceof \WP_Term );
			$folder_title     = __( 'Materiali generali', 'fp-dmk' );
			if ( $block['term'] instanceof \WP_Term ) {
				$folder_title = AssetManager::get_folder_breadcrumb_label( $block['term'] );
			}
			$folder_title_id = self::accordion_el_id( 'fpdmk-foldert-' );
			$folder_btn_id   = self::accordion_el_id( 'fpdmk-folderbtn-' );
			$folder_panel_id = self::accordion_el_id( 'fpdmk-folderpanel-' );
			$folder_open     = ( $folder_index === 0 );

			$html .= '<div class="fpdmk-folder-block' . ( $is_uncategorized ? ' fpdmk-folder-block--uncategorized' : '' ) . '">';
			$html .= '<div class="fpdmk-folder-head">';
			$html .= '<div class="fpdmk-folder-head-row">';
			$html .= '<h3 class="fpdmk-folder-title" id="' . esc_attr( $folder_title_id ) . '">' . esc_html( $folder_title ) . '</h3>';
			$html .= '<button type="button" class="fpdmk-accordion-trigger fpdmk-accordion-trigger--folder' . ( $folder_open ? '' : ' is-collapsed' ) . '" id="' . esc_attr( $folder_btn_id ) . '" aria-expanded="' . ( $folder_open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $folder_panel_id ) . '" aria-labelledby="' . esc_attr( $folder_title_id ) . '" title="' . esc_attr__( 'Espandi o comprimi cartella', 'fp-dmk' ) . '">';
			$html .= '<span class="fpdmk-accordion-icon" aria-hidden="true"></span>';
			$html .= '<span class="fpdmk-sr-only">' . esc_html__( 'Espandi o comprimi cartella', 'fp-dmk' ) . '</span>';
			$html .= '</button>';
			$html .= '</div>';
			if ( $is_uncategorized ) {
				$html .= '<p class="fpdmk-folder-hint">' . esc_html__( 'Materiali non assegnati a una cartella nel catalogo: sono comunque disponibili per il download.', 'fp-dmk' ) . '</p>';
			}
			$html .= '</div>';
			$html .= '<div id="' . esc_attr( $folder_panel_id ) . '" class="fpdmk-folder-panel" role="region"' . ( $folder_open ? '' : ' hidden' ) . '>';

			$cat_index = 0;
			foreach ( $block['by_category'] as $cat_data ) {
				$sec_title_id = self::accordion_el_id( 'fpdmk-sectitle-' );
				$sec_btn_id   = self::accordion_el_id( 'fpdmk-secbtn-' );
				$sec_panel_id = self::accordion_el_id( 'fpdmk-secpanel-' );
				$sec_open     = ( $cat_index === 0 );

				$html .= '<section class="fpdmk-section fpdmk-section-nested fpdmk-accordion-section">';
				$html .= '<div class="fpdmk-section-head-row">';
				$html .= '<h4 class="fpdmk-section-title" id="' . esc_attr( $sec_title_id ) . '">' . esc_html( $cat_data['name'] ) . '</h4>';
				$html .= '<button type="button" class="fpdmk-accordion-trigger fpdmk-accordion-trigger--section' . ( $sec_open ? '' : ' is-collapsed' ) . '" id="' . esc_attr( $sec_btn_id ) . '" aria-expanded="' . ( $sec_open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $sec_panel_id ) . '" aria-labelledby="' . esc_attr( $sec_title_id ) . '" title="' . esc_attr__( 'Espandi o comprimi elenco', 'fp-dmk' ) . '">';
				$html .= '<span class="fpdmk-accordion-icon" aria-hidden="true"></span>';
				$html .= '<span class="fpdmk-sr-only">' . esc_html__( 'Espandi o comprimi elenco', 'fp-dmk' ) . '</span>';
				$html .= '</button>';
				$html .= '</div>';
				$html .= '<div id="' . esc_attr( $sec_panel_id ) . '" class="fpdmk-section-panel" role="region"' . ( $sec_open ? '' : ' hidden' ) . '>';
				$html .= '<div class="fpdmk-asset-list" role="list">';
				foreach ( $cat_data['items'] as $post ) {
					if ( $post instanceof \WP_Post ) {
						$html .= self::render_asset_list_row( $post, $zip_ok );
					}
				}
				$html .= '</div>';
				$html .= '</div>';
				$html .= '</section>';
				$cat_index++;
			}

			$html .= '</div>';
			$html .= '</div>';
			$folder_index++;
		}

		if ( empty( $by_folder ) ) {
			$html .= '<div class="fpdmk-empty-state">';
			if ( $has_active_filters ) {
				$html .= '<p class="fpdmk-message fpdmk-message-info">' . esc_html__( 'Nessun risultato con i filtri o la ricerca attuali. Modifica i criteri o reimposta.', 'fp-dmk' ) . '</p>';
				$html .= '<p><a class="fpdmk-btn fpdmk-btn-secondary" href="' . esc_url( $current_url ) . '">' . esc_html__( 'Reimposta tutto', 'fp-dmk' ) . '</a></p>';
			} else {
				$html .= '<p class="fpdmk-message fpdmk-message-info">' . esc_html__( 'Nessun asset disponibile al momento. Torna più tardi o contatta l\'amministratore.', 'fp-dmk' ) . '</p>';
			}
			$html .= '</div>';
		}

		$html .= '</div>';

		wp_reset_postdata();

		return $html;
	}

	/**
	 * Restringe la query principale a titolo o meta descrizione (LIKE).
	 *
	 * @param string    $where SQL WHERE.
	 * @param \WP_Query $query Query in corso.
	 */
	public static function filter_posts_where_search( string $where, \WP_Query $query ): string {
		if ( self::$media_kit_search_like === null ) {
			return $where;
		}
		if ( ! (bool) $query->get( 'fp_dmk_main_list' ) ) {
			return $where;
		}
		$qterm = (string) $query->get( 'fp_dmk_search_q' );
		if ( $qterm === '' ) {
			return $where;
		}

		global $wpdb;
		$like = self::$media_kit_search_like;
		if ( $like === null ) {
			return $where;
		}

		$where .= $wpdb->prepare(
			" AND ( {$wpdb->posts}.post_title LIKE %s OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = %s AND pm.meta_value LIKE %s ) )",
			$like,
			AssetManager::META_DESCRIPTION,
			$like
		);

		return $where;
	}

	/**
	 * Etichetta conteggio risultati (plurale corretto anche per shortcode `*_en` senza ngettext).
	 *
	 * @param int $post_count Numero di materiali nella lista corrente.
	 */
	private static function format_results_count_label( int $post_count ): string {
		if ( ShortcodeUiLang::is_english_ui() ) {
			return $post_count === 1
				? sprintf( '%d item found', $post_count )
				: sprintf( '%d items found', $post_count );
		}

		return sprintf(
			/* translators: %d: number of materials */
			_n( '%d materiale trovato', '%d materiali trovati', $post_count, 'fp-dmk' ),
			$post_count
		);
	}

	/**
	 * @param array<string, mixed> $query_args Riferimento agli argomenti WP_Query.
	 */
	private static function apply_sort_to_query_args( array &$query_args, string $sort ): void {
		switch ( $sort ) {
			case 'date':
				$query_args['orderby'] = 'modified';
				$query_args['order']   = 'DESC';
				break;
			case 'lang':
				// Ordinamento lingua in PHP dopo la query: meta_key in WP_Query escluderebbe asset senza meta.
				$query_args['orderby'] = 'title';
				$query_args['order']   = 'ASC';
				break;
			case 'title':
			default:
				$query_args['orderby'] = 'title';
				$query_args['order']   = 'ASC';
				break;
		}
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
			'post_type'      => AssetManager::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
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
		$q   = new \WP_Query( $args );
		$ids = $q->posts ?? [];
		if ( ! is_array( $ids ) ) {
			return [];
		}

		return array_values( array_map( 'intval', array_filter( $ids, static fn( $x ): bool => (int) $x > 0 ) ) );
	}

	/**
	 * ID termini cartella (e antenati) collegati agli asset indicati.
	 *
	 * @param list<int> $post_ids
	 * @return list<int>
	 */
	private static function collect_folder_term_ids_from_post_ids( array $post_ids ): array {
		return AssetManager::get_distinct_folder_term_ids_for_post_ids( $post_ids );
	}

	/**
	 * Termini categoria asset da mostrare nel filtro (solo categorie effettivamente assegnate agli asset visibili).
	 *
	 * @param list<int> $post_ids ID pubblicati nell’ambito audience (nessun filtro cartella/lingua/ricerca).
	 * @return list<\WP_Term>
	 */
	private static function get_category_terms_for_visible_assets( array $post_ids ): array {
		return AssetManager::get_distinct_category_terms_for_post_ids( $post_ids );
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

	/**
	 * Chip classificazione: tipo materiale (`fp_dmk_category`) e, se assente, cartella catalogo (`fp_dmk_folder`).
	 *
	 * Molti asset hanno solo la cartella assegnata in metabox senza termine «categoria»; in quel caso si mostra
	 * il percorso cartella (come in admin) invece del fallback generico «Altro».
	 *
	 * @param \WP_Post $post Asset pubblicato.
	 * @param string   $wrapper_class Classe contenitore chip (lista vs legacy card).
	 * @return string Markup con chip leggibili.
	 */
	private static function render_card_category_region( \WP_Post $post, string $wrapper_class = 'fpdmk-card-category' ): string {
		$labels            = [];
		$from_folder_only = false;

		foreach ( AssetManager::get_asset_category_terms( $post->ID ) as $t ) {
			if ( $t instanceof \WP_Term && $t->name !== '' ) {
				$labels[] = $t->name;
			}
		}

		if ( $labels === [] ) {
			$fterm = AssetManager::get_primary_folder_term_for_post( $post->ID );
			if ( $fterm instanceof \WP_Term ) {
				$labels[]          = AssetManager::get_folder_breadcrumb_label( $fterm );
				$from_folder_only = true;
			}
		}

		if ( $labels === [] ) {
			$labels[] = __( 'Altro', 'fp-dmk' );
		}

		$labels = array_values( array_unique( $labels ) );
		$chips  = '';
		foreach ( $labels as $name ) {
			$class = 'fpdmk-card-category-chip' . ( $from_folder_only ? ' fpdmk-card-category-chip--folder' : '' );
			$chips .= '<span class="' . esc_attr( $class ) . '">' . esc_html( $name ) . '</span>';
		}
		$aria = esc_attr( implode( ', ', $labels ) );

		return '<div class="' . esc_attr( $wrapper_class ) . '" role="group" aria-label="' . esc_attr__( 'Classificazione', 'fp-dmk' ) . ': ' . $aria . '">' . $chips . '</div>';
	}

	/**
	 * Riga elenco asset (sostituisce la card a griglia): più compatta, adatta a schermi larghi.
	 *
	 * @param \WP_Post $post   Asset pubblicato.
	 * @param bool     $zip_ok Se il client può usare selezione multipla ZIP.
	 */
	private static function render_asset_list_row( \WP_Post $post, bool $zip_ok ): string {
		$file_id    = (int) get_post_meta( $post->ID, AssetManager::META_FILE_ID, true );
		$desc       = (string) get_post_meta( $post->ID, AssetManager::META_DESCRIPTION, true );
		$lang       = (string) get_post_meta( $post->ID, AssetManager::META_LANGUAGE, true );
		$lang_label = AssetManager::LANGUAGES[ $lang ] ?? $lang;

		$bulk_pick = $zip_ok && $file_id > 0;
		$html      = '<div class="fpdmk-asset-row' . ( $bulk_pick ? ' fpdmk-asset-row--bulk' : '' ) . '" role="listitem">';
		if ( $bulk_pick ) {
			$html .= '<div class="fpdmk-asset-row-check">';
			$html .= '<input type="checkbox" class="fpdmk-card-checkbox" name="fpdmk_asset_pick[]" value="' . esc_attr( (string) $post->ID ) . '" id="fpdmk-asset-' . (int) $post->ID . '" aria-label="' . esc_attr(
				sprintf(
					/* translators: %s: asset title */
					__( 'Seleziona per ZIP: %s', 'fp-dmk' ),
					$post->post_title
				)
			) . '" />';
			$html .= '</div>';
		}
		$html .= '<div class="fpdmk-asset-row-main">';
		$html .= self::render_card_category_region( $post, 'fpdmk-asset-row-classif' );
		$html .= '<h4 class="fpdmk-asset-row-title">' . esc_html( $post->post_title ) . '</h4>';
		if ( $desc !== '' ) {
			$html .= '<p class="fpdmk-asset-row-desc">' . esc_html( $desc ) . '</p>';
		}
		$html .= '</div>';
		$html .= '<div class="fpdmk-asset-row-lang"><span class="fpdmk-asset-row-lang-badge">' . esc_html( $lang_label ) . '</span></div>';
		$html .= '<div class="fpdmk-asset-row-action">';
		if ( $file_id > 0 ) {
			$nonce = wp_create_nonce( 'fp_dmk_download_' . $post->ID );
			$url   = add_query_arg(
				[ 'fp_dmk_download' => '1', 'asset_id' => $post->ID, 'nonce' => $nonce ],
				home_url( '/' )
			);
			$html .= '<a href="' . esc_url( $url ) . '" class="fpdmk-btn fpdmk-btn-primary fpdmk-btn-download fpdmk-btn-download--row">' . esc_html__( 'Scarica', 'fp-dmk' ) . '</a>';
		} else {
			$html .= '<span class="fpdmk-asset-row-no-file">' . esc_html__( 'Nessun file', 'fp-dmk' ) . '</span>';
		}
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}
}
