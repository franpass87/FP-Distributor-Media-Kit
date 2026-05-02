<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Admin;

use FP\DistributorMediaKit\Download\TrackingService;

/**
 * Gestione CPT fp_dmk_asset, tassonomie fp_dmk_category (tipo materiale) e fp_dmk_folder (cartelle), metabox e list table.
 *
 * CPT con capability dedicate (`fp_dmk_asset` / `fp_dmk_assets`) e tassonomia con `manage_fp_dmk_categories`,
 * così un ruolo dedicato può gestire asset senza `edit_posts` globale (menu Articoli).
 *
 * @package FP\DistributorMediaKit\Admin
 */
final class AssetManager {

	public const CPT = 'fp_dmk_asset';

	public const TAXONOMY = 'fp_dmk_category';

	public const TAXONOMY_FOLDER = 'fp_dmk_folder';

	public const META_FILE_ID     = '_fp_dmk_file_id';
	public const META_DESCRIPTION = '_fp_dmk_description';
	public const META_LANGUAGE    = '_fp_dmk_language';

	public const LANGUAGES = [ 'it' => 'IT', 'en' => 'EN' ];

	/**
	 * User meta: lingua predefinita pagina Caricamento multiplo (ultima scelta utente).
	 */
	public const USER_META_BULK_DEFAULT_LANGUAGE = 'fp_dmk_bulk_default_language';

	public const CATEGORY_SLUGS = [
		'visual-assets'     => 'Visual Assets',
		'tech-sheets'       => 'Tech Sheets',
		'copy-templates'    => 'Copy Templates',
		'brand-voice-guide' => 'Brand Voice Guide',
	];

