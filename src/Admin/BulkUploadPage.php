<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Admin;

/**
 * Caricamento multiplo di asset nel Media Kit.
 *
 * Layout tipo explorer: albero cartelle, zona drag-and-drop (REST verso la
 * Libreria media), selezione da wp.media e trascinamento righe sulle cartelle.
 *
 * @package FP\DistributorMediaKit\Admin
 */
final class BulkUploadPage {

	public const PAGE_SLUG = 'fp-dmk-bulk-upload';

	private const SCRIPT_HANDLE = 'fp-dmk-bulk-upload';

	/**
	 * MIME consentiti per upload (stesso filtro del metabox asset).
	 *
	 * @return list<string>
	 */
	private static function allowed_upload_mimes(): array {
		return array_values(
			apply_filters(
				'fp_dmk_allowed_mime_types',
				[
					'application/pdf',
					'image/jpeg',
					'image/png',
					'image/gif',
					'image/webp',
					'image/svg+xml',
					'video/mp4',
					'video/webm',
					'text/plain',
				]
			)
		);
	}

	public function __construct() {
		add_action( 'admin_init', [ $this, 'handle_save' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Libreria media, script bulk e configurazione REST (upload fino a 3 paralleli lato client).
	 */
	public function enqueue( string $hook ): void {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page !== self::PAGE_SLUG ) {
			return;
		}
		wp_enqueue_media();
		wp_register_script(
			self::SCRIPT_HANDLE,
			FP_DMK_URL . 'assets/js/bulk-upload.js',
			[ 'jquery', 'wp-util' ],
			FP_DMK_VERSION,
			true
		);
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'fpdmkBulk',
			[
				'restMediaUrl'     => esc_url_raw( rest_url( 'wp/v2/media' ) ),
				'restNonce'        => wp_create_nonce( 'wp_rest' ),
				'allowedMimes'     => self::allowed_upload_mimes(),
				'folderNonce'      => wp_create_nonce( 'fp_dmk_create_folder' ),
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'canCreateFolders'   => current_user_can( 'manage_fp_dmk_categories' ),
				'canCreateCategories' => current_user_can( 'manage_fp_dmk_categories' ),
				'categoryNonce'      => wp_create_nonce( 'fp_dmk_create_asset_category' ),
				'folderTree'         => AssetManager::get_folder_tree_nested(),
				'i18n'               => [
					'filesLabel'        => __( 'file', 'fp-dmk' ),
					'rootFolder'        => __( 'Radice (nessuna cartella)', 'fp-dmk' ),
					'dropTitle'         => __( 'Trascina qui i file o clicca per sfogliare', 'fp-dmk' ),
					'dropHint'          => __( 'Caricamento diretto nella Libreria media (fino a 3 file in parallelo). Tipi consentiti come per gli asset.', 'fp-dmk' ),
					'mediaTitle'        => __( 'Seleziona file per il caricamento multiplo', 'fp-dmk' ),
					'mediaButton'       => __( 'Aggiungi alla lista', 'fp-dmk' ),
					'uploading'         => __( 'Caricamento in corso…', 'fp-dmk' ),
					'uploadDone'        => __( 'Caricamento completato.', 'fp-dmk' ),
					'noValidFiles'      => __( 'Nessun file compatibile con i tipi consentiti.', 'fp-dmk' ),
					'skippedType'       => __( 'Ignorati %d file (tipo non consentito).', 'fp-dmk' ),
					'creatingFolder'    => __( 'Creazione in corso…', 'fp-dmk' ),
					'folderCreateError' => __( 'Errore durante la creazione.', 'fp-dmk' ),
					'folderExists'      => __( 'Cartella già esistente, selezionata.', 'fp-dmk' ),
					'folderCreated'     => __( 'Cartella creata e selezionata.', 'fp-dmk' ),
					'networkError'      => __( 'Errore di rete.', 'fp-dmk' ),
					'treeLabel'         => __( 'Struttura cartelle Media Kit', 'fp-dmk' ),
					'filterByFolder'    => __( 'Mostra solo le righe con questa cartella', 'fp-dmk' ),
					'dragRowHint'       => __( 'Trascina una riga su una cartella per assegnarla.', 'fp-dmk' ),
					'confirmBulkRemove' => __( 'Rimuovere %d file dalla coda?', 'fp-dmk' ),
					'confirmDirectoryDrop' => __( 'Caricare %1$d file creando %2$d cartella/e ricalcate dalla struttura?', 'fp-dmk' ),
					'creatingFolders'   => __( 'Creazione cartelle in corso…', 'fp-dmk' ),
					'folderCreateDenied' => __( 'Non hai i permessi per creare cartelle: verranno caricati solo i file al livello principale.', 'fp-dmk' ),
					'renameFolder'      => __( 'Rinomina cartella', 'fp-dmk' ),
					'deleteFolder'      => __( 'Elimina cartella', 'fp-dmk' ),
					'deleteEmpty'       => __( 'Eliminare la cartella «%s»?', 'fp-dmk' ),
					'deleteWithAssets'  => __( 'La cartella «%1$s» contiene %2$d asset. Verranno spostati nella cartella superiore (o scollegati, se radice). Procedere?', 'fp-dmk' ),
					'deleteHasChildren' => __( 'Elimina prima le sottocartelle oppure spostale altrove.', 'fp-dmk' ),
					'dragGhost'           => __( '%d file selezionati', 'fp-dmk' ),
					'creatingCategory'    => __( 'Creazione categoria in corso…', 'fp-dmk' ),
					'categoryCreateError' => __( 'Errore durante la creazione della categoria.', 'fp-dmk' ),
					'categoryExists'      => __( 'Categoria già esistente, selezionata.', 'fp-dmk' ),
					'categoryCreated'     => __( 'Categoria creata e selezionata.', 'fp-dmk' ),
				],
			]
		);
		wp_enqueue_script( self::SCRIPT_HANDLE );
	}

	public function handle_save(): void {
		if ( ! isset( $_POST['fp_dmk_bulk_upload'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}
		if ( ! isset( $_POST['fp_dmk_bulk_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_dmk_bulk_nonce'] ) ), 'fp_dmk_bulk_upload' ) ) {
			return;
		}

		$items = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : [];

		$created = 0;
		$errors  = 0;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$attachment_id = isset( $item['attachment_id'] ) ? absint( $item['attachment_id'] ) : 0;
			if ( $attachment_id <= 0 ) {
				continue;
			}
			$attachment = get_post( $attachment_id );
			if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
				$errors++;
				continue;
			}

			$title = isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '';
			if ( $title === '' ) {
				$title = (string) $attachment->post_title;
			}
			if ( $title === '' ) {
				$title = sprintf( /* translators: %d: attachment ID */ __( 'Asset %d', 'fp-dmk' ), $attachment_id );
			}

			$description = isset( $item['description'] ) ? sanitize_textarea_field( (string) $item['description'] ) : '';

			$language = isset( $item['language'] ) && in_array( (string) $item['language'], array_keys( AssetManager::LANGUAGES ), true )
				? sanitize_text_field( (string) $item['language'] )
				: 'it';

			$folder_id = isset( $item['folder_term'] ) ? absint( $item['folder_term'] ) : 0;

			$category_ids = [];
			if ( isset( $item['categories'] ) && is_array( $item['categories'] ) ) {
				foreach ( $item['categories'] as $raw ) {
					$cid = absint( $raw );
					if ( $cid > 0 ) {
						$category_ids[] = $cid;
					}
				}
				$category_ids = array_values( array_unique( $category_ids ) );
			}

			$post_id = wp_insert_post(
				[
					'post_type'   => AssetManager::CPT,
					'post_title'  => $title,
					'post_status' => 'publish',
				],
				true
			);
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$errors++;
				continue;
			}

