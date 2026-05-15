<?php

declare( strict_types=1 );

namespace FP\DistributorMediaKit\Frontend;

use FP\DistributorMediaKit\User\AudienceService;

/**
 * Supporto shortcode IT/EN per login, registrazione e Media Kit (mappa inglese via filtro gettext).
 *
 * @package FP\DistributorMediaKit\Frontend
 */
final class ShortcodeUiLang {

	private static bool $english_ui = false;

	/** @var array<string, string> msgid italiano => inglese */
	private const EN_MAP = [
		// Login
		'Sei già collegato.'                                    => 'You are already signed in.',
		'Vai al Media Kit'                                      => 'Go to Media Kit',
		'Esci'                                                  => 'Sign out',
		'Il tuo account è in attesa di approvazione.'           => 'Your account is pending approval.',
		'Email o password non corretti.'                        => 'Incorrect email or password.',
		'Il tuo account non è ancora stato approvato.'          => 'Your account has not been approved yet.',
		'Email o username'                                      => 'Email or username',
		'email@esempio.it o username'                           => 'you@example.com or username',
		'Password'                                              => 'Password',
		'Mostra password'                                       => 'Show password',
		'Mostra'                                                => 'Show',
		'Ricordami'                                             => 'Remember me',
		'Accedi'                                                => 'Sign in',
		'Password dimenticata?'                                 => 'Forgot password?',
		'Non hai un account? Registrati'                        => 'No account? Register',
		'Riceverai un\'email per reimpostare la password. Dopo il reset potrai accedere da questa pagina.' => 'You will receive an email to reset your password. After resetting you can sign in from this page.',
		'Collegamenti utili'                                   => 'Useful links',
		'Torna al sito'                                         => 'Back to site',
		'Privacy'                                               => 'Privacy',
		// Registrazione
		'Sei già registrato.'                                   => 'You are already registered.',
		'Indirizzo email non valido.'                           => 'Invalid email address.',
		'Questo indirizzo email è già registrato.'               => 'This email address is already registered.',
		'La password deve essere di almeno 8 caratteri.'        => 'Password must be at least 8 characters long.',
		'La password deve contenere almeno una lettera maiuscola.' => 'Password must contain at least one uppercase letter.',
		'La password deve contenere almeno una lettera minuscola.' => 'Password must contain at least one lowercase letter.',
		'La password deve contenere almeno un numero.'          => 'Password must contain at least one number.',
		'Si è verificato un errore. Riprova.'                   => 'Something went wrong. Please try again.',
		'Seleziona il tipo di accesso richiesto.'               => 'Please select the required access type.',
		'Registrazione completata. La tua richiesta è in attesa di approvazione da parte dell\'amministratore.' => 'Registration complete. Your request is awaiting approval by the administrator.',
		'Nome'                                                  => 'Name',
		'Il tuo nome'                                           => 'Your name',
		'Email'                                                 => 'Email',
		'email@esempio.it'                                      => 'you@example.com',
		'Minimo 8 caratteri, con maiuscola, minuscola e numero.' => 'At least 8 characters, with uppercase, lowercase and a number.',
		'Minimo 8 caratteri'                                    => 'At least 8 characters',
		'Tipo di accesso'                                       => 'Access type',
		'— Seleziona —'                                         => '— Select —',
		'Distributore'                                          => 'Distributor',
		'Giornalista'                                           => 'Journalist',
		'Il materiale visibile dipende dal tipo scelto e dalla configurazione del sito.' => 'Visible materials depend on the selected type and site configuration.',
		'Registrati'                                            => 'Register',
		'Hai già un account? Accedi'                            => 'Already have an account? Sign in',
		// Media Kit
		'Effettua l\'accesso per visualizzare il Media Kit.'    => 'Please sign in to view the Media Kit.',
		'Altro'                                                 => 'Other',
		'Media Kit'                                             => 'Media Kit',
		'Scarica gli asset e i materiali disponibili.'          => 'Download available assets and materials.',
		'Seleziona tutti'                                       => 'Select all',
		'Scarica ZIP selezione'                                 => 'Download selection as ZIP',
		'Download multiplo ZIP non disponibile su questo server.' => 'Bulk ZIP download is not available on this server.',
		'Filtra e ordina'                                       => 'Filter and sort',
		'Cerca'                                                 => 'Search',
		'Titolo o descrizione…'                                 => 'Title or description…',
		'Cartella'                                              => 'Folder',
		'Tutte le cartelle'                                     => 'All folders',
		'Categoria'                                             => 'Category',
		'Tutte le categorie'                                    => 'All categories',
		'Lingua'                                                => 'Language',
		'Tutte le lingue'                                       => 'All languages',
		'Ordina per'                                            => 'Sort by',
		'Titolo (A-Z)'                                          => 'Title (A–Z)',
		'Data aggiornamento'                                    => 'Last updated',
		'Applica filtri'                                        => 'Apply filters',
		'Reimposta'                                             => 'Reset',
		'Materiali generali'                                    => 'General materials',
		'Espandi o comprimi cartella'                           => 'Expand or collapse folder',
		'Espandi o comprimi elenco'                             => 'Expand or collapse list',
		'Materiali non assegnati a una cartella nel catalogo: sono comunque disponibili per il download.' => 'Materials not assigned to a catalog folder are still available for download.',
		'Nessun risultato con i filtri o la ricerca attuali. Modifica i criteri o reimposta.' => 'No results match your filters or search. Change the criteria or reset.',
		'Reimposta tutto'                                       => 'Reset all',
		'Nessun asset disponibile al momento. Torna più tardi o contatta l\'amministratore.' => 'No assets are available right now. Please try again later or contact the administrator.',
		'Classificazione'                                       => 'Classification',
		'Seleziona per ZIP: %s'                                 => 'Select for ZIP: %s',
		'Scarica'                                               => 'Download',
		'Nessun file'                                           => 'No file',
		// Script frontend (AppearanceService)
		'Invio in corso...'                                     => 'Submitting...',
		'Creazione archivio…'                                   => 'Creating archive…',
		'Seleziona almeno un file.'                             => 'Select at least one file.',
		'Hai superato il numero massimo di file selezionabili.' => 'You have exceeded the maximum number of selectable files.',
		'Download ZIP non disponibile.'                         => 'ZIP download is not available.',
		'Nascondi password'                                     => 'Hide password',
		'Nascondi'                                              => 'Hide',
		'Forza password: debole'                                => 'Password strength: weak',
		'Forza password: discreta'                              => 'Password strength: fair',
		'Forza password: buona'                                 => 'Password strength: good',
	];

