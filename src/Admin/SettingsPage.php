<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Admin;

/**
 * Pagina impostazioni plugin.
 *
 * @package FP\DistributorMediaKit\Admin
 */
final class SettingsPage {

	private const OPTION_KEY = 'fp_dmk_settings';

	private const DEFAULTS = [
		'media_kit_page'   => 0,
		'login_page'       => 0,
		'register_page'    => 0,
		'email_from'       => '',
		'email_from_name'  => '',
		'use_fpmail_from'  => false,
		'auto_notify'                  => false,
		'admin_notify_email'           => '',
		'notify_pending_registration'  => false,
		'daily_download_report'        => false,
		'purge_days'                   => 0,
		'audience_enabled'             => false,
		'audience_segments'            => [
			[ 'slug' => 'distributor', 'label' => 'Distributore' ],
			[ 'slug' => 'journalist', 'label' => 'Giornalista' ],
		],
		'audience_segment_categories'  => [],
		// Aspetto frontend
		'btn_primary'        => '#667eea',
		'btn_primary_end'    => '#764ba2',
		'btn_secondary'      => '#e5e7eb',
		'btn_secondary_hover'=> '#d1d5db',
		'card_bg'            => '#ffffff',
		'card_border'        => '#e5e7eb',
		'section_bg'         => '',
		'input_border'       => '#e5e7eb',
		'input_focus'        => '#667eea',
		'border_radius'      => '8',
		'card_radius'        => '12',
	];

