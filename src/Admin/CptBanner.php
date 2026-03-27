<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Admin;

/**
 * Inietta il banner FP standard sulle pagine CPT e taxonomy WordPress.
 *
 * @package FP\DistributorMediaKit\Admin
 */
final class CptBanner {

	public static function init(): void {
		add_action( 'all_admin_notices', [ self::class, 'render' ], 0 );
	}

	public static function render(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$title    = '';
		$desc     = '';
		$dashicon = 'dashicons-media-archive';
		$add_url  = '';

		if ( isset( $screen->taxonomy ) && $screen->taxonomy === AssetManager::TAXONOMY ) {
			$title    = __( 'Categorie', 'fp-dmk' );
			$desc     = __( 'Organizza gli asset in categorie (Visual Assets, Tech Sheets, ecc.).', 'fp-dmk' );
			$dashicon = 'dashicons-category';
		} elseif ( isset( $screen->taxonomy ) && $screen->taxonomy === AssetManager::TAXONOMY_FOLDER ) {
			$title    = __( 'Cartelle', 'fp-dmk' );
			$desc     = __( 'Raggruppa gli asset in cartelle e sottocartelle per il Media Kit (ordinamento in frontend).', 'fp-dmk' );
			$dashicon = 'dashicons-portfolio';
		} elseif ( $screen->post_type === AssetManager::CPT ) {
			$post_type_obj = get_post_type_object( AssetManager::CPT );
			if ( $screen->base === 'edit' ) {
				$title   = __( 'Media Kit Assets', 'fp-dmk' );
				$desc    = __( 'Gestisci gli asset disponibili nel Media Kit per i distributori.', 'fp-dmk' );
				if ( $post_type_obj && current_user_can( $post_type_obj->cap->create_posts ) ) {
					$add_url = admin_url( 'post-new.php?post_type=' . AssetManager::CPT );
				}
			} elseif ( $screen->base === 'post' ) {
				$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
				$title   = $post_id ? __( 'Modifica asset', 'fp-dmk' ) : __( 'Aggiungi nuovo asset', 'fp-dmk' );
				$desc    = __( 'Configura titolo, file e metadati dell\'asset.', 'fp-dmk' );
			}
		}

		if ( $title === '' ) {
			return;
		}

		?>
		<div class="fpdmk-cpt-banner-wrap">
			<h1 class="screen-reader-text"><?php echo esc_html( __( 'FP Media Kit', 'fp-dmk' ) ); ?></h1>
			<div class="fpdmk-page-header fpdmk-cpt-page-header">
				<div class="fpdmk-page-header-content">
					<h2 class="fpdmk-page-header-title" aria-hidden="true"><span class="dashicons <?php echo esc_attr( $dashicon ); ?>"></span> <?php echo esc_html( $title ); ?></h2>
					<p><?php echo esc_html( $desc ); ?></p>
				</div>
				<div class="fpdmk-page-header-right">
					<?php if ( $add_url !== '' ) : ?>
						<a href="<?php echo esc_url( $add_url ); ?>" class="fpdmk-btn fpdmk-btn-primary fpdmk-btn-add"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Aggiungi nuovo', 'fp-dmk' ); ?></a>
					<?php endif; ?>
					<span class="fpdmk-page-header-badge">v<?php echo esc_html( FP_DMK_VERSION ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}
}
