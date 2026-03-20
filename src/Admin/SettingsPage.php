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
		'media_kit_page' => 0,
		'login_page'     => 0,
		'register_page'  => 0,
		'email_from'     => '',
		'email_from_name'=> '',
		'auto_notify'    => false,
	];

	public function __construct() {
		add_action( 'admin_init', [ $this, 'handle_save' ] );
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
		$opts['email_from'] = isset( $_POST['email_from'] ) ? sanitize_email( wp_unslash( $_POST['email_from'] ) ) : '';
		$opts['email_from_name'] = isset( $_POST['email_from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['email_from_name'] ) ) : '';
		$opts['auto_notify'] = ! empty( $_POST['auto_notify'] );

		update_option( self::OPTION_KEY, $opts );
		update_option( 'fp_dmk_media_kit_page', $opts['media_kit_page'] );
		update_option( 'fp_dmk_login_page', $opts['login_page'] );
		update_option( 'fp_dmk_register_page', $opts['register_page'] );
		update_option( 'fp_dmk_email_from', $opts['email_from'] ?: get_bloginfo( 'admin_email' ) );
		update_option( 'fp_dmk_email_from_name', $opts['email_from_name'] ?: get_bloginfo( 'name' ) );

		wp_safe_redirect( add_query_arg( 'fp_dmk_saved', '1', wp_get_referer() ?: admin_url( 'admin.php?page=fp-dmk-settings' ) ) );
		exit;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_fp_dmk' ) ) {
			return;
		}
		$opts = wp_parse_args( get_option( self::OPTION_KEY, [] ), self::DEFAULTS );
		$pages = get_pages( [ 'sort_column' => 'post_title' ] );
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

			<?php if ( isset( $_GET['fp_dmk_saved'] ) ) : ?>
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
					</div>
				</div>
			</div>

			<div class="fpdmk-card">
				<div class="fpdmk-card-body">
					<button type="submit" class="fpdmk-btn fpdmk-btn-primary"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Salva', 'fp-dmk' ); ?></button>
				</div>
			</div>
			</form>
		<?php
		// Fix: il form deve wrappare tutto - correggo
	}
}
