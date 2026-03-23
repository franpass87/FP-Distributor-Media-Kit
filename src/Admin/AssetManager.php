<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Admin;

use FP\DistributorMediaKit\Download\TrackingService;

/**
 * Gestione CPT fp_dmk_asset, taxonomy fp_dmk_category, metabox e list table.
 *
 * @package FP\DistributorMediaKit\Admin
 */
final class AssetManager {

	public const CPT = 'fp_dmk_asset';

	public const TAXONOMY = 'fp_dmk_category';

	public const META_FILE_ID     = '_fp_dmk_file_id';
	public const META_DESCRIPTION = '_fp_dmk_description';
	public const META_LANGUAGE    = '_fp_dmk_language';

	public const LANGUAGES = [ 'it' => 'IT', 'en' => 'EN' ];

	public const CATEGORY_SLUGS = [
		'visual-assets'     => 'Visual Assets',
		'tech-sheets'       => 'Tech Sheets',
		'copy-templates'    => 'Copy Templates',
		'brand-voice-guide' => 'Brand Voice Guide',
	];

	public static function init(): void {
		add_action( 'init', [ self::class, 'register_cpt' ] );
		add_action( 'init', [ self::class, 'register_taxonomy' ] );
		add_action( 'add_meta_boxes', [ self::class, 'add_meta_boxes' ] );
		add_action( 'save_post_' . self::CPT, [ self::class, 'save_meta' ], 10, 2 );
		add_filter( 'manage_' . self::CPT . '_posts_columns', [ self::class, 'columns' ] );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', [ self::class, 'column_content' ], 10, 2 );
		add_filter( 'manage_edit-' . self::CPT . '_sortable_columns', [ self::class, 'sortable_columns' ] );
		add_action( 'transition_post_status', [ self::class, 'on_publish' ], 10, 3 );
	}

	public static function register_cpt(): void {
		$labels = [
			'name'               => _x( 'Media Kit Assets', 'post type general name', 'fp-dmk' ),
			'singular_name'      => _x( 'Asset', 'post type singular name', 'fp-dmk' ),
			'menu_name'          => __( 'Media Kit Assets', 'fp-dmk' ),
			'add_new'            => __( 'Aggiungi nuovo', 'fp-dmk' ),
			'add_new_item'       => __( 'Aggiungi nuovo asset', 'fp-dmk' ),
			'edit_item'          => __( 'Modifica asset', 'fp-dmk' ),
			'new_item'           => __( 'Nuovo asset', 'fp-dmk' ),
			'view_item'          => __( 'Visualizza asset', 'fp-dmk' ),
			'search_items'       => __( 'Cerca asset', 'fp-dmk' ),
			'not_found'          => __( 'Nessun asset trovato', 'fp-dmk' ),
			'not_found_in_trash' => __( 'Nessun asset nel cestino', 'fp-dmk' ),
		];
		register_post_type( self::CPT, [
			'labels'              => $labels,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'fp-dmk',
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => [ 'title', 'thumbnail' ],
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'menu_icon'           => 'dashicons-media-archive',
		] );
	}