	public static function init(): void {
		add_action( 'init', [ self::class, 'register_cpt' ] );
		add_action( 'init', [ self::class, 'register_taxonomy' ] );
		add_action( 'init', [ self::class, 'register_folder_taxonomy' ] );
		add_action( 'add_meta_boxes', [ self::class, 'add_meta_boxes' ] );
		add_action( 'save_post_' . self::CPT, [ self::class, 'save_meta' ], 10, 2 );
		add_action( 'save_post_' . self::CPT, [ self::class, 'normalize_single_folder_term' ], 30, 2 );
		add_filter( 'manage_' . self::CPT . '_posts_columns', [ self::class, 'columns' ] );
		add_action( 'manage_' . self::CPT . '_posts_custom_column', [ self::class, 'column_content' ], 10, 2 );
		add_filter( 'manage_edit-' . self::CPT . '_sortable_columns', [ self::class, 'sortable_columns' ] );
		add_action( 'transition_post_status', [ self::class, 'on_publish' ], 10, 3 );
		add_action( 'wp_ajax_fp_dmk_create_folder', [ self::class, 'ajax_create_folder' ] );
		add_action( 'wp_ajax_fp_dmk_create_asset_category', [ self::class, 'ajax_create_asset_category' ] );
		add_action( 'wp_ajax_fp_dmk_ensure_folder_paths', [ self::class, 'ajax_ensure_folder_paths' ] );
		add_action( 'wp_ajax_fp_dmk_rename_folder', [ self::class, 'ajax_rename_folder' ] );
		add_action( 'wp_ajax_fp_dmk_delete_folder', [ self::class, 'ajax_delete_folder' ] );
		add_action( 'wp_ajax_fp_dmk_move_folder', [ self::class, 'ajax_move_folder' ] );
		add_action( 'wp_ajax_fp_dmk_save_bulk_default_language', [ self::class, 'ajax_save_bulk_default_language' ] );
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
			'capability_type'     => [ 'fp_dmk_asset', 'fp_dmk_assets' ],
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
			'show_admin_column' => false,
			'show_in_rest'      => true,
			'rest_base'         => 'fp_dmk_category',
			'rewrite'           => false,
			'capabilities'      => [
				'manage_terms' => 'manage_fp_dmk_categories',
				'edit_terms'   => 'manage_fp_dmk_categories',
				'delete_terms' => 'manage_fp_dmk_categories',
				'assign_terms' => 'edit_fp_dmk_assets',
			],
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

	public static function register_folder_taxonomy(): void {
		$labels = [
			'name'          => _x( 'Cartelle', 'taxonomy general name', 'fp-dmk' ),
			'singular_name' => _x( 'Cartella', 'taxonomy singular name', 'fp-dmk' ),
			'menu_name'     => __( 'Cartelle', 'fp-dmk' ),
		];
		register_taxonomy(
			self::TAXONOMY_FOLDER,
			self::CPT,
			[
				'labels'            => $labels,
				'hierarchical'      => true,
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_admin_column' => false,
				'show_in_rest'      => false,
				'meta_box_cb'       => false,
				'rewrite'           => false,
				'capabilities'      => [
					'manage_terms' => 'manage_fp_dmk_categories',
					'edit_terms'   => 'manage_fp_dmk_categories',
					'delete_terms' => 'manage_fp_dmk_categories',
					'assign_terms' => 'edit_fp_dmk_assets',
				],
			]
		);
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
			<div class="fpdmk-field">
				<label for="fp_dmk_folder_term"><?php esc_html_e( 'Cartella', 'fp-dmk' ); ?></label>
				<select id="fp_dmk_folder_term" name="fp_dmk_folder_term" class="fpdmk-folder-select">
					<option value="0"><?php esc_html_e( '— Nessuna cartella —', 'fp-dmk' ); ?></option>
					<?php
					$sel_folder = 0;
					$cur_folder = self::get_primary_folder_term_for_post( $post->ID );
					if ( $cur_folder instanceof \WP_Term ) {
						$sel_folder = (int) $cur_folder->term_id;
					}
					foreach ( self::get_folder_terms_hierarchical_options() as $row ) {
						$t = $row['term'];
						if ( ! $t instanceof \WP_Term ) {
							continue;
						}
						$pad = str_repeat( '— ', $row['depth'] );
						?>
						<option value="<?php echo (int) $t->term_id; ?>" data-depth="<?php echo (int) $row['depth']; ?>" <?php selected( $sel_folder, (int) $t->term_id ); ?>><?php echo esc_html( $pad . $t->name ); ?></option>
						<?php
					}
					?>
				</select>
				<?php if ( current_user_can( 'manage_fp_dmk_categories' ) ) : ?>
					<div class="fpdmk-folder-new" data-target="#fp_dmk_folder_term">
						<button type="button" class="button fpdmk-folder-new-toggle"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Nuova cartella', 'fp-dmk' ); ?></button>
						<div class="fpdmk-folder-new-form" hidden>
							<input type="text" class="regular-text fpdmk-folder-new-name" placeholder="<?php esc_attr_e( 'Nome della cartella', 'fp-dmk' ); ?>">
							<select class="fpdmk-folder-new-parent">
								<option value="0"><?php esc_html_e( '— Nessun parent (radice) —', 'fp-dmk' ); ?></option>
								<?php foreach ( self::get_folder_terms_hierarchical_options() as $row ) : ?>
									<?php
									$t = $row['term'];
									if ( ! $t instanceof \WP_Term ) {
										continue;
									}
									$pad = str_repeat( '— ', $row['depth'] );
									?>
									<option value="<?php echo (int) $t->term_id; ?>"><?php echo esc_html( $pad . $t->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="button button-primary fpdmk-folder-new-save"><?php esc_html_e( 'Crea', 'fp-dmk' ); ?></button>
							<button type="button" class="button fpdmk-folder-new-cancel"><?php esc_html_e( 'Annulla', 'fp-dmk' ); ?></button>
							<span class="fpdmk-folder-new-msg" role="status" aria-live="polite"></span>
						</div>
					</div>
				<?php endif; ?>
				<span class="fpdmk-hint"><?php esc_html_e( 'Raggruppa l’asset nel Media Kit frontend. Puoi crearne una nuova al volo oppure dall’albero in Caricamento multiplo.', 'fp-dmk' ); ?></span>
			</div>
			<?php
			$material_terms = get_terms(
				[
					'taxonomy'               => self::TAXONOMY,
					'hide_empty'             => false,
					'orderby'                => 'name',
					'order'                  => 'ASC',
					'suppress_filters'       => true,
					'update_term_meta_cache' => false,
				]
			);
			if ( ! is_array( $material_terms ) || is_wp_error( $material_terms ) ) {
				$material_terms = [];
			}
			$assigned_cat_ids = wp_list_pluck( self::get_asset_category_terms( $post->ID ), 'term_id' );
			?>
			<div class="fpdmk-field fpdmk-field-material-cats">
				<span class="fpdmk-field-label-like"><?php esc_html_e( 'Categoria tipo materiale', 'fp-dmk' ); ?></span>
				<input type="hidden" name="fp_dmk_category_metabox_present" value="1" />
				<ul class="fpdmk-category-checklist">
					<?php foreach ( $material_terms as $ct ) : ?>
						<?php
						if ( ! $ct instanceof \WP_Term ) {
							continue;
						}
						$cid = (int) $ct->term_id;
						?>
						<li>
							<label>
								<input type="checkbox" name="fp_dmk_asset_category_ids[]" value="<?php echo esc_attr( (string) $cid ); ?>" <?php checked( in_array( $cid, $assigned_cat_ids, true ), true ); ?> />
								<?php echo esc_html( $ct->name ); ?>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="fpdmk-hint"><?php esc_html_e( 'Seleziona almeno una voce e clicca «Aggiorna»: così fp_dmk_category resta allineata anche se il pannello a blocchi non salva le tassonomie come previsto.', 'fp-dmk' ); ?></p>
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

			<?php if ( current_user_can( 'manage_fp_dmk_categories' ) ) : ?>
			var nonce    = <?php echo wp_json_encode( wp_create_nonce( 'fp_dmk_create_folder' ) ); ?>;
			var ajaxUrl  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var widget   = document.querySelector('.fpdmk-folder-new');
			if (widget) {
				var targetSel = document.querySelector(widget.dataset.target);
				var parentSel = widget.querySelector('.fpdmk-folder-new-parent');
				var toggle    = widget.querySelector('.fpdmk-folder-new-toggle');
				var form      = widget.querySelector('.fpdmk-folder-new-form');
				var nameInput = widget.querySelector('.fpdmk-folder-new-name');
				var saveBtn   = widget.querySelector('.fpdmk-folder-new-save');
				var cancelBtn = widget.querySelector('.fpdmk-folder-new-cancel');
				var msg       = widget.querySelector('.fpdmk-folder-new-msg');
				toggle.addEventListener('click', function() {
					form.hidden = false;
					toggle.hidden = true;
					nameInput.focus();
				});
				cancelBtn.addEventListener('click', function() {
					form.hidden = true;
					toggle.hidden = false;
					nameInput.value = '';
					msg.textContent = '';
				});
				saveBtn.addEventListener('click', function() {
					var name = (nameInput.value || '').trim();
					if (!name) { nameInput.focus(); return; }
					saveBtn.disabled = true;
					msg.textContent = <?php echo wp_json_encode( __( 'Creazione in corso…', 'fp-dmk' ) ); ?>;
					var body = new URLSearchParams();
					body.append('action', 'fp_dmk_create_folder');
					body.append('_nonce', nonce);
					body.append('name', name);
					body.append('parent', parentSel.value || '0');
					fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function(r){ return r.json(); }).then(function(res){
						saveBtn.disabled = false;
						if (!res || !res.success) {
							msg.textContent = (res && res.data && res.data.message) ? res.data.message : <?php echo wp_json_encode( __( 'Errore durante la creazione.', 'fp-dmk' ) ); ?>;
							return;
						}
						var d = res.data;
						var pad = ''; for (var i=0;i<d.depth;i++) pad += '— ';
						var optText = pad + d.name;
						var existing = targetSel.querySelector('option[value="' + d.term_id + '"]');
						if (!existing) {
							var opt = document.createElement('option');
							opt.value = d.term_id;
							opt.textContent = optText;
							opt.setAttribute('data-depth', d.depth);
							targetSel.appendChild(opt);
							var popt = document.createElement('option');
							popt.value = d.term_id;
							popt.textContent = optText;
							parentSel.appendChild(popt);
						}
						targetSel.value = String(d.term_id);
						nameInput.value = '';
						parentSel.value = '0';
						form.hidden = true;
						toggle.hidden = false;
						msg.textContent = d.existed ? <?php echo wp_json_encode( __( 'Cartella già esistente, selezionata.', 'fp-dmk' ) ); ?> : <?php echo wp_json_encode( __( 'Cartella creata e selezionata.', 'fp-dmk' ) ); ?>;
					}).catch(function(){
						saveBtn.disabled = false;
						msg.textContent = <?php echo wp_json_encode( __( 'Errore di rete.', 'fp-dmk' ) ); ?>;
					});
				});
			}
			<?php endif; ?>
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

		if ( isset( $_POST['fp_dmk_folder_term'] ) ) {
			$folder_id = absint( $_POST['fp_dmk_folder_term'] );
			if ( $folder_id > 0 ) {
				$t = get_term( $folder_id, self::TAXONOMY_FOLDER );
				if ( $t instanceof \WP_Term && ! is_wp_error( $t ) ) {
					wp_set_object_terms( $post_id, [ $folder_id ], self::TAXONOMY_FOLDER );
				}
			} else {
				wp_set_object_terms( $post_id, [], self::TAXONOMY_FOLDER );
			}
		}

		if ( isset( $_POST['fp_dmk_category_metabox_present'] ) && (string) wp_unslash( $_POST['fp_dmk_category_metabox_present'] ) === '1' ) {
			$cat_ids = [];
			if ( isset( $_POST['fp_dmk_asset_category_ids'] ) && is_array( $_POST['fp_dmk_asset_category_ids'] ) ) {
				$cat_ids = array_map( 'absint', wp_unslash( $_POST['fp_dmk_asset_category_ids'] ) );
			}
			$cat_ids = array_values( array_unique( array_filter( $cat_ids, static fn( int $x ): bool => $x > 0 ) ) );
			$valid   = [];
			foreach ( $cat_ids as $cid ) {
				$t = get_term( $cid, self::TAXONOMY );
				if ( $t instanceof \WP_Term && ! is_wp_error( $t ) ) {
					$valid[] = $cid;
				}
			}
			wp_set_object_terms( $post_id, $valid, self::TAXONOMY, false );
		}
	}

	/**
	 * Se più cartelle sono assegnate (es. da flussi alternativi), mantiene una sola (la più specifica in profondità).
	 */
	public static function normalize_single_folder_term( int $post_id, \WP_Post $post ): void {
		if ( $post->post_type !== self::CPT ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		$terms = wp_get_object_terms( $post_id, self::TAXONOMY_FOLDER );
		if ( is_wp_error( $terms ) || count( $terms ) <= 1 ) {
			return;
		}
		$best   = $terms[0];
		$best_d = self::folder_term_depth( $best );
		foreach ( $terms as $t ) {
			if ( ! $t instanceof \WP_Term ) {
				continue;
			}
			$d = self::folder_term_depth( $t );
			if ( $d > $best_d ) {
				$best_d = $d;
				$best   = $t;
			}
		}
		wp_set_object_terms( $post_id, [ (int) $best->term_id ], self::TAXONOMY_FOLDER );
	}

	/**
	 * Profondità del termine nella gerarchia cartelle (0 = radice).
	 */
	public static function folder_term_depth( \WP_Term $term ): int {
		$d = 0;
		$p = (int) $term->parent;
		while ( $p > 0 ) {
			$d++;
			$pt = get_term( $p, self::TAXONOMY_FOLDER );
			if ( ! $pt instanceof \WP_Term || is_wp_error( $pt ) ) {
				break;
			}
			$p = (int) $pt->parent;
		}
		return $d;
	}

	/**
	 * Cartella principale dell’asset (se più termini, quello più profondo nella gerarchia).
	 */
	public static function get_primary_folder_term_for_post( int $post_id ): ?\WP_Term {
		if ( $post_id <= 0 ) {
			return null;
		}
		$terms = get_the_terms( $post_id, self::TAXONOMY_FOLDER );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return null;
		}
		$best   = null;
		$best_d = -1;
		foreach ( $terms as $t ) {
			if ( ! $t instanceof \WP_Term ) {
				continue;
			}
			$d = self::folder_term_depth( $t );
			if ( $d > $best_d ) {
				$best_d = $d;
				$best   = $t;
			}
		}
		return $best;
	}

	/**
	 * Termini `fp_dmk_category` assegnati all’asset (solo lettura da DB, senza filtri/cache su get_terms).
	 *
	 * @return list<\WP_Term>
	 */
	public static function get_asset_category_terms( int $post_id ): array {
		if ( $post_id <= 0 ) {
			return [];
		}

		global $wpdb;
		$tax = self::TAXONOMY;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.parent, tt.count
				FROM {$wpdb->term_relationships} AS tr
				INNER JOIN {$wpdb->term_taxonomy} AS tt
					ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
				INNER JOIN {$wpdb->terms} AS t ON t.term_id = tt.term_id
				WHERE tr.object_id = %d
				ORDER BY t.name ASC",
				$tax,
				$post_id
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) || $rows === [] ) {
			return [];
		}