	public function __construct() {
		add_action( 'admin_init', [ $this, 'handle_save' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_color_picker' ] );
	}

	public function enqueue_color_picker( string $hook ): void {
		$on_settings = ( strpos( $hook, 'fp-dmk-settings' ) !== false ) || ( isset( $_GET['page'] ) && sanitize_text_field( wp_unslash( $_GET['page'] ) ) === 'fp-dmk-settings' );
		if ( ! $on_settings ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	public function handle_save(): void {
		if ( ! isset( $_POST['fp_dmk_save_settings'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}
		if ( ! isset( $_POST['fp_dmk_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fp_dmk_settings_nonce'] ) ), 'fp_dmk_settings' ) ) {
			return;
		}
		$opts = get_option( self::OPTION_KEY, self::DEFAULTS );
		$opts = is_array( $opts ) ? $opts : self::DEFAULTS;

		$opts['media_kit_page'] = isset( $_POST['media_kit_page'] ) ? absint( $_POST['media_kit_page'] ) : 0;
		$opts['login_page'] = isset( $_POST['login_page'] ) ? absint( $_POST['login_page'] ) : 0;
		$opts['register_page'] = isset( $_POST['register_page'] ) ? absint( $_POST['register_page'] ) : 0;
		$opts['email_from']      = isset( $_POST['email_from'] ) ? sanitize_email( wp_unslash( $_POST['email_from'] ) ) : '';
		$opts['email_from_name'] = isset( $_POST['email_from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['email_from_name'] ) ) : '';
		$opts['use_fpmail_from'] = ! empty( $_POST['use_fpmail_from'] );
		$opts['auto_notify']     = ! empty( $_POST['auto_notify'] );
		$opts['admin_notify_email'] = isset( $_POST['admin_notify_email'] ) ? sanitize_email( wp_unslash( $_POST['admin_notify_email'] ) ) : '';
		$opts['notify_pending_registration'] = ! empty( $_POST['notify_pending_registration'] );
		$opts['daily_download_report']      = ! empty( $_POST['daily_download_report'] );
		$opts['purge_days'] = isset( $_POST['purge_days'] ) ? absint( $_POST['purge_days'] ) : 0;

		$seg_slugs  = isset( $_POST['audience_seg_slug'] ) ? (array) wp_unslash( $_POST['audience_seg_slug'] ) : [];
		$seg_labels = isset( $_POST['audience_seg_label'] ) ? (array) wp_unslash( $_POST['audience_seg_label'] ) : [];
		$segments_parsed = [];
		foreach ( $seg_slugs as $idx => $raw_slug ) {
			$slug = sanitize_key( is_string( $raw_slug ) ? $raw_slug : '' );
			if ( $slug === '' ) {
				continue;
			}
			$label_raw = isset( $seg_labels[ $idx ] ) ? (string) $seg_labels[ $idx ] : '';
			$label     = sanitize_text_field( $label_raw );
			if ( $label === '' ) {
				$label = $slug;
			}
			$segments_parsed[] = [ 'slug' => $slug, 'label' => $label ];
		}
		$audience_on = ! empty( $_POST['audience_enabled'] );
		if ( $audience_on && $segments_parsed === [] ) {
			$audience_on = false;
		}
		$opts['audience_enabled']           = $audience_on;
		$opts['audience_segments']          = $segments_parsed;
		$opts['audience_segment_categories'] = [];
		$valid_slugs                        = array_column( $segments_parsed, 'slug' );
		$restrict_post                      = isset( $_POST['audience_restrict'] ) && is_array( $_POST['audience_restrict'] ) ? wp_unslash( $_POST['audience_restrict'] ) : [];
		foreach ( $valid_slugs as $seg_key ) {
			if ( empty( $restrict_post[ $seg_key ] ) ) {
				continue;
			}
			$raw_cats = isset( $_POST['audience_segment_cats'][ $seg_key ] ) && is_array( $_POST['audience_segment_cats'][ $seg_key ] )
				? wp_unslash( $_POST['audience_segment_cats'][ $seg_key ] )
				: [];
			$vals     = [];
			foreach ( $raw_cats as $t ) {
				$vals[] = sanitize_title( is_string( $t ) ? $t : (string) $t );
			}
			$vals = array_values( array_unique( array_filter( $vals ) ) );
			$opts['audience_segment_categories'][ $seg_key ] = $vals;
		}

		// Aspetto
		$hex = static fn( string $k ) => isset( $_POST[ $k ] ) ? ( self::sanitize_hex( (string) wp_unslash( $_POST[ $k ] ) ) ?: self::DEFAULTS[ $k ] ) : self::DEFAULTS[ $k ];
		$opts['btn_primary'] = $hex( 'btn_primary' );
		$opts['btn_primary_end'] = $hex( 'btn_primary_end' );
		$opts['btn_secondary'] = $hex( 'btn_secondary' );
		$opts['btn_secondary_hover'] = $hex( 'btn_secondary_hover' );
		$opts['card_bg'] = $hex( 'card_bg' );
		$opts['card_border'] = $hex( 'card_border' );
		$opts['section_bg'] = isset( $_POST['section_bg'] ) ? self::sanitize_hex( (string) wp_unslash( $_POST['section_bg'] ) ) : '';
		$opts['input_border'] = $hex( 'input_border' );
		$opts['input_focus'] = $hex( 'input_focus' );
		$opts['border_radius'] = isset( $_POST['border_radius'] ) ? absint( $_POST['border_radius'] ) : 8;
		$opts['card_radius'] = isset( $_POST['card_radius'] ) ? absint( $_POST['card_radius'] ) : 12;

		update_option( self::OPTION_KEY, $opts );
		update_option( 'fp_dmk_media_kit_page', $opts['media_kit_page'] );
		update_option( 'fp_dmk_login_page', $opts['login_page'] );
		update_option( 'fp_dmk_register_page', $opts['register_page'] );
		update_option( 'fp_dmk_email_from', $opts['email_from'] ?: get_bloginfo( 'admin_email' ) );
		update_option( 'fp_dmk_email_from_name', $opts['email_from_name'] ?: get_bloginfo( 'name' ) );
		update_option( 'fp_dmk_purge_days', $opts['purge_days'] );
		\FP\DistributorMediaKit\Cron\PurgeDownloadsCron::maybe_schedule();
		\FP\DistributorMediaKit\Cron\DailyDownloadReportCron::maybe_schedule();

		wp_safe_redirect( add_query_arg( 'fp_dmk_saved', '1', wp_get_referer() ?: admin_url( 'admin.php?page=fp-dmk-settings' ) ) );
		exit;
	}

	private static function sanitize_hex( string $v ): string {
		$v = trim( $v );
		if ( $v === '' || $v === 'transparent' ) {
			return '';
		}
		if ( preg_match( '/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $v ) ) {
			return $v;
		}
		return '';
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}
		$opts = wp_parse_args( get_option( self::OPTION_KEY, [] ), self::DEFAULTS );
		$pages = get_pages( [ 'sort_column' => 'post_title' ] );
		$seg_form_rows = isset( $opts['audience_segments'] ) && is_array( $opts['audience_segments'] ) ? $opts['audience_segments'] : [];
		$seg_form_rows[] = [ 'slug' => '', 'label' => '' ];
		$matrix_segments = \FP\DistributorMediaKit\User\AudienceService::get_segments();
		$cat_map         = isset( $opts['audience_segment_categories'] ) && is_array( $opts['audience_segment_categories'] ) ? $opts['audience_segment_categories'] : [];
		$asset_terms     = get_terms( [ 'taxonomy' => AssetManager::TAXONOMY, 'hide_empty' => false ] );
		if ( is_wp_error( $asset_terms ) ) {
			$asset_terms = [];
		}
		?>
		<div class="wrap fpdmk-admin-page">
			<h1 class="screen-reader-text"><?php esc_html_e( 'FP Media Kit', 'fp-dmk' ); ?></h1>
			<div class="fpdmk-page-header">
				<div class="fpdmk-page-header-content">
					<h2 class="fpdmk-page-header-title" aria-hidden="true"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Impostazioni', 'fp-dmk' ); ?></h2>
					<p><?php esc_html_e( 'Configura le pagine e le notifiche email.', 'fp-dmk' ); ?></p>
				</div>
				<span class="fpdmk-page-header-badge">v<?php echo esc_html( FP_DMK_VERSION ); ?></span>
			</div>

			<?php if ( isset( $_GET['fp_dmk_saved'] ) && sanitize_text_field( wp_unslash( $_GET['fp_dmk_saved'] ) ) === '1' ) : ?>
				<div class="fpdmk-alert fpdmk-alert-success">
					<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Impostazioni salvate.', 'fp-dmk' ); ?>
				</div>
			<?php endif; ?>

			<form method="post" id="fpdmk-settings-form">
				<?php wp_nonce_field( 'fp_dmk_settings', 'fp_dmk_settings_nonce' ); ?>
				<input type="hidden" name="fp_dmk_save_settings" value="1">

			<div class="fpdmk-card">
				<div class="fpdmk-card-header">
					<div class="fpdmk-card-header-left">
						<span class="dashicons dashicons-admin-page"></span>
						<h2><?php esc_html_e( 'Pagine', 'fp-dmk' ); ?></h2>
					</div>
				</div>
				<div class="fpdmk-card-body">
					<div class="fpdmk-fields-grid">
						<div class="fpdmk-field">
							<label for="media_kit_page"><?php esc_html_e( 'Pagina Media Kit', 'fp-dmk' ); ?></label>
							<select id="media_kit_page" name="media_kit_page">
								<option value="0"><?php esc_html_e( '— Seleziona —', 'fp-dmk' ); ?></option>
								<?php foreach ( $pages as $p ) : ?>
									<option value="<?php echo (int) $p->ID; ?>" <?php selected( $opts['media_kit_page'], $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="fpdmk-hint"><?php esc_html_e( 'Pagina con shortcode [fp_dmk_media_kit]. L\'accesso è limitato agli utenti approvati.', 'fp-dmk' ); ?></span>
						</div>
						<div class="fpdmk-field">
							<label for="login_page"><?php esc_html_e( 'Pagina Login', 'fp-dmk' ); ?></label>
							<select id="login_page" name="login_page">
								<option value="0"><?php esc_html_e( '— Seleziona —', 'fp-dmk' ); ?></option>
								<?php foreach ( $pages as $p ) : ?>
									<option value="<?php echo (int) $p->ID; ?>" <?php selected( $opts['login_page'], $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="fpdmk-hint"><?php esc_html_e( 'Pagina con shortcode [fp_dmk_login].', 'fp-dmk' ); ?></span>
						</div>
						<div class="fpdmk-field">
							<label for="register_page"><?php esc_html_e( 'Pagina Registrazione', 'fp-dmk' ); ?></label>
							<select id="register_page" name="register_page">
								<option value="0"><?php esc_html_e( '— Seleziona —', 'fp-dmk' ); ?></option>
								<?php foreach ( $pages as $p ) : ?>
									<option value="<?php echo (int) $p->ID; ?>" <?php selected( $opts['register_page'], $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="fpdmk-hint"><?php esc_html_e( 'Pagina con shortcode [fp_dmk_register].', 'fp-dmk' ); ?></span>
						</div>
					</div>
				</div>
			</div>

			<div class="fpdmk-card">
				<div class="fpdmk-card-header">
					<div class="fpdmk-card-header-left">
						<span class="dashicons dashicons-email"></span>
						<h2><?php esc_html_e( 'Email', 'fp-dmk' ); ?></h2>
					</div>
				</div>
				<div class="fpdmk-card-body">
					<div class="fpdmk-fields-grid">
						<div class="fpdmk-field">
							<label for="email_from"><?php esc_html_e( 'Email mittente', 'fp-dmk' ); ?></label>
							<input type="email" id="email_from" name="email_from" class="regular-text" value="<?php echo esc_attr( $opts['email_from'] ?: get_bloginfo( 'admin_email' ) ); ?>">
						</div>
						<div class="fpdmk-field">
							<label for="email_from_name"><?php esc_html_e( 'Nome mittente', 'fp-dmk' ); ?></label>
							<input type="text" id="email_from_name" name="email_from_name" class="regular-text" value="<?php echo esc_attr( $opts['email_from_name'] ?: get_bloginfo( 'name' ) ); ?>">
						</div>
						<?php if ( defined( 'FP_FPMAIL_VERSION' ) ) : ?>
						<div class="fpdmk-field fpdmk-toggle-row">
							<div class="fpdmk-toggle-info">
								<strong><?php esc_html_e( 'Usa mittente da FP Mail SMTP', 'fp-dmk' ); ?></strong>
								<span><?php esc_html_e( 'Se attivo, le email del Media Kit useranno il mittente configurato in FP Mail SMTP invece di quello sopra.', 'fp-dmk' ); ?></span>
							</div>
							<label class="fpdmk-toggle">
								<input type="checkbox" name="use_fpmail_from" value="1" <?php checked( ! empty( $opts['use_fpmail_from'] ) ); ?>>
								<span class="fpdmk-toggle-slider"></span>
							</label>
						</div>
						<?php endif; ?>
						<div class="fpdmk-field fpdmk-toggle-row">
							<div class="fpdmk-toggle-info">
								<strong><?php esc_html_e( 'Notifica automatica', 'fp-dmk' ); ?></strong>
								<span><?php esc_html_e( 'Invia email ai distributori quando un nuovo asset viene pubblicato.', 'fp-dmk' ); ?></span>
							</div>
							<label class="fpdmk-toggle">
								<input type="checkbox" name="auto_notify" value="1" <?php checked( $opts['auto_notify'] ); ?>>
								<span class="fpdmk-toggle-slider"></span>
							</label>
						</div>
						<div class="fpdmk-field">
							<label for="admin_notify_email"><?php esc_html_e( 'Email notifiche amministratore', 'fp-dmk' ); ?></label>
							<input type="email" id="admin_notify_email" name="admin_notify_email" class="regular-text" value="<?php echo esc_attr( (string) ( $opts['admin_notify_email'] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'admin_email' ) ); ?>">
							<span class="fpdmk-hint"><?php esc_html_e( 'Destinatario per nuove registrazioni in attesa e per il report giornaliero download. Se vuoto, viene usata l\'email amministratore del sito.', 'fp-dmk' ); ?></span>
						</div>
						<div class="fpdmk-field fpdmk-toggle-row">
							<div class="fpdmk-toggle-info">
								<strong><?php esc_html_e( 'Email a ogni registrazione in attesa', 'fp-dmk' ); ?></strong>
								<span><?php esc_html_e( 'Invia un\'email all\'indirizzo sopra con link monouso per approvare il distributore (non richiede login in bacheca).', 'fp-dmk' ); ?></span>
							</div>
							<label class="fpdmk-toggle">
								<input type="checkbox" name="notify_pending_registration" value="1" <?php checked( ! empty( $opts['notify_pending_registration'] ) ); ?>>
								<span class="fpdmk-toggle-slider"></span>
							</label>
						</div>
						<div class="fpdmk-field fpdmk-toggle-row">
							<div class="fpdmk-toggle-info">
								<strong><?php esc_html_e( 'Report giornaliero download', 'fp-dmk' ); ?></strong>
								<span><?php esc_html_e( 'Invia ogni giorno un riepilogo dei download del giorno precedente (per file), con link ai report completi.', 'fp-dmk' ); ?></span>
							</div>
							<label class="fpdmk-toggle">
								<input type="checkbox" name="daily_download_report" value="1" <?php checked( ! empty( $opts['daily_download_report'] ) ); ?>>
								<span class="fpdmk-toggle-slider"></span>
							</label>
						</div>
						<div class="fpdmk-field">
							<label for="purge_days"><?php esc_html_e( 'Pulizia log download (giorni)', 'fp-dmk' ); ?></label>
							<input type="number" id="purge_days" name="purge_days" min="0" max="3650" value="<?php echo esc_attr( $opts['purge_days'] ?? 0 ); ?>">
							<span class="fpdmk-hint"><?php esc_html_e( 'Elimina i record più vecchi di N giorni. 0 = nessuna pulizia automatica.', 'fp-dmk' ); ?></span>
						</div>
					</div>
				</div>
			</div>

			<div class="fpdmk-card">
				<div class="fpdmk-card-header">
					<div class="fpdmk-card-header-left">
						<span class="dashicons dashicons-groups"></span>
						<h2><?php esc_html_e( 'Tipi di accesso (audience)', 'fp-dmk' ); ?></h2>
					</div>
				</div>
				<div class="fpdmk-card-body">
					<div class="fpdmk-field fpdmk-toggle-row">
						<div class="fpdmk-toggle-info">
							<strong><?php esc_html_e( 'Abilita tipi di accesso', 'fp-dmk' ); ?></strong>
							<span><?php esc_html_e( 'In registrazione compare la scelta del tipo; puoi limitare le categorie di asset per tipo. Staff (admin, editori, gestori Media Kit) vede sempre tutto.', 'fp-dmk' ); ?></span>
						</div>
						<label class="fpdmk-toggle">
							<input type="checkbox" name="audience_enabled" value="1" <?php checked( ! empty( $opts['audience_enabled'] ) ); ?>>
							<span class="fpdmk-toggle-slider"></span>
						</label>
					</div>
					<p class="description fpdmk-mb"><?php esc_html_e( 'Definisci slug (solo lettere minuscole, numeri, trattini) e etichetta. Aggiungi righe lasciando l’ultima vuota per nuovi tipi.', 'fp-dmk' ); ?></p>
					<table class="fpdmk-table fpdmk-mb">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Slug', 'fp-dmk' ); ?></th>
								<th><?php esc_html_e( 'Etichetta', 'fp-dmk' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $seg_form_rows as $row ) : ?>
								<?php
								$rslug = isset( $row['slug'] ) ? sanitize_key( (string) $row['slug'] ) : '';
								$rlab  = isset( $row['label'] ) ? (string) $row['label'] : '';
								?>
								<tr>
									<td><input type="text" name="audience_seg_slug[]" class="regular-text" value="<?php echo esc_attr( $rslug ); ?>" pattern="[a-z0-9\-]+" placeholder="es. journalist"></td>
									<td><input type="text" name="audience_seg_label[]" class="regular-text" value="<?php echo esc_attr( $rlab ); ?>" placeholder="<?php esc_attr_e( 'Nome visualizzato', 'fp-dmk' ); ?>"></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( $matrix_segments !== [] && $asset_terms !== [] ) : ?>
						<h3 class="fpdmk-subheading"><?php esc_html_e( 'Categorie asset per tipo', 'fp-dmk' ); ?></h3>
						<p class="description fpdmk-mb"><?php esc_html_e( 'Solo se attivi «Limita categorie» per un tipo: scegli quali categorie può vedere. Nessuna spunta = nessun file per quel tipo. Senza limite (checkbox disattivata) = tutte le categorie.', 'fp-dmk' ); ?></p>
						<?php foreach ( $matrix_segments as $mrow ) : ?>
							<?php
							$mslug    = $mrow['slug'];
							$restricted = array_key_exists( $mslug, $cat_map );
							$sel      = $restricted && isset( $cat_map[ $mslug ] ) && is_array( $cat_map[ $mslug ] ) ? $cat_map[ $mslug ] : [];
							?>
							<div class="fpdmk-card fpdmk-card-nested fpdmk-mb">
								<div class="fpdmk-card-body">
									<p><strong><?php echo esc_html( $mrow['label'] ); ?></strong> <code><?php echo esc_html( $mslug ); ?></code></p>
									<label class="fpdmk-field">
										<input type="checkbox" name="audience_restrict[<?php echo esc_attr( $mslug ); ?>]" value="1" <?php checked( $restricted ); ?>>
										<?php esc_html_e( 'Limita categorie per questo tipo', 'fp-dmk' ); ?>
									</label>
									<div class="fpdmk-fields-grid" style="margin-top:12px;">
										<?php foreach ( $asset_terms as $term ) : ?>
											<?php
											if ( ! $term instanceof \WP_Term ) {
												continue;
											}
											?>
											<label class="fpdmk-field" style="display:flex;align-items:center;gap:8px;">
												<input type="checkbox" name="audience_segment_cats[<?php echo esc_attr( $mslug ); ?>][]" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $sel, true ) ); ?>>
												<?php echo esc_html( $term->name ); ?>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					<?php elseif ( $matrix_segments !== [] && $asset_terms === [] ) : ?>
						<p class="description"><?php esc_html_e( 'Crea almeno una categoria asset dalla sezione «Nuova categoria» in Caricamento multiplo per configurare i permessi per tipo.', 'fp-dmk' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="fpdmk-card">
				<div class="fpdmk-card-header">
					<div class="fpdmk-card-header-left">
						<span class="dashicons dashicons-art"></span>
						<h2><?php esc_html_e( 'Aspetto', 'fp-dmk' ); ?></h2>
					</div>
				</div>
				<div class="fpdmk-card-body">
					<p class="description fpdmk-mb"><?php esc_html_e( 'Personalizza colori e stili delle pagine Login, Registrazione e Media Kit.', 'fp-dmk' ); ?></p>
					<div class="fpdmk-fields-grid">
						<div class="fpdmk-field">
							<label for="btn_primary"><?php esc_html_e( 'Bottone primario (inizio gradiente)', 'fp-dmk' ); ?></label>
							<input type="text" id="btn_primary" name="btn_primary" class="fpdmk-color-picker" value="<?php echo esc_attr( $opts['btn_primary'] ?? self::DEFAULTS['btn_primary'] ); ?>">
						</div>
						<div class="fpdmk-field">
							<label for="btn_primary_end"><?php esc_html_e( 'Bottone primario (fine gradiente)', 'fp-dmk' ); ?></label>
							<input type="text" id="btn_primary_end" name="btn_primary_end" class="fpdmk-color-picker" value="<?php echo esc_attr( $opts['btn_primary_end'] ?? self::DEFAULTS['btn_primary_end'] ); ?>">
						</div>
						<div class="fpdmk-field">
							<label for="btn_secondary"><?php esc_html_e( 'Bottone secondario', 'fp-dmk' ); ?></label>
							<input type="text" id="btn_secondary" name="btn_secondary" class="fpdmk-color-picker" value="<?php echo esc_attr( $opts['btn_secondary'] ?? self::DEFAULTS['btn_secondary'] ); ?>">
						</div>
						<div class="fpdmk-field">
							<label for="btn_secondary_hover"><?php esc_html_e( 'Bottone secondario (hover)', 'fp-dmk' ); ?></label>
							<input type="text" id="btn_secondary_hover" name="btn_secondary_hover" class="fpdmk-color-picker" value="<?php echo esc_attr( $opts['btn_secondary_hover'] ?? self::DEFAULTS['btn_secondary_hover'] ); ?>">
						</div>
						<div class="fpdmk-field">
							<label for="card_bg"><?php esc_html_e( 'Sfondo card', 'fp-dmk' ); ?></label>
							<input type="text" id="card_bg" name="card_bg" class="fpdmk-color-picker" value="<?php echo esc_attr( $opts['card_bg'] ?? self::DEFAULTS['card_bg'] ); ?>">
						</div>
						<div class="fpdmk-field">
							<label for="card_border"><?php esc_html_e( 'Bordo card', 'fp-dmk' ); ?></label>
							<input type="text" id="card_border" name="card_border" class="fpdmk-color-picker" value="<?php echo esc_attr( $opts['card_border'] ?? self::DEFAULTS['card_border'] ); ?>">
						</div>
						<div class="fpdmk-field">
							<label for="section_bg"><?php esc_html_e( 'Sfondo sezione (lascia vuoto per trasparente)', 'fp-dmk' ); ?></label>
							<input type="text" id="section_bg" name="section_bg" class="fpdmk-color-picker" value="<?php echo esc_attr( $opts['section_bg'] ?? '' ); ?>" placeholder="trasparente">
						</div>
						<div class="fpdmk-field">
							<label for="input_border"><?php esc_html_e( 'Bordo campi input', 'fp-dmk' ); ?></label>
							<input type="text" id="input_border" name="input_border" class="fpdmk-color-picker" value="<?php echo esc_attr( $opts['input_border'] ?? self::DEFAULTS['input_border'] ); ?>">
						</div>
						<div class="fpdmk-field">
							<label for="input_focus"><?php esc_html_e( 'Bordo input (focus)', 'fp-dmk' ); ?></label>
							<input type="text" id="input_focus" name="input_focus" class="fpdmk-color-picker" value="<?php echo esc_attr( $opts['input_focus'] ?? self::DEFAULTS['input_focus'] ); ?>">
						</div>
						<div class="fpdmk-field">
							<label for="border_radius"><?php esc_html_e( 'Raggio bordi bottoni/input (px)', 'fp-dmk' ); ?></label>
							<input type="number" id="border_radius" name="border_radius" min="0" max="24" value="<?php echo esc_attr( $opts['border_radius'] ?? 8 ); ?>">
						</div>
						<div class="fpdmk-field">
							<label for="card_radius"><?php esc_html_e( 'Raggio bordi card (px)', 'fp-dmk' ); ?></label>
							<input type="number" id="card_radius" name="card_radius" min="0" max="32" value="<?php echo esc_attr( $opts['card_radius'] ?? 12 ); ?>">
						</div>
					</div>
				</div>
			</div>

			<div class="fpdmk-card">
				<div class="fpdmk-card-body">
					<button type="submit" class="fpdmk-btn fpdmk-btn-primary"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Salva', 'fp-dmk' ); ?></button>
				</div>
			</div>
			</form>
			<script>
			jQuery(function($) {
				$('.fpdmk-color-picker').wpColorPicker();
			});
			</script>
		<?php
	}
}
