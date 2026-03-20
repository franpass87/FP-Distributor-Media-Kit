<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Frontend;

use FP\DistributorMediaKit\Admin\AssetManager;
use FP\DistributorMediaKit\Download\TrackingService;
use FP\DistributorMediaKit\User\ApprovalService;

/**
 * Shortcode [fp_dmk_media_kit] - Griglia asset per categoria.
 *
 * @package FP\DistributorMediaKit\Frontend
 */
final class ShortcodeMediaKit {

	public static function render( array $atts ): string {
		wp_enqueue_style( 'fp-dmk-frontend', FP_DMK_URL . 'assets/css/frontend.css', [], FP_DMK_VERSION );

		if ( ! is_user_logged_in() ) {
			return '<p class="fpdmk-message fpdmk-message-error">' . esc_html__( 'Effettua l\'accesso per visualizzare il Media Kit.', 'fp-dmk' ) . '</p>';
		}
		$user_id = get_current_user_id();
		if ( ! ApprovalService::is_approved( $user_id ) ) {
			return '<p class="fpdmk-message fpdmk-message-warning">' . esc_html__( 'Il tuo account è in attesa di approvazione.', 'fp-dmk' ) . '</p>';
		}

		$filter_cat = isset( $atts['category'] ) ? sanitize_text_field( $atts['category'] ) : '';
		$filter_lang = isset( $atts['language'] ) ? sanitize_text_field( $atts['language'] ) : '';

		$query_args = [
			'post_type'      => AssetManager::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];
		if ( $filter_cat !== '' ) {
			$query_args['tax_query'] = [
				[
					'taxonomy' => AssetManager::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $filter_cat,
				],
			];
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

		$by_category = [];
		foreach ( $posts as $post ) {
			$terms = get_the_terms( $post->ID, AssetManager::TAXONOMY );
			$cat_slug = 'uncategorized';
			$cat_name = __( 'Altro', 'fp-dmk' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$t = reset( $terms );
				$cat_slug = $t->slug;
				$cat_name = $t->name;
			}
			if ( ! isset( $by_category[ $cat_slug ] ) ) {
				$by_category[ $cat_slug ] = [ 'name' => $cat_name, 'items' => [] ];
			}
			$by_category[ $cat_slug ]['items'][] = $post;
		}

		$html = '<div class="fpdmk-media-kit">';
		$html .= '<div class="fpdmk-media-kit-header">';
		$html .= '<h2 class="fpdmk-media-kit-title">' . esc_html__( 'Media Kit', 'fp-dmk' ) . '</h2>';
		$html .= '<p class="fpdmk-media-kit-desc">' . esc_html__( 'Scarica gli asset e i materiali disponibili.', 'fp-dmk' ) . '</p>';
		$html .= '<div class="fpdmk-media-kit-actions">';
		$html .= '<a href="' . esc_url( wp_logout_url( get_permalink() ) ) . '" class="fpdmk-btn fpdmk-btn-secondary">' . esc_html__( 'Esci', 'fp-dmk' ) . '</a>';
		$html .= '</div>';
		$html .= '</div>';

		foreach ( $by_category as $cat_slug => $cat_data ) {
			$html .= '<section class="fpdmk-section">';
			$html .= '<h3 class="fpdmk-section-title">' . esc_html( $cat_data['name'] ) . '</h3>';
			$html .= '<div class="fpdmk-cards">';
			foreach ( $cat_data['items'] as $post ) {
				$html .= self::render_card( $post );
			}
			$html .= '</div>';
			$html .= '</section>';
		}

		if ( empty( $by_category ) ) {
			$html .= '<p class="fpdmk-message fpdmk-message-info">' . esc_html__( 'Nessun asset disponibile al momento.', 'fp-dmk' ) . '</p>';
		}

		$html .= '</div>';

		wp_reset_postdata();

		return $html;
	}

	private static function render_card( \WP_Post $post ): string {
		$file_id = (int) get_post_meta( $post->ID, AssetManager::META_FILE_ID, true );
		$desc    = (string) get_post_meta( $post->ID, AssetManager::META_DESCRIPTION, true );
		$lang    = (string) get_post_meta( $post->ID, AssetManager::META_LANGUAGE, true );
		$lang_label = AssetManager::LANGUAGES[ $lang ] ?? $lang;
		$downloads = TrackingService::get_count_for_asset( $post->ID );

		$html = '<div class="fpdmk-card">';
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
			$html .= '<span class="fpdmk-card-no-file">' . esc_html__( 'File non disponibile', 'fp-dmk' ) . '</span>';
		}
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}
}
