<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Admin;

/**
 * Caricamento multiplo di asset nel Media Kit.
 *
 * Permette di selezionare N file dalla Libreria media in un'unica sessione e
 * creare un CPT `fp_dmk_asset` pubblicato per ciascun allegato, con override
 * per-file di titolo, descrizione, lingua, cartella e categorie rispetto ai
 * valori predefiniti selezionati in testa alla pagina.
 *
 * Viene innescato l'hook `fp_dmk_asset_published` su ogni asset pubblicato,
 * quindi — se l'opzione «Notifica automatica» è attiva — parte anche la mail
 * ai distributori esattamente come accade creando l'asset singolarmente.
 *
 * @package FP\DistributorMediaKit\Admin
 */
final class BulkUploadPage {

	public const PAGE_SLUG = 'fp-dmk-bulk-upload';

	public function __construct() {
		add_action( 'admin_init', [ $this, 'handle_save' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueue della libreria media sulla pagina bulk (admin.css è già caricato da UserApprovalPage).
	 */
	public function enqueue( string $hook ): void {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page !== self::PAGE_SLUG ) {
			return;
		}
		wp_enqueue_media();
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

		$allowed_mimes = apply_filters(
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
		);

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

		$created = isset( $_GET['fp_dmk_bulk_created'] ) ? absint( $_GET['fp_dmk_bulk_created'] ) : 0;
		$errors  = isset( $_GET['fp_dmk_bulk_errors'] ) ? absint( $_GET['fp_dmk_bulk_errors'] ) : 0;
		?>
		<div class="wrap fpdmk-admin-page">
			<h1 class="screen-reader-text"><?php esc_html_e( 'FP Media Kit', 'fp-dmk' ); ?></h1>
			<div class="fpdmk-page-header">
				<div class="fpdmk-page-header-content">
					<h2 class="fpdmk-page-header-title" aria-hidden="true"><span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Caricamento multiplo', 'fp-dmk' ); ?></h2>
					<p><?php esc_html_e( 'Seleziona più file dalla Libreria media e crea in blocco gli asset del Media Kit. I valori predefiniti sono applicati a ogni file e restano modificabili singolarmente.', 'fp-dmk' ); ?></p>
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
						<span class="fpdmk-badge fpdmk-badge-neutral"><?php esc_html_e( 'Applicate a tutti i file selezionati', 'fp-dmk' ); ?></span>
					</div>
					<div class="fpdmk-card-body">
						<p class="description"><?php esc_html_e( 'Verranno precompilate in ogni riga della lista file qui sotto. Potrai comunque modificare i valori file per file prima di salvare.', 'fp-dmk' ); ?></p>
						<div class="fpdmk-fields-grid">
							<div class="fpdmk-field">
								<label for="fpdmk_bulk_default_language"><?php esc_html_e( 'Lingua', 'fp-dmk' ); ?></label>
								<select id="fpdmk_bulk_default_language">
									<?php foreach ( AssetManager::LANGUAGES as $code => $label ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="fpdmk-field">
								<label for="fpdmk_bulk_default_folder"><?php esc_html_e( 'Cartella', 'fp-dmk' ); ?></label>
								<select id="fpdmk_bulk_default_folder" class="fpdmk-folder-select">
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
								<?php if ( current_user_can( 'manage_fp_dmk_categories' ) ) : ?>
									<div class="fpdmk-folder-new" data-target="#fpdmk_bulk_default_folder">
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
								<span class="fpdmk-hint"><?php esc_html_e( 'Creala al volo oppure gestiscile da FP Media Kit → Cartelle. Le nuove cartelle vengono aggiunte a ogni riga della lista file qui sotto.', 'fp-dmk' ); ?></span>
							</div>
							<div class="fpdmk-field">
								<label for="fpdmk_bulk_default_categories"><?php esc_html_e( 'Categorie', 'fp-dmk' ); ?></label>
								<select id="fpdmk_bulk_default_categories" multiple size="4">
									<?php foreach ( $cat_terms as $term ) : ?>
										<?php if ( ! $term instanceof \WP_Term ) { continue; } ?>
										<option value="<?php echo (int) $term->term_id; ?>"><?php echo esc_html( $term->name ); ?></option>
									<?php endforeach; ?>
								</select>
								<span class="fpdmk-hint"><?php esc_html_e( 'Tieni premuto Ctrl/Cmd per selezione multipla.', 'fp-dmk' ); ?></span>
							</div>
						</div>
					</div>
				</div>

				<div class="fpdmk-card">
					<div class="fpdmk-card-header">
						<div class="fpdmk-card-header-left">
							<span class="dashicons dashicons-media-archive"></span>
							<h2><?php esc_html_e( 'File da caricare', 'fp-dmk' ); ?></h2>
						</div>
						<span class="fpdmk-badge fpdmk-badge-neutral" id="fpdmk-bulk-count">0 <?php esc_html_e( 'file', 'fp-dmk' ); ?></span>
					</div>
					<div class="fpdmk-card-body">
						<p class="description"><?php esc_html_e( 'Clicca «Seleziona file» per aprire la Libreria media e scegliere più allegati. Puoi aggiungere altri file in qualsiasi momento o rimuovere singole righe prima di salvare.', 'fp-dmk' ); ?></p>
						<p>
							<button type="button" class="fpdmk-btn fpdmk-btn-secondary" id="fpdmk-bulk-pick"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Seleziona file', 'fp-dmk' ); ?></button>
						</p>
						<div id="fpdmk-bulk-empty" class="description" style="margin-top:12px;"><?php esc_html_e( 'Nessun file selezionato.', 'fp-dmk' ); ?></div>
						<table class="fpdmk-table" id="fpdmk-bulk-table" style="display:none;">
							<thead>
								<tr>
									<th style="width:24%;"><?php esc_html_e( 'File', 'fp-dmk' ); ?></th>
									<th style="width:22%;"><?php esc_html_e( 'Titolo', 'fp-dmk' ); ?></th>
									<th style="width:22%;"><?php esc_html_e( 'Descrizione', 'fp-dmk' ); ?></th>
									<th style="width:8%;"><?php esc_html_e( 'Lingua', 'fp-dmk' ); ?></th>
									<th style="width:12%;"><?php esc_html_e( 'Cartella', 'fp-dmk' ); ?></th>
									<th style="width:10%;"><?php esc_html_e( 'Categorie', 'fp-dmk' ); ?></th>
									<th style="width:2%;"></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>

				<div class="fpdmk-card">
					<div class="fpdmk-card-body">
						<button type="submit" class="fpdmk-btn fpdmk-btn-primary" id="fpdmk-bulk-submit" disabled><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Crea asset', 'fp-dmk' ); ?></button>
						<span class="fpdmk-hint" style="margin-left:12px;"><?php esc_html_e( 'Gli asset vengono creati in stato pubblicato.', 'fp-dmk' ); ?></span>
					</div>
				</div>
			</form>

			<script type="text/html" id="tmpl-fpdmk-bulk-row">
				<tr data-row-id="{{ data.rowId }}">
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

			<script>
			(function($){
				if (typeof wp === 'undefined' || !wp.media) { return; }
				var allowed     = <?php echo wp_json_encode( array_values( $allowed_mimes ) ); ?>;
				var folderNonce = <?php echo wp_json_encode( wp_create_nonce( 'fp_dmk_create_folder' ) ); ?>;
				var ajaxUrl     = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
				var canCreateFolders = <?php echo wp_json_encode( current_user_can( 'manage_fp_dmk_categories' ) ); ?>;
				var $pickBtn = document.getElementById('fpdmk-bulk-pick');
				var $submit  = document.getElementById('fpdmk-bulk-submit');
				var $tbody   = document.querySelector('#fpdmk-bulk-table tbody');
				var $table   = document.getElementById('fpdmk-bulk-table');
				var $empty   = document.getElementById('fpdmk-bulk-empty');
				var $count   = document.getElementById('fpdmk-bulk-count');
				var $defLang = document.getElementById('fpdmk_bulk_default_language');
				var $defFold = document.getElementById('fpdmk_bulk_default_folder');
				var $defCats = document.getElementById('fpdmk_bulk_default_categories');
				var tpl      = wp.template('fpdmk-bulk-row');
				var rowSeq   = 0;
				var addedIds = {};
				var frame;

				function refreshUI(){
					var n = $tbody.querySelectorAll('tr').length;
					$count.textContent = n + ' <?php echo esc_js( __( 'file', 'fp-dmk' ) ); ?>';
					$table.style.display = n > 0 ? '' : 'none';
					$empty.style.display = n > 0 ? 'none' : '';
					$submit.disabled = n === 0;
				}

				function cloneSelect(sourceSelect, namePrefix, rowId, multiple){
					var s = document.createElement('select');
					s.name = 'items[' + rowId + ']' + namePrefix + (multiple ? '[]' : '');
					if (multiple) { s.multiple = true; s.size = 3; }
					Array.prototype.forEach.call(sourceSelect.options, function(o){
						var opt = document.createElement('option');
						opt.value = o.value;
						opt.textContent = o.textContent;
						if (multiple) {
							if (o.selected) { opt.selected = true; }
						} else {
							if (o.selected) { opt.selected = true; }
						}
						s.appendChild(opt);
					});
					return s;
				}

				function addRow(att){
					if (!att || !att.id) { return; }
					if (addedIds[att.id]) { return; }
					addedIds[att.id] = true;
					rowSeq++;
					var rowId = 'r' + rowSeq;
					var filename = att.filename || att.title || ('attachment-' + att.id);
					var mime = att.mime || '';
					var html = tpl({
						rowId: rowId,
						id: att.id,
						filename: filename,
						mime: mime,
						title: att.title || filename,
						description: att.caption || ''
					});
					var tmp = document.createElement('tbody');
					tmp.innerHTML = html.trim();
					var tr = tmp.firstChild;
					tr.querySelector('.fpdmk-bulk-lang-cell').appendChild(cloneSelect($defLang, '[language]', rowId, false));
					tr.querySelector('.fpdmk-bulk-folder-cell').appendChild(cloneSelect($defFold, '[folder_term]', rowId, false));
					tr.querySelector('.fpdmk-bulk-cats-cell').appendChild(cloneSelect($defCats, '[categories]', rowId, true));
					$tbody.appendChild(tr);
					refreshUI();
				}

				$pickBtn.addEventListener('click', function(){
					frame = wp.media({
						title: '<?php echo esc_js( __( 'Seleziona file per il bulk upload', 'fp-dmk' ) ); ?>',
						button: { text: '<?php echo esc_js( __( 'Aggiungi alla lista', 'fp-dmk' ) ); ?>' },
						library: { type: allowed },
						multiple: 'add'
					});
					frame.on('select', function(){
						var sel = frame.state().get('selection');
						sel.each(function(att){ addRow(att.toJSON()); });
					});
					frame.open();
				});

				$tbody.addEventListener('click', function(e){
					var btn = e.target.closest('.fpdmk-bulk-remove');
					if (!btn) { return; }
					var tr = btn.closest('tr');
					if (!tr) { return; }
					var hid = tr.querySelector('input[type="hidden"]');
					if (hid && hid.value) { delete addedIds[hid.value]; }
					tr.parentNode.removeChild(tr);
					refreshUI();
				});

				function appendFolderOption(select, termId, label, depth, markSelected){
					if (!select) { return; }
					if (select.querySelector('option[value="' + termId + '"]')) {
						if (markSelected) { select.value = String(termId); }
						return;
					}
					var opt = document.createElement('option');
					opt.value = String(termId);
					opt.textContent = label;
					opt.setAttribute('data-depth', String(depth));
					select.appendChild(opt);
					if (markSelected) { select.value = String(termId); }
				}

				function broadcastFolder(termId, name, depth){
					var pad = ''; for (var i=0;i<depth;i++) pad += '— ';
					var label = pad + name;
					appendFolderOption($defFold, termId, label, depth, true);
					var rowSelects = $tbody.querySelectorAll('select[name$="[folder_term]"]');
					Array.prototype.forEach.call(rowSelects, function(s){
						appendFolderOption(s, termId, label, depth, false);
					});
					var parentSel = document.querySelector('.fpdmk-folder-new-parent');
					if (parentSel) {
						appendFolderOption(parentSel, termId, label, depth, false);
					}
				}

				if (canCreateFolders) {
					var widget = document.querySelector('.fpdmk-folder-new');
					if (widget) {
						var toggle    = widget.querySelector('.fpdmk-folder-new-toggle');
						var form      = widget.querySelector('.fpdmk-folder-new-form');
						var nameInput = widget.querySelector('.fpdmk-folder-new-name');
						var parentSel = widget.querySelector('.fpdmk-folder-new-parent');
						var saveBtn   = widget.querySelector('.fpdmk-folder-new-save');
						var cancelBtn = widget.querySelector('.fpdmk-folder-new-cancel');
						var msg       = widget.querySelector('.fpdmk-folder-new-msg');
						toggle.addEventListener('click', function(){
							form.hidden = false;
							toggle.hidden = true;
							nameInput.focus();
						});
						cancelBtn.addEventListener('click', function(){
							form.hidden = true;
							toggle.hidden = false;
							nameInput.value = '';
							msg.textContent = '';
						});
						saveBtn.addEventListener('click', function(){
							var name = (nameInput.value || '').trim();
							if (!name) { nameInput.focus(); return; }
							saveBtn.disabled = true;
							msg.textContent = <?php echo wp_json_encode( __( 'Creazione in corso…', 'fp-dmk' ) ); ?>;
							var body = new URLSearchParams();
							body.append('action', 'fp_dmk_create_folder');
							body.append('_nonce', folderNonce);
							body.append('name', name);
							body.append('parent', parentSel.value || '0');
							fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function(r){ return r.json(); }).then(function(res){
								saveBtn.disabled = false;
								if (!res || !res.success) {
									msg.textContent = (res && res.data && res.data.message) ? res.data.message : <?php echo wp_json_encode( __( 'Errore durante la creazione.', 'fp-dmk' ) ); ?>;
									return;
								}
								var d = res.data;
								broadcastFolder(d.term_id, d.name, d.depth);
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
				}

				refreshUI();
			})(jQuery);
			</script>
		</div>
		<?php
	}
}