		$out   = [];
		$seen  = [];
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$tid = isset( $r['term_id'] ) ? (int) $r['term_id'] : 0;
			if ( $tid <= 0 || isset( $seen[ $tid ] ) ) {
				continue;
			}
			$seen[ $tid ] = true;
			$out[]        = new \WP_Term(
				(object) [
					'term_id'          => $tid,
					'name'             => (string) ( $r['name'] ?? '' ),
					'slug'             => (string) ( $r['slug'] ?? '' ),
					'taxonomy'         => $tax,
					'term_taxonomy_id' => (int) ( $r['term_taxonomy_id'] ?? 0 ),
					'parent'           => (int) ( $r['parent'] ?? 0 ),
					'count'            => (int) ( $r['count'] ?? 0 ),
					'filter'           => 'raw',
				]
			);
		}

		return $out;
	}

	/**
	 * Etichetta con percorso (Genitore › Figlio) per ordinamento e filtri.
	 */
	public static function get_folder_breadcrumb_label( \WP_Term $term ): string {
		$chain = get_ancestors( $term->term_id, self::TAXONOMY_FOLDER );
		$chain = array_reverse( array_map( 'absint', $chain ) );
		$parts = [];
		foreach ( $chain as $tid ) {
			$t = get_term( $tid, self::TAXONOMY_FOLDER );
			if ( $t instanceof \WP_Term && ! is_wp_error( $t ) ) {
				$parts[] = $t->name;
			}
		}
		$parts[] = $term->name;
		return implode( ' › ', $parts );
	}

	/**
	 * Termini cartella ordinati ad albero (per select admin).
	 *
	 * @return list<array{term: \WP_Term, depth: int}>
	 */
	public static function get_folder_terms_hierarchical_options(): array {
		$terms = get_terms(
			[
				'taxonomy'   => self::TAXONOMY_FOLDER,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);
		if ( ! is_array( $terms ) || is_wp_error( $terms ) ) {
			return [];
		}
		$by_parent = [];
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$by_parent[ (int) $term->parent ][] = $term;
		}
		$out = [];
		self::walk_folder_children( $by_parent, 0, 0, $out );
		return $out;
	}

	/**
	 * @param array<int, list<\WP_Term>> $by_parent
	 * @param list<array{term: \WP_Term, depth: int}> $out
	 */
	private static function walk_folder_children( array $by_parent, int $parent_id, int $depth, array &$out ): void {
		if ( empty( $by_parent[ $parent_id ] ) ) {
			return;
		}
		foreach ( $by_parent[ $parent_id ] as $term ) {
			$out[] = [ 'term' => $term, 'depth' => $depth ];
			self::walk_folder_children( $by_parent, (int) $term->term_id, $depth + 1, $out );
		}
	}

	/**
	 * Albero cartelle annidato per UI (explorer / JSON in admin).
	 *
	 * Include per ogni nodo:
	 *   - `count`: numero di asset assegnati direttamente a quella cartella
	 *   - `count_deep`: totale includendo tutti i discendenti (utile per mostrare "Cartella (123)")
	 *
	 * @return list<array{id:int,name:string,slug:string,count:int,count_deep:int,children:list<array<string,mixed>>}>
	 */
	public static function get_folder_tree_nested(): array {
		$terms = get_terms(
			[
				'taxonomy'   => self::TAXONOMY_FOLDER,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);
		if ( ! is_array( $terms ) || is_wp_error( $terms ) ) {
			return [];
		}
		$by_parent = [];
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$by_parent[ (int) $term->parent ][] = $term;
		}
		return self::build_folder_tree_nodes( $by_parent, 0 );
	}

	/**
	 * @param array<int, list<\WP_Term>> $by_parent
	 * @return list<array{id:int,name:string,slug:string,count:int,count_deep:int,children:list<array<string,mixed>>}>
	 */
	private static function build_folder_tree_nodes( array $by_parent, int $parent_id ): array {
		if ( empty( $by_parent[ $parent_id ] ) ) {
			return [];
		}
		$nodes = [];
		foreach ( $by_parent[ $parent_id ] as $t ) {
			if ( ! $t instanceof \WP_Term ) {
				continue;
			}
			$children      = self::build_folder_tree_nodes( $by_parent, (int) $t->term_id );
			$count_direct  = (int) $t->count;
			$count_deep    = $count_direct;
			foreach ( $children as $child ) {
				$count_deep += (int) $child['count_deep'];
			}
			$nodes[] = [
				'id'         => (int) $t->term_id,
				'name'       => $t->name,
				'slug'       => $t->slug,
				'count'      => $count_direct,
				'count_deep' => $count_deep,
				'children'   => $children,
			];
		}
		return $nodes;
	}

	public static function columns( array $columns ): array {
		unset( $columns[ 'taxonomy-' . self::TAXONOMY ] );
		$new = [];
		foreach ( $columns as $k => $v ) {
			$new[ $k ] = $v;
			if ( $k === 'title' ) {
				$new['fp_dmk_folder']      = __( 'Cartella', 'fp-dmk' );
				$new['fp_dmk_category']   = __( 'Categoria', 'fp-dmk' );
				$new['fp_dmk_language']   = __( 'Lingua', 'fp-dmk' );
				$new['fp_dmk_downloads'] = __( 'Download', 'fp-dmk' );
			}
		}

		return $new;
	}

	public static function column_content( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'fp_dmk_folder':
				$ft = self::get_primary_folder_term_for_post( $post_id );
				echo $ft instanceof \WP_Term ? esc_html( self::get_folder_breadcrumb_label( $ft ) ) : '—';
				break;
			case 'fp_dmk_category':
				$terms = self::get_asset_category_terms( $post_id );
				echo $terms !== [] ? esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) ) : '—';
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

	/**
	 * AJAX: crea una nuova cartella ({@see self::TAXONOMY_FOLDER}) con nome + parent opzionale
	 * e restituisce le informazioni del termine (compresa la profondità) così che il frontend
	 * possa aggiornare le `<select>` senza ricaricare la pagina.
	 *
	 * Se un termine con stesso nome/slug esiste già sotto lo stesso parent, lo restituisce
	 * con il flag `existed=true` invece di fallire: così l'utente può semplicemente selezionarlo
	 * senza ricevere un errore poco utile.
	 */
	public static function ajax_create_folder(): void {
		if ( ! check_ajax_referer( 'fp_dmk_create_folder', '_nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Nonce non valido.', 'fp-dmk' ) ], 400 );
		}
		if ( ! current_user_can( 'manage_fp_dmk_categories' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permesso negato.', 'fp-dmk' ) ], 403 );
		}

		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '';
		$parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;

		if ( $name === '' ) {
			wp_send_json_error( [ 'message' => __( 'Il nome della cartella è obbligatorio.', 'fp-dmk' ) ], 400 );
		}

		if ( $parent > 0 ) {
			$parent_term = get_term( $parent, self::TAXONOMY_FOLDER );
			if ( ! $parent_term instanceof \WP_Term || is_wp_error( $parent_term ) ) {
				wp_send_json_error( [ 'message' => __( 'Cartella superiore non valida.', 'fp-dmk' ) ], 400 );
			}
		}

		$result = wp_insert_term( $name, self::TAXONOMY_FOLDER, [ 'parent' => $parent ] );
		if ( is_wp_error( $result ) ) {
			$existing_id = $result->get_error_data( 'term_exists' );
			if ( $existing_id ) {
				$existing = get_term( (int) $existing_id, self::TAXONOMY_FOLDER );
				if ( $existing instanceof \WP_Term && ! is_wp_error( $existing ) ) {
					wp_send_json_success(
						[
							'term_id' => (int) $existing->term_id,
							'name'    => $existing->name,
							'parent'  => (int) $existing->parent,
							'depth'   => self::folder_term_depth( $existing ),
							'label'   => self::get_folder_breadcrumb_label( $existing ),
							'existed' => true,
						]
					);
				}
			}
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
		}

		$term_id = isset( $result['term_id'] ) ? (int) $result['term_id'] : 0;
		$term    = $term_id > 0 ? get_term( $term_id, self::TAXONOMY_FOLDER ) : null;
		if ( ! $term instanceof \WP_Term ) {
			wp_send_json_error( [ 'message' => __( 'Errore nel recupero del termine creato.', 'fp-dmk' ) ], 500 );
		}

		wp_send_json_success(
			[
				'term_id' => (int) $term->term_id,
				'name'    => $term->name,
				'parent'  => (int) $term->parent,
				'depth'   => self::folder_term_depth( $term ),
				'label'   => self::get_folder_breadcrumb_label( $term ),
				'existed' => false,
			]
		);
	}

	/**
	 * AJAX: crea una categoria asset ({@see self::TAXONOMY}) per uso dal caricamento multiplo senza menu tassonomie.
	 *
	 * Stesso livello di permesso della gestione categorie (`manage_fp_dmk_categories`).
	 */
	public static function ajax_create_asset_category(): void {
		if ( ! check_ajax_referer( 'fp_dmk_create_asset_category', '_nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Nonce non valido.', 'fp-dmk' ) ], 400 );
		}
		if ( ! current_user_can( 'manage_fp_dmk_categories' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permesso negato.', 'fp-dmk' ) ], 403 );
		}

		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '';
		$parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
		if ( $name === '' ) {
			wp_send_json_error( [ 'message' => __( 'Il nome della categoria è obbligatorio.', 'fp-dmk' ) ], 400 );
		}

		if ( $parent > 0 ) {
			$parent_term = get_term( $parent, self::TAXONOMY );
			if ( ! $parent_term instanceof \WP_Term || is_wp_error( $parent_term ) ) {
				wp_send_json_error( [ 'message' => __( 'Categoria superiore non valida.', 'fp-dmk' ) ], 400 );
			}
		}

		$result = wp_insert_term( $name, self::TAXONOMY, [ 'parent' => $parent ] );
		if ( is_wp_error( $result ) ) {
			$existing_id = $result->get_error_data( 'term_exists' );
			if ( $existing_id ) {
				$existing = get_term( (int) $existing_id, self::TAXONOMY );
				if ( $existing instanceof \WP_Term && ! is_wp_error( $existing ) ) {
					wp_send_json_success(
						[
							'term_id' => (int) $existing->term_id,
							'name'    => $existing->name,
							'label'   => self::get_category_breadcrumb_label( $existing ),
							'existed' => true,
						]
					);
				}
			}
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
		}

		$term_id = isset( $result['term_id'] ) ? (int) $result['term_id'] : 0;
		$term    = $term_id > 0 ? get_term( $term_id, self::TAXONOMY ) : null;
		if ( ! $term instanceof \WP_Term ) {
			wp_send_json_error( [ 'message' => __( 'Errore nel recupero della categoria creata.', 'fp-dmk' ) ], 500 );
		}

		wp_send_json_success(
			[
				'term_id' => (int) $term->term_id,
				'name'    => $term->name,
				'label'   => self::get_category_breadcrumb_label( $term ),
				'existed' => false,
			]
		);
	}

	/**
	 * AJAX: salva la lingua predefinita del caricamento multiplo nel profilo utente.
	 *
	 * Così al ricarico della pagina il selettore mantiene l’ultima scelta e le nuove righe
	 * ereditano la stessa lingua dal selettore predefinito (clone lato JS).
	 */
	public static function ajax_save_bulk_default_language(): void {
		if ( ! check_ajax_referer( 'fp_dmk_bulk_prefs', '_nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Nonce non valido.', 'fp-dmk' ) ], 400 );
		}
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permesso negato.', 'fp-dmk' ) ], 403 );
		}

		$lang = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['language'] ) ) : '';
		if ( ! in_array( $lang, array_keys( self::LANGUAGES ), true ) ) {
			wp_send_json_error( [ 'message' => __( 'Lingua non valida.', 'fp-dmk' ) ], 400 );
		}

		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Utente non valido.', 'fp-dmk' ) ], 403 );
		}

		update_user_meta( $uid, self::USER_META_BULK_DEFAULT_LANGUAGE, $lang );
		wp_send_json_success( [ 'language' => $lang ] );
	}

	/**
	 * Etichetta categoria con antenati (Genitore › Figlio) per select admin.
	 */
	public static function get_category_breadcrumb_label( \WP_Term $term ): string {
		$chain = get_ancestors( $term->term_id, self::TAXONOMY );
		$chain = array_reverse( array_map( 'absint', $chain ) );
		$parts = [];
		foreach ( $chain as $tid ) {
			$t = get_term( $tid, self::TAXONOMY );
			if ( $t instanceof \WP_Term && ! is_wp_error( $t ) ) {
				$parts[] = $t->name;
			}
		}
		$parts[] = $term->name;
		return implode( ' › ', $parts );
	}

	/**
	 * AJAX: crea/trova in batch la catena di cartelle corrispondente a uno o più percorsi.
	 *
	 * Input atteso (POST):
	 *   - `_nonce`: nonce `fp_dmk_create_folder` (stesso pool di `ajax_create_folder`)
	 *   - `paths`: array di array di stringhe (es. `[["2026","Q1"],["2026","Q2","Draft"]]`)
	 *
	 * Risposta: mappa `"segment/sub" => { term_id, depth, created, existed, parts }` con il
	 * percorso come chiave per consentire al frontend di associare i file alla cartella corretta.
	 *
	 * Usato dallo «Commit C» del bulk upload quando l'utente trascina una directory dal desktop:
	 * consente di ricreare lato WordPress la gerarchia delle sottocartelle prima di caricare i file.
	 */
	public static function ajax_ensure_folder_paths(): void {
		if ( ! check_ajax_referer( 'fp_dmk_create_folder', '_nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Nonce non valido.', 'fp-dmk' ) ], 400 );
		}
		if ( ! current_user_can( 'manage_fp_dmk_categories' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permesso negato.', 'fp-dmk' ) ], 403 );
		}

		$raw_paths = isset( $_POST['paths'] ) && is_array( $_POST['paths'] ) ? wp_unslash( $_POST['paths'] ) : [];
		if ( empty( $raw_paths ) ) {
			wp_send_json_error( [ 'message' => __( 'Nessun percorso fornito.', 'fp-dmk' ) ], 400 );
		}

		$parent_root = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
		if ( $parent_root > 0 ) {
			$root_term = get_term( $parent_root, self::TAXONOMY_FOLDER );
			if ( ! $root_term instanceof \WP_Term || is_wp_error( $root_term ) ) {
				$parent_root = 0;
			}
		}

		// Cache locale parent_id => [ lower(name) => term_id ] per evitare query ripetute.
		$children_cache = [];
		$results        = [];
		$created_nodes  = [];

		foreach ( $raw_paths as $path ) {
			if ( ! is_array( $path ) || empty( $path ) ) {
				continue;
			}
			$parent_id    = $parent_root;
			$clean_parts  = [];
			$any_created  = false;
			$any_existed  = true;
			foreach ( $path as $segment ) {
				$name = sanitize_text_field( (string) $segment );
				if ( $name === '' ) {
					continue;
				}
				$clean_parts[] = $name;
				$key           = strtolower( $name );

				if ( ! isset( $children_cache[ $parent_id ] ) ) {
					$children_cache[ $parent_id ] = [];
					$children                     = get_terms(
						[
							'taxonomy'   => self::TAXONOMY_FOLDER,
							'hide_empty' => false,
							'parent'     => $parent_id,
						]
					);
					if ( is_array( $children ) ) {
						foreach ( $children as $child ) {
							if ( $child instanceof \WP_Term ) {
								$children_cache[ $parent_id ][ strtolower( $child->name ) ] = (int) $child->term_id;
							}
						}
					}
				}

				if ( isset( $children_cache[ $parent_id ][ $key ] ) ) {
					$parent_id = $children_cache[ $parent_id ][ $key ];
					continue;
				}

				$insert = wp_insert_term( $name, self::TAXONOMY_FOLDER, [ 'parent' => $parent_id ] );
				if ( is_wp_error( $insert ) ) {
					$existing_id = $insert->get_error_data( 'term_exists' );
					if ( $existing_id ) {
						$parent_id                                   = (int) $existing_id;
						$children_cache[ $parent_id ][ $key ]        = $parent_id;
						continue;
					}
					wp_send_json_error(
						[
							'message' => sprintf(
								/* translators: 1: path segment, 2: error message */
								__( 'Errore creando la cartella «%1$s»: %2$s', 'fp-dmk' ),
								$name,
								$insert->get_error_message()
							),
						],
						400
					);
				}
				$new_id = isset( $insert['term_id'] ) ? (int) $insert['term_id'] : 0;
				if ( $new_id <= 0 ) {
					wp_send_json_error( [ 'message' => __( 'Errore creando la cartella.', 'fp-dmk' ) ], 500 );
				}
				$children_cache[ $parent_id ][ $key ] = $new_id;
				$parent_id                            = $new_id;
				$any_created                          = true;
				$any_existed                          = false;
				$new_term                             = get_term( $new_id, self::TAXONOMY_FOLDER );
				if ( $new_term instanceof \WP_Term ) {
					$created_nodes[] = [
						'term_id' => $new_id,
						'name'    => $new_term->name,
						'parent'  => (int) $new_term->parent,
						'depth'   => self::folder_term_depth( $new_term ),
					];
				}
			}
			if ( empty( $clean_parts ) ) {
				continue;
			}
			$path_key             = implode( '/', $clean_parts );
			$results[ $path_key ] = [
				'term_id' => $parent_id,
				'parts'   => $clean_parts,
				'created' => $any_created,
				'existed' => $any_existed,
			];
		}

		wp_send_json_success(
			[
				'paths'   => $results,
				'created' => $created_nodes,
			]
		);
	}

	/**
	 * AJAX: rinomina una cartella ({@see self::TAXONOMY_FOLDER}).
	 *
	 * Riutilizza il nonce `fp_dmk_create_folder`. Se esiste già una cartella con lo stesso
	 * nome/slug sotto lo stesso parent, ritorna errore leggibile invece di un WP_Error grezzo.
	 */
	public static function ajax_rename_folder(): void {
		if ( ! check_ajax_referer( 'fp_dmk_create_folder', '_nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Nonce non valido.', 'fp-dmk' ) ], 400 );
		}
		if ( ! current_user_can( 'manage_fp_dmk_categories' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permesso negato.', 'fp-dmk' ) ], 403 );
		}

		$term_id  = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$new_name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '';
		if ( $term_id <= 0 || $new_name === '' ) {
			wp_send_json_error( [ 'message' => __( 'Parametri non validi.', 'fp-dmk' ) ], 400 );
		}
		$term = get_term( $term_id, self::TAXONOMY_FOLDER );
		if ( ! $term instanceof \WP_Term || is_wp_error( $term ) ) {
			wp_send_json_error( [ 'message' => __( 'Cartella non trovata.', 'fp-dmk' ) ], 404 );
		}

		$result = wp_update_term( $term_id, self::TAXONOMY_FOLDER, [ 'name' => $new_name ] );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
		}
		$fresh = get_term( $term_id, self::TAXONOMY_FOLDER );
		if ( ! $fresh instanceof \WP_Term ) {
			wp_send_json_error( [ 'message' => __( 'Errore aggiornamento termine.', 'fp-dmk' ) ], 500 );
		}
		wp_send_json_success(
			[
				'term_id' => (int) $fresh->term_id,
				'name'    => $fresh->name,
				'parent'  => (int) $fresh->parent,
				'depth'   => self::folder_term_depth( $fresh ),
			]
		);
	}

	/**
	 * AJAX: elimina una cartella; richiede che non abbia sottocartelle.
	 *
	 * Parametri POST:
	 *   - `term_id`: id cartella
	 *   - `orphan_action`: come gestire gli asset contenuti. Valori ammessi:
	 *       - `move_to_parent` (default): gli asset vengono riassegnati alla cartella padre (o scollegati se root)
	 *       - `detach`: rimuove solo l'associazione cartella (asset restano ma senza cartella)
	 */
	public static function ajax_delete_folder(): void {
		if ( ! check_ajax_referer( 'fp_dmk_create_folder', '_nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Nonce non valido.', 'fp-dmk' ) ], 400 );
		}
		if ( ! current_user_can( 'manage_fp_dmk_categories' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permesso negato.', 'fp-dmk' ) ], 403 );
		}

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$orphan  = isset( $_POST['orphan_action'] ) ? sanitize_key( (string) $_POST['orphan_action'] ) : 'move_to_parent';
		if ( ! in_array( $orphan, [ 'move_to_parent', 'detach' ], true ) ) {
			$orphan = 'move_to_parent';
		}
		if ( $term_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Parametri non validi.', 'fp-dmk' ) ], 400 );
		}
		$term = get_term( $term_id, self::TAXONOMY_FOLDER );
		if ( ! $term instanceof \WP_Term || is_wp_error( $term ) ) {
			wp_send_json_error( [ 'message' => __( 'Cartella non trovata.', 'fp-dmk' ) ], 404 );
		}

		$children = get_terms(
			[
				'taxonomy'   => self::TAXONOMY_FOLDER,
				'hide_empty' => false,
				'parent'     => $term_id,
				'fields'     => 'ids',
			]
		);
		if ( ! is_wp_error( $children ) && is_array( $children ) && count( $children ) > 0 ) {
			wp_send_json_error(
				[
					'message' => sprintf(
						/* translators: %d: number of subfolders */
						__( 'La cartella contiene %d sottocartella/e: eliminale o spostale prima.', 'fp-dmk' ),
						count( $children )
					),
				],
				409
			);
		}

		// Gestione asset contenuti direttamente.
		$assets = get_posts(
			[
				'post_type'      => self::CPT,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => [
					[
						'taxonomy' => self::TAXONOMY_FOLDER,
						'field'    => 'term_id',
						'terms'    => [ $term_id ],
					],
				],
			]
		);
		$parent_id    = (int) $term->parent;
		$moved_assets = 0;
		if ( is_array( $assets ) && ! empty( $assets ) ) {
			foreach ( $assets as $post_id ) {
				if ( $orphan === 'move_to_parent' && $parent_id > 0 ) {
					wp_set_object_terms( (int) $post_id, [ $parent_id ], self::TAXONOMY_FOLDER, false );
				} else {
					wp_set_object_terms( (int) $post_id, [], self::TAXONOMY_FOLDER, false );
				}
				$moved_assets++;
			}
		}

		$deleted = wp_delete_term( $term_id, self::TAXONOMY_FOLDER );
		if ( is_wp_error( $deleted ) || $deleted === 0 || $deleted === false ) {
			wp_send_json_error(
				[
					'message' => is_wp_error( $deleted ) ? $deleted->get_error_message() : __( 'Impossibile eliminare la cartella.', 'fp-dmk' ),
				],
				500
			);
		}

		wp_send_json_success(
			[
				'term_id'      => $term_id,
				'parent'       => $parent_id,
				'moved_assets' => $moved_assets,
				'asset_count'  => is_array( $assets ) ? count( $assets ) : 0,
				'orphan_action'=> $orphan,
			]
		);
	}

	/**
	 * AJAX: sposta una cartella sotto un nuovo parent.
	 *
	 * Validazioni:
	 *   - il termine esiste nella taxonomy cartelle;
	 *   - il nuovo parent esiste (o è 0 = radice);
	 *   - il nuovo parent NON è il termine stesso né un suo discendente (evita cicli);
	 *   - se parent identico al corrente, no-op con `changed=false`.
	 */
	public static function ajax_move_folder(): void {
		if ( ! check_ajax_referer( 'fp_dmk_create_folder', '_nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Nonce non valido.', 'fp-dmk' ) ], 400 );
		}
		if ( ! current_user_can( 'manage_fp_dmk_categories' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permesso negato.', 'fp-dmk' ) ], 403 );
		}

		$term_id   = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$new_parent = isset( $_POST['new_parent'] ) ? absint( $_POST['new_parent'] ) : 0;
		if ( $term_id <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Parametri non validi.', 'fp-dmk' ) ], 400 );
		}
		$term = get_term( $term_id, self::TAXONOMY_FOLDER );
		if ( ! $term instanceof \WP_Term || is_wp_error( $term ) ) {
			wp_send_json_error( [ 'message' => __( 'Cartella non trovata.', 'fp-dmk' ) ], 404 );
		}

		if ( (int) $term->parent === $new_parent ) {
			wp_send_json_success(
				[
					'term_id' => $term_id,
					'parent'  => $new_parent,
					'depth'   => self::folder_term_depth( $term ),
					'changed' => false,
				]
			);
		}

		if ( $new_parent > 0 ) {
			if ( $new_parent === $term_id ) {
				wp_send_json_error( [ 'message' => __( 'Non puoi spostare una cartella dentro se stessa.', 'fp-dmk' ) ], 400 );
			}
			$parent_term = get_term( $new_parent, self::TAXONOMY_FOLDER );
			if ( ! $parent_term instanceof \WP_Term || is_wp_error( $parent_term ) ) {
				wp_send_json_error( [ 'message' => __( 'Cartella di destinazione non trovata.', 'fp-dmk' ) ], 404 );
			}
			// Verifica anti-ciclo: il nuovo parent non deve essere un discendente del termine.
			$ancestors = get_ancestors( $new_parent, self::TAXONOMY_FOLDER, 'taxonomy' );
			if ( in_array( $term_id, array_map( 'absint', (array) $ancestors ), true ) ) {
				wp_send_json_error(
					[ 'message' => __( 'Non puoi spostare una cartella dentro una delle sue sottocartelle.', 'fp-dmk' ) ],
					400
				);
			}
		}

		$updated = wp_update_term( $term_id, self::TAXONOMY_FOLDER, [ 'parent' => $new_parent ] );
		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( [ 'message' => $updated->get_error_message() ], 400 );
		}
		$fresh = get_term( $term_id, self::TAXONOMY_FOLDER );
		if ( ! $fresh instanceof \WP_Term ) {
			wp_send_json_error( [ 'message' => __( 'Errore aggiornamento termine.', 'fp-dmk' ) ], 500 );
		}

		// La cache degli ancestor/breadcrumb per i discendenti viene invalidata da core WP,
		// ma per sicurezza puliamo la cache della taxonomy.
		clean_term_cache( $term_id, self::TAXONOMY_FOLDER, true );

		wp_send_json_success(
			[
				'term_id' => (int) $fresh->term_id,
				'parent'  => (int) $fresh->parent,
				'depth'   => self::folder_term_depth( $fresh ),
				'changed' => true,
			]
		);
	}
}