	public static function register_taxonomy(): void {
		$labels = [
			'name'          => _x( 'Categorie', 'taxonomy general name', 'fp-dmk' ),
			'singular_name' => _x( 'Categoria', 'taxonomy singular name', 'fp-dmk' ),
			'menu_name'     => __( 'Categorie', 'fp-dmk' ),
		];
		register_taxonomy( self::TAXONOMY, self::CPT, [
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_admin_column' => true,
			'rewrite'           => false,
		] );

		// Inserisci termini default se vuoti
		$terms = get_terms( [ 'taxonomy' => self::TAXONOMY, 'hide_empty' => false ] );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			foreach ( self::CATEGORY_SLUGS as $slug => $name ) {
				if ( ! term_exists( $slug, self::TAXONOMY ) ) {
					wp_insert_term( $name, self::TAXONOMY, [ 'slug' => $slug ] );
				}
			}
		}
	}

	public static function add_meta_boxes(): void {
		add_meta_box(
			'fp_dmk_asset_details',
			__( 'Dettagli asset', 'fp-dmk' ),
			[ self::class, 'render_meta_box' ],
			self::CPT,
			'normal',
			'high'
		);
	}

	public static function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'fp_dmk_save_asset', 'fp_dmk_asset_nonce' );

		$file_id     = (int) get_post_meta( $post->ID, self::META_FILE_ID, true );
		$description = (string) get_post_meta( $post->ID, self::META_DESCRIPTION, true );
		$language    = (string) get_post_meta( $post->ID, self::META_LANGUAGE, true );
		if ( $language === '' ) {
			$language = 'it';
		}

		$allowed = apply_filters( 'fp_dmk_allowed_mime_types', [
			'application/pdf',
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/svg+xml',
			'video/mp4',
			'video/webm',
			'text/plain',
		] );
		?>
		<div class="fpdmk-fields-grid">
			<div class="fpdmk-field">
				<label for="fp_dmk_file_id"><?php esc_html_e( 'File', 'fp-dmk' ); ?></label>
				<?php
				$file_url = $file_id ? wp_get_attachment_url( $file_id ) : '';
				?>
				<input type="hidden" id="fp_dmk_file_id" name="fp_dmk_file_id" value="<?php echo absint( $file_id ); ?>">
				<div class="fpdmk-file-preview">
					<?php if ( $file_url ) : ?>
						<a href="<?php echo esc_url( $file_url ); ?>" target="_blank"><?php echo esc_html( basename( get_attached_file( $file_id ) ?: '' ) ); ?></a>
						<button type="button" class="button fpdmk-remove-file"><?php esc_html_e( 'Rimuovi', 'fp-dmk' ); ?></button>
					<?php else : ?>
						<button type="button" class="button fpdmk-upload-file"><?php esc_html_e( 'Seleziona file', 'fp-dmk' ); ?></button>
					<?php endif; ?>
				</div>
			</div>
			<div class="fpdmk-field">
				<label for="fp_dmk_description"><?php esc_html_e( 'Descrizione breve', 'fp-dmk' ); ?></label>
				<textarea id="fp_dmk_description" name="fp_dmk_description" rows="3" class="large-text"><?php echo esc_textarea( $description ); ?></textarea>
			</div>
			<div class="fpdmk-field">
				<label for="fp_dmk_language"><?php esc_html_e( 'Lingua', 'fp-dmk' ); ?></label>
				<select id="fp_dmk_language" name="fp_dmk_language">
					<?php foreach ( self::LANGUAGES as $code => $label ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $language, $code ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<script>
		(function() {
			var frame;
			document.querySelector('.fpdmk-upload-file')?.addEventListener('click', function() {
				if (frame) frame.open();
				else {
					frame = wp.media({
						library: { type: <?php echo wp_json_encode( array_values( $allowed ) ); ?> },
						multiple: false
					});
					frame.on('select', function() {
						var att = frame.state().get('selection').first().toJSON();
						document.getElementById('fp_dmk_file_id').value = att.id;
						location.reload();
					});
					frame.open();
				}
			});
			document.querySelector('.fpdmk-remove-file')?.addEventListener('click', function() {
				document.getElementById('fp_dmk_file_id').value = '0';
				location.reload();
			});
		})();
		</script>
		<?php
	}

	public static function save_meta( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['fp_dmk_asset_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_dmk_asset_nonce'] ) ), 'fp_dmk_save_asset' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$file_id = isset( $_POST['fp_dmk_file_id'] ) ? absint( $_POST['fp_dmk_file_id'] ) : 0;
		$desc    = isset( $_POST['fp_dmk_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fp_dmk_description'] ) ) : '';
		$lang    = isset( $_POST['fp_dmk_language'] ) && in_array( $_POST['fp_dmk_language'], array_keys( self::LANGUAGES ), true )
			? sanitize_text_field( wp_unslash( $_POST['fp_dmk_language'] ) )
			: 'it';

		update_post_meta( $post_id, self::META_FILE_ID, $file_id );
		update_post_meta( $post_id, self::META_DESCRIPTION, $desc );
		update_post_meta( $post_id, self::META_LANGUAGE, $lang );
	}

	public static function columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $k => $v ) {
			$new[ $k ] = $v;
			if ( $k === 'title' ) {
				$new['fp_dmk_category'] = __( 'Categoria', 'fp-dmk' );
				$new['fp_dmk_language'] = __( 'Lingua', 'fp-dmk' );
				$new['fp_dmk_downloads'] = __( 'Download', 'fp-dmk' );
			}
		}
		return $new;
	}

	public static function column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'fp_dmk_category':
				$terms = get_the_terms( $post_id, self::TAXONOMY );
				echo $terms && ! is_wp_error( $terms ) ? esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) ) : '—';
				break;
			case 'fp_dmk_language':
				$lang = get_post_meta( $post_id, self::META_LANGUAGE, true );
				echo esc_html( self::LANGUAGES[ $lang ] ?? $lang );
				break;
			case 'fp_dmk_downloads':
				echo absint( TrackingService::get_count_for_asset( $post_id ) );
				break;
		}
	}

	public static function sortable_columns( array $cols ): array {
		$cols['fp_dmk_downloads'] = 'fp_dmk_downloads';
		return $cols;
	}


	/**
	 * Fire hook on publish per notifiche automatiche.
	 */
	public static function on_publish( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( $post->post_type !== self::CPT || $new_status !== 'publish' ) {
			return;
		}
		do_action( 'fp_dmk_asset_published', (int) $post->ID );
	}
}
