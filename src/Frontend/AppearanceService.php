<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Frontend;

/**
 * Servizio per stili personalizzabili (colori, sfondi) del frontend Media Kit.
 *
 * @package FP\DistributorMediaKit\Frontend
 */
final class AppearanceService {

	private const OPTION_KEY = 'fp_dmk_settings';

	public const DEFAULTS = [
		'btn_primary'       => '#667eea',
		'btn_primary_end'   => '#764ba2',
		'btn_secondary'     => '#e5e7eb',
		'btn_secondary_hover'=> '#d1d5db',
		'card_bg'           => '#ffffff',
		'card_border'       => '#e5e7eb',
		'section_bg'        => 'transparent',
		'input_border'      => '#e5e7eb',
		'input_focus'       => '#667eea',
		'border_radius'     => '8',
		'card_radius'       => '12',
	];

	/**
	 * Restituisce le opzioni aspetto (merge con defaults).
	 *
	 * @return array<string, string>
	 */
	public static function get_options(): array {
		$opts = get_option( self::OPTION_KEY, [] );
		$opts = is_array( $opts ) ? $opts : [];
		return wp_parse_args( $opts, self::DEFAULTS );
	}

	/**
	 * Genera CSS custom properties per il frontend.
	 */
	public static function get_custom_css(): string {
		$o = self::get_options();
		$def = self::DEFAULTS;
		$vars = [
			'--fpdmk-btn-primary'        => self::sanitize_color( ! empty( $o['btn_primary'] ) ? $o['btn_primary'] : $def['btn_primary'] ),
			'--fpdmk-btn-primary-end'    => self::sanitize_color( ! empty( $o['btn_primary_end'] ) ? $o['btn_primary_end'] : $def['btn_primary_end'] ),
			'--fpdmk-btn-secondary'      => self::sanitize_color( ! empty( $o['btn_secondary'] ) ? $o['btn_secondary'] : $def['btn_secondary'] ),
			'--fpdmk-btn-secondary-hover'=> self::sanitize_color( ! empty( $o['btn_secondary_hover'] ) ? $o['btn_secondary_hover'] : $def['btn_secondary_hover'] ),
			'--fpdmk-card-bg'            => self::sanitize_color( ! empty( $o['card_bg'] ) ? $o['card_bg'] : $def['card_bg'] ),
			'--fpdmk-card-border'        => self::sanitize_color( ! empty( $o['card_border'] ) ? $o['card_border'] : $def['card_border'] ),
			'--fpdmk-section-bg'         => ! empty( $o['section_bg'] ) ? self::sanitize_color( $o['section_bg'] ) : 'transparent',
			'--fpdmk-input-border'       => self::sanitize_color( ! empty( $o['input_border'] ) ? $o['input_border'] : $def['input_border'] ),
			'--fpdmk-input-focus'        => self::sanitize_color( ! empty( $o['input_focus'] ) ? $o['input_focus'] : $def['input_focus'] ),
			'--fpdmk-radius'             => ( absint( $o['border_radius'] ?? $def['border_radius'] ) ) . 'px',
			'--fpdmk-card-radius'        => ( absint( $o['card_radius'] ?? $def['card_radius'] ) ) . 'px',
		];
		$lines = [];
		foreach ( $vars as $k => $v ) {
			$lines[] = $k . ':' . $v;
		}
		return '.fpdmk-register-wrap, .fpdmk-login-wrap, .fpdmk-media-kit {' . implode( ';', $lines ) . '}';
	}

	private static function sanitize_color( string $v ): string {
		if ( $v === '' || $v === 'transparent' ) {
			return 'transparent';
		}
		if ( preg_match( '/^#([a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/', $v ) ) {
			return $v;
		}
		if ( preg_match( '/^rgba?\(/', $v ) ) {
			return $v;
		}
		return '#667eea';
	}

	/**
	 * Aggiunge gli stili inline allo stylesheet frontend.
	 */
	public static function enqueue_with_custom_styles(): void {
		wp_enqueue_style( 'fp-dmk-frontend', FP_DMK_URL . 'assets/css/frontend.css', [], FP_DMK_VERSION );
		wp_add_inline_style( 'fp-dmk-frontend', self::get_custom_css() );
	}
}