			update_post_meta( $post_id, AssetManager::META_FILE_ID, $attachment_id );
			update_post_meta( $post_id, AssetManager::META_DESCRIPTION, $description );
			update_post_meta( $post_id, AssetManager::META_LANGUAGE, $language );

			if ( ! empty( $category_ids ) ) {
				wp_set_object_terms( $post_id, $category_ids, AssetManager::TAXONOMY, false );
			}
			if ( $folder_id > 0 ) {
				$folder_term = get_term( $folder_id, AssetManager::TAXONOMY_FOLDER );
				if ( $folder_term instanceof \WP_Term && ! is_wp_error( $folder_term ) ) {
					wp_set_object_terms( $post_id, [ $folder_id ], AssetManager::TAXONOMY_FOLDER, false );
				}
			}

			$created++;
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'fp_dmk_bulk_created' => $created,
					'fp_dmk_bulk_errors'  => $errors,
				],
				admin_url( 'admin.php?page=' . self::PAGE_SLUG )
			)
		);
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}

		$folder_rows = AssetManager::get_folder_terms_hierarchical_options();
		$cat_terms   = get_terms(
			[
				'taxonomy'   => AssetManager::TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);
		if ( is_wp_error( $cat_terms ) || ! is_array( $cat_terms ) ) {
			$cat_terms = [];
		}

		$file_accept = '.pdf,.jpg,.jpeg,.png,.gif,.webp,.svg,.mp4,.webm,.txt';

		$created = isset( $_GET['fp_dmk_bulk_created'] ) ? absint( $_GET['fp_dmk_bulk_created'] ) : 0;
		$errors  = isset( $_GET['fp_dmk_bulk_errors'] ) ? absint( $_GET['fp_dmk_bulk_errors'] ) : 0;
		?>
		<div class="wrap fpdmk-admin-page fpdmk-bulk-upload-page">
			<h1 class="screen-reader-text"><?php esc_html_e( 'FP Media Kit', 'fp-dmk' ); ?></h1>
			<div class="fpdmk-page-header">
				<div class="fpdmk-page-header-content">
					<h2 class="fpdmk-page-header-title" aria-hidden="true"><span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Caricamento multiplo', 'fp-dmk' ); ?></h2>
					<p><?php esc_html_e( 'Esplora le cartelle a sinistra: sotto il percorso vedi in tempo reale i file in coda per la cartella selezionata (come elenco remoto in un client FTP). Trascina nella zona centrale o usa la Libreria media; puoi trascinare una riga su una cartella per assegnarla.', 'fp-dmk' ); ?></p>
				</div>
				<span class="fpdmk-page-header-badge">v<?php echo esc_html( FP_DMK_VERSION ); ?></span>
			</div>

			<?php if ( $created > 0 ) : ?>
				<div class="fpdmk-alert fpdmk-alert-success">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php echo esc_html( sprintf( /* translators: %d: number of created assets */ _n( 'Creato %d asset.', 'Creati %d asset.', $created, 'fp-dmk' ), $created ) ); ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . AssetManager::CPT ) ); ?>"><?php esc_html_e( 'Vai alla lista asset', 'fp-dmk' ); ?></a>
				</div>
			<?php endif; ?>
			<?php if ( $errors > 0 ) : ?>
				<div class="fpdmk-alert fpdmk-alert-danger">
					<span class="dashicons dashicons-warning"></span>
					<?php echo esc_html( sprintf( /* translators: %d: number of failed items */ _n( '%d file non caricato (errore).', '%d file non caricati (errori).', $errors, 'fp-dmk' ), $errors ) ); ?>
				</div>
			<?php endif; ?>

			<form method="post" id="fpdmk-bulk-form">
				<?php wp_nonce_field( 'fp_dmk_bulk_upload', 'fp_dmk_bulk_nonce' ); ?>
				<input type="hidden" name="fp_dmk_bulk_upload" value="1">

				<div class="fpdmk-card">
					<div class="fpdmk-card-header">
						<div class="fpdmk-card-header-left">
							<span class="dashicons dashicons-admin-generic"></span>
							<h2><?php esc_html_e( 'Impostazioni predefinite', 'fp-dmk' ); ?></h2>
						</div>
						<span class="fpdmk-badge fpdmk-badge-neutral"><?php esc_html_e( 'Lingua e categorie per le nuove righe', 'fp-dmk' ); ?></span>
					</div>
					<div class="fpdmk-card-body">
						<p class="description"><?php esc_html_e( 'La cartella di destinazione si sceglie dall’albero a sinistra. Lingua e categorie qui sotto vengono copiate in ogni nuova riga (modificabili per file).', 'fp-dmk' ); ?></p>
						<div class="fpdmk-fields-grid">
							<div class="fpdmk-field">
								<label for="fpdmk_bulk_default_language"><?php esc_html_e( 'Lingua', 'fp-dmk' ); ?></label>
								<select id="fpdmk_bulk_default_language">
									<?php foreach ( AssetManager::LANGUAGES as $code => $label ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="fpdmk-field fpdmk-field-fw">
								<label for="fpdmk_bulk_default_categories"><?php esc_html_e( 'Categorie', 'fp-dmk' ); ?></label>
								<select id="fpdmk_bulk_default_categories" multiple size="4">
									<?php foreach ( $cat_terms as $term ) : ?>
										<?php if ( ! $term instanceof \WP_Term ) { continue; } ?>
										<option value="<?php echo (int) $term->term_id; ?>"><?php echo esc_html( $term->name ); ?></option>
									<?php endforeach; ?>
								</select>
								<span class="fpdmk-hint"><?php esc_html_e( 'Tieni premuto Ctrl/Cmd per selezione multipla.', 'fp-dmk' ); ?></span>
								<?php if ( current_user_can( 'manage_fp_dmk_categories' ) ) : ?>
									<div class="fpdmk-category-new fpdmk-folder-new-sidebar" data-target="#fpdmk_bulk_default_categories">
										<button type="button" class="button fpdmk-category-new-toggle"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Nuova categoria', 'fp-dmk' ); ?></button>
										<div class="fpdmk-category-new-form" hidden>
											<input type="text" class="regular-text fpdmk-category-new-name" placeholder="<?php esc_attr_e( 'Nome categoria', 'fp-dmk' ); ?>">
											<select class="fpdmk-category-new-parent">
												<option value="0"><?php esc_html_e( '— Radice (nessun genitore) —', 'fp-dmk' ); ?></option>
												<?php foreach ( $cat_terms as $term ) : ?>
													<?php
													if ( ! $term instanceof \WP_Term ) {
														continue;
													}
													$depth = count( get_ancestors( $term->term_id, AssetManager::TAXONOMY ) );
													$pad   = str_repeat( '— ', $depth );
													?>
													<option value="<?php echo (int) $term->term_id; ?>"><?php echo esc_html( $pad . $term->name ); ?></option>
												<?php endforeach; ?>
											</select>
											<button type="button" class="button button-primary fpdmk-category-new-save"><?php esc_html_e( 'Crea', 'fp-dmk' ); ?></button>
											<button type="button" class="button fpdmk-category-new-cancel"><?php esc_html_e( 'Annulla', 'fp-dmk' ); ?></button>
											<span class="fpdmk-category-new-msg" role="status" aria-live="polite"></span>
										</div>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>

				<div class="fpdmk-card fpdmk-bulk-workspace-card">
					<div class="fpdmk-card-header">
						<div class="fpdmk-card-header-left">
							<span class="dashicons dashicons-portfolio"></span>
							<h2><?php esc_html_e( 'File e cartelle', 'fp-dmk' ); ?></h2>
						</div>
						<span class="fpdmk-badge fpdmk-badge-neutral" id="fpdmk-bulk-count">0 <?php esc_html_e( 'file', 'fp-dmk' ); ?></span>
					</div>
					<div class="fpdmk-card-body fpdmk-bulk-workspace">
						<aside class="fpdmk-bulk-sidebar" aria-label="<?php esc_attr_e( 'Struttura cartelle Media Kit', 'fp-dmk' ); ?>">
							<div class="fpdmk-bulk-sidebar-title"><?php esc_html_e( 'Cartelle', 'fp-dmk' ); ?></div>
							<div id="fpdmk-bulk-tree" class="fpdmk-bulk-tree" role="tree"></div>
							<?php if ( current_user_can( 'manage_fp_dmk_categories' ) ) : ?>
								<div class="fpdmk-folder-new fpdmk-folder-new-sidebar" data-target="#fpdmk_bulk_default_folder">
									<button type="button" class="button fpdmk-folder-new-toggle"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Nuova cartella', 'fp-dmk' ); ?></button>
									<div class="fpdmk-folder-new-form" hidden>
										<input type="text" class="regular-text fpdmk-folder-new-name" placeholder="<?php esc_attr_e( 'Nome della cartella', 'fp-dmk' ); ?>">
										<select class="fpdmk-folder-new-parent">
											<option value="0"><?php esc_html_e( '— Nessun parent (radice) —', 'fp-dmk' ); ?></option>
											<?php foreach ( $folder_rows as $row ) : ?>
												<?php
												$t = $row['term'];
												if ( ! $t instanceof \WP_Term ) {
													continue;
												}
												$pad = str_repeat( '— ', (int) $row['depth'] );
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
						</aside>
						<div class="fpdmk-bulk-pane">
							<div class="fpdmk-bulk-toolbar">
								<button type="button" class="fpdmk-btn fpdmk-btn-secondary" id="fpdmk-bulk-pick"><span class="dashicons dashicons-admin-media"></span> <?php esc_html_e( 'Libreria media', 'fp-dmk' ); ?></button>
								<nav id="fpdmk-bulk-breadcrumb" class="fpdmk-bulk-breadcrumb" aria-label="<?php esc_attr_e( 'Percorso cartella selezionata', 'fp-dmk' ); ?>"></nav>
								<label class="fpdmk-bulk-filter-label">
									<input type="checkbox" id="fpdmk-bulk-filter-folder" value="1">
									<?php esc_html_e( 'Filtra elenco per cartella selezionata', 'fp-dmk' ); ?>
								</label>
							</div>
							<p class="description fpdmk-bulk-drag-hint"><?php esc_html_e( 'Trascina una riga della tabella su una cartella per impostare la cartella dell’asset; trascina una cartella su un’altra per spostarla (o sulla radice per portarla al livello superiore).', 'fp-dmk' ); ?></p>
							<div class="fpdmk-bulk-fz-remote" aria-labelledby="fpdmk-bulk-fz-remote-heading">
								<div class="fpdmk-bulk-fz-remote-head">
									<span id="fpdmk-bulk-fz-remote-heading" class="fpdmk-bulk-fz-remote-title"><?php esc_html_e( 'File in coda per questa cartella', 'fp-dmk' ); ?></span>
									<span id="fpdmk-bulk-fz-remote-count" class="fpdmk-bulk-fz-remote-count" aria-live="polite">0</span>
								</div>
								<div class="fpdmk-bulk-fz-remote-scroll">
									<table class="fpdmk-bulk-fz-table" id="fpdmk-bulk-fz-remote-table">
										<thead>
											<tr>
												<th scope="col"><?php esc_html_e( 'Nome file', 'fp-dmk' ); ?></th>
												<th scope="col"><?php esc_html_e( 'Tipo', 'fp-dmk' ); ?></th>
											</tr>
										</thead>
										<tbody id="fpdmk-bulk-fz-remote-tbody"></tbody>
									</table>
								</div>
								<p id="fpdmk-bulk-fz-remote-empty" class="description fpdmk-bulk-fz-remote-empty" hidden><?php esc_html_e( 'Nessun file in coda con questa cartella assegnata.', 'fp-dmk' ); ?></p>
							</div>
							<div id="fpdmk-bulk-dropzone" class="fpdmk-bulk-dropzone" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'Zona caricamento file', 'fp-dmk' ); ?>">
								<span class="dashicons dashicons-upload fpdmk-bulk-dropzone-icon" aria-hidden="true"></span>
								<strong class="fpdmk-bulk-dropzone-title"><?php esc_html_e( 'Trascina qui i file o clicca per sfogliare', 'fp-dmk' ); ?></strong>
								<span class="fpdmk-bulk-dropzone-hint"><?php esc_html_e( 'Upload nella Libreria media (max 3 in parallelo). Se trascini cartelle, la struttura viene ricreata automaticamente. Tipi: PDF, immagini, SVG, MP4, WebM, TXT.', 'fp-dmk' ); ?></span>
							</div>
							<input type="file" id="fpdmk-bulk-file-input" class="fpdmk-bulk-file-input-hidden" multiple accept="<?php echo esc_attr( $file_accept ); ?>">
							<p id="fpdmk-bulk-status" class="fpdmk-bulk-status" role="status" aria-live="polite"></p>
							<div id="fpdmk-bulk-empty" class="description fpdmk-bulk-empty-msg"><?php esc_html_e( 'Nessun file in coda. Usa la zona sopra o la Libreria media.', 'fp-dmk' ); ?></div>
							<div id="fpdmk-bulk-bulkbar" class="fpdmk-bulk-bulkbar is-hidden" role="toolbar" aria-label="<?php esc_attr_e( 'Azioni in blocco', 'fp-dmk' ); ?>">
								<span class="fpdmk-bulk-bulkbar-count"><span id="fpdmk-bulk-sel-count">0</span> <?php esc_html_e( 'selezionati', 'fp-dmk' ); ?></span>
								<span class="fpdmk-bulk-bulkbar-sep" aria-hidden="true">·</span>
								<button type="button" class="button fpdmk-bulk-action" data-action="set-folder"><span class="dashicons dashicons-portfolio"></span> <?php esc_html_e( 'Sposta in cartella selezionata', 'fp-dmk' ); ?></button>
								<button type="button" class="button fpdmk-bulk-action" data-action="set-language"><span class="dashicons dashicons-translation"></span> <?php esc_html_e( 'Imposta lingua predefinita', 'fp-dmk' ); ?></button>
								<button type="button" class="button fpdmk-bulk-action" data-action="set-categories"><span class="dashicons dashicons-category"></span> <?php esc_html_e( 'Applica categorie predefinite', 'fp-dmk' ); ?></button>
								<button type="button" class="button fpdmk-bulk-action is-danger" data-action="remove"><span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Rimuovi', 'fp-dmk' ); ?></button>
							</div>
							<div class="fpdmk-bulk-table-wrap">
								<table class="fpdmk-table fpdmk-bulk-table is-hidden" id="fpdmk-bulk-table">
									<thead>
										<tr>
											<th class="fpdmk-bulk-col-check">
												<label class="screen-reader-text" for="fpdmk-bulk-master-check"><?php esc_html_e( 'Seleziona tutto', 'fp-dmk' ); ?></label>
												<input type="checkbox" id="fpdmk-bulk-master-check">
											</th>
											<th class="fpdmk-bulk-col-file fpdmk-bulk-sortable" data-sort-key="file" scope="col"><button type="button" class="fpdmk-bulk-sort-btn"><?php esc_html_e( 'File', 'fp-dmk' ); ?><span class="fpdmk-bulk-sort-indicator" aria-hidden="true"></span></button></th>
											<th class="fpdmk-bulk-col-title fpdmk-bulk-sortable" data-sort-key="title" scope="col"><button type="button" class="fpdmk-bulk-sort-btn"><?php esc_html_e( 'Titolo', 'fp-dmk' ); ?><span class="fpdmk-bulk-sort-indicator" aria-hidden="true"></span></button></th>
											<th class="fpdmk-bulk-col-desc" scope="col"><?php esc_html_e( 'Descrizione', 'fp-dmk' ); ?></th>
											<th class="fpdmk-bulk-col-lang fpdmk-bulk-sortable" data-sort-key="language" scope="col"><button type="button" class="fpdmk-bulk-sort-btn"><?php esc_html_e( 'Lingua', 'fp-dmk' ); ?><span class="fpdmk-bulk-sort-indicator" aria-hidden="true"></span></button></th>
											<th class="fpdmk-bulk-col-folder fpdmk-bulk-sortable" data-sort-key="folder" scope="col"><button type="button" class="fpdmk-bulk-sort-btn"><?php esc_html_e( 'Cartella', 'fp-dmk' ); ?><span class="fpdmk-bulk-sort-indicator" aria-hidden="true"></span></button></th>
											<th class="fpdmk-bulk-col-cats" scope="col"><?php esc_html_e( 'Categorie', 'fp-dmk' ); ?></th>
											<th class="fpdmk-bulk-col-actions" aria-label="<?php esc_attr_e( 'Azioni', 'fp-dmk' ); ?>" scope="col"></th>
										</tr>
									</thead>
									<tbody></tbody>
								</table>
							</div>
						</div>
					</div>
				</div>

				<div class="fpdmk-field screen-reader-text">
					<label for="fpdmk_bulk_default_folder"><?php esc_html_e( 'Cartella predefinita (sincronizzata con l’albero)', 'fp-dmk' ); ?></label>
					<select id="fpdmk_bulk_default_folder" class="fpdmk-folder-select" tabindex="-1" aria-hidden="true">
						<option value="0" data-depth="0"><?php esc_html_e( '— Nessuna cartella —', 'fp-dmk' ); ?></option>
						<?php foreach ( $folder_rows as $row ) : ?>
							<?php
							$t = $row['term'];
							if ( ! $t instanceof \WP_Term ) {
								continue;
							}
							$pad = str_repeat( '— ', (int) $row['depth'] );
							?>
							<option value="<?php echo (int) $t->term_id; ?>" data-depth="<?php echo (int) $row['depth']; ?>"><?php echo esc_html( $pad . $t->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="fpdmk-card">
					<div class="fpdmk-card-body fpdmk-bulk-footer-actions">
						<button type="submit" class="fpdmk-btn fpdmk-btn-primary" id="fpdmk-bulk-submit" disabled><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Crea asset', 'fp-dmk' ); ?></button>
						<span class="fpdmk-hint"><?php esc_html_e( 'Gli asset vengono creati in stato pubblicato.', 'fp-dmk' ); ?></span>
					</div>
				</div>
			</form>

			<script type="text/html" id="tmpl-fpdmk-bulk-row">
				<tr data-row-id="{{ data.rowId }}">
					<td class="fpdmk-bulk-col-check">
						<label class="screen-reader-text"><?php esc_html_e( 'Seleziona riga', 'fp-dmk' ); ?></label>
						<input type="checkbox" class="fpdmk-bulk-row-check">
					</td>
					<td>
						<input type="hidden" name="items[{{ data.rowId }}][attachment_id]" value="{{ data.id }}">
						<strong>{{ data.filename }}</strong>
						<br><span class="fpdmk-hint">#{{ data.id }} · {{ data.mime }}</span>
					</td>
					<td>
						<input type="text" class="regular-text" name="items[{{ data.rowId }}][title]" value="{{ data.title }}">
					</td>
					<td>
						<textarea rows="2" class="large-text" name="items[{{ data.rowId }}][description]">{{ data.description }}</textarea>
					</td>
					<td class="fpdmk-bulk-lang-cell"></td>
					<td class="fpdmk-bulk-folder-cell"></td>
					<td class="fpdmk-bulk-cats-cell"></td>
					<td>
						<button type="button" class="fpdmk-btn fpdmk-btn-secondary fpdmk-bulk-remove" aria-label="<?php esc_attr_e( 'Rimuovi', 'fp-dmk' ); ?>"><span class="dashicons dashicons-trash"></span></button>
					</td>
				</tr>
			</script>
		</div>
		<?php
	}
}