	/**
	 * True durante il rendering degli shortcode `*_en` (anche per stringhe JS in enqueue).
	 */
	public static function is_english_ui(): bool {
		return self::$english_ui;
	}

	/**
	 * Esegue una callback adattando l'interfaccia alla lingua della pagina corrente.
	 *
	 * @param callable(): string $callback
	 */
	public static function render_for_page_ui_language( callable $callback ): string {
		if ( self::detect_page_english_ui() ) {
			return self::with_english_ui( $callback );
		}

		return $callback();
	}

	/**
	 * Rileva se la pagina corrente va mostrata con interfaccia inglese.
	 */
	public static function detect_page_english_ui(): bool {
		$override = apply_filters( 'fp_dmk_shortcode_use_english_ui', null );
		if ( is_bool( $override ) ) {
			return $override;
		}

		// TranslatePress (locale tipo en_US / it_IT).
		if ( function_exists( 'trp_get_locale' ) ) {
			$trp = strtolower( strtok( (string) trp_get_locale(), '_' ) );

			return $trp === 'en';
		}

		// Polylang: preferisci la lingua del post (affidabile in singolare), poi quella corrente / cookie.
		if ( function_exists( 'pll_get_post_language' ) && is_singular() ) {
			$post_id = get_queried_object_id();
			if ( $post_id > 0 ) {
				$lang = pll_get_post_language( $post_id, 'slug' );
				if ( is_string( $lang ) && $lang !== '' ) {
					return self::slug_implies_english_ui( $lang );
				}
			}
		}

		if ( function_exists( 'pll_current_language' ) ) {
			$lang = pll_current_language( 'slug' );
			if ( is_string( $lang ) && $lang !== '' ) {
				return self::slug_implies_english_ui( $lang );
			}
		}

		if ( isset( $_COOKIE['pll_language'] ) ) {
			$cookie_lang = sanitize_text_field( wp_unslash( (string) $_COOKIE['pll_language'] ) );
			if ( $cookie_lang !== '' ) {
				return self::slug_implies_english_ui( $cookie_lang );
			}
		}

		// WPML: solo se il core è effettivamente caricato (evita falsi positivi da filtri di terze parti).
		if ( defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( 'SitePress', false ) ) {
			$wpml_lang = apply_filters( 'wpml_current_language', null );
			if ( is_string( $wpml_lang ) && $wpml_lang !== '' ) {
				return self::slug_implies_english_ui( $wpml_lang );
			}
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( $request_uri !== '' ) {
			$path = wp_parse_url( $request_uri, PHP_URL_PATH );
			if ( is_string( $path ) && $path !== '' && preg_match( '#(^|/)(en|eng|english)(/|$)#i', $path ) ) {
				return true;
			}
		}

		if ( is_singular() ) {
			$page_uri = get_page_uri( get_queried_object_id() );
			if ( is_string( $page_uri ) && ( $page_uri === 'en' || str_starts_with( $page_uri, 'en/' ) ) ) {
				return true;
			}
		}

		// Non usare determine_locale(): su molti siti bilingue resta en_US anche per pagine IT → interfaccia errata.
		return false;
	}

	/**
	 * Slug lingua (Polylang/WPML/cookie) → interfaccia inglese del plugin.
	 */
	private static function slug_implies_english_ui( string $slug ): bool {
		$slug = strtolower( trim( $slug ) );

		return $slug === 'en' || str_starts_with( $slug, 'en_' ) || str_starts_with( $slug, 'en-' );
	}

	/**
	 * Esegue una callback con interfaccia inglese (filtro gettext sul dominio fp-dmk).
	 *
	 * @param callable(): string $callback
	 */
	public static function with_english_ui( callable $callback ): string {
		self::$english_ui = true;
		add_filter( 'gettext', [ self::class, 'filter_gettext' ], 10, 3 );
		try {
			return $callback();
		} finally {
			remove_filter( 'gettext', [ self::class, 'filter_gettext' ], 10 );
			self::$english_ui = false;
		}
	}

	/**
	 * @param array|string $atts Attributi shortcode WordPress.
	 * @return array<string, mixed>
	 */
	public static function normalize_atts( $atts ): array {
		return is_array( $atts ) ? $atts : [];
	}

	/**
	 * @param mixed $translation Traduzione corrente.
	 */
	public static function filter_gettext( $translation, string $text, string $domain ): string {
		if ( $domain !== 'fp-dmk' ) {
			return is_string( $translation ) ? $translation : (string) $translation;
		}
		if ( isset( self::EN_MAP[ $text ] ) ) {
			return self::EN_MAP[ $text ];
		}
		return is_string( $translation ) ? $translation : (string) $translation;
	}

	/**
	 * Etichetta segmento audience per shortcode EN (slug + label da impostazioni).
	 */
	public static function translate_audience_segment_label( string $slug, string $label, string $label_en = '' ): string {
		if ( ! self::is_english_ui() ) {
			return $label;
		}

		return AudienceService::get_segment_display_label(
			[
				'slug'     => $slug,
				'label'    => $label,
				'label_en' => $label_en,
			],
			true
		);
	}
}
