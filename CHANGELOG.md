# Changelog

## [1.5.5] - 2026-03-23

### Fixed

- Pagine **Media Kit Asset** e **Categorie** aggiornate al design system FP: lista asset con card, thead viola, tablenav; taxonomy con form-wrap card, tabella categorie, postbox stile FP; add/edit asset con postbox come card e input stilizzati

## [1.5.4] - 2026-03-23

### Fixed

- Grafica sezioni e box allineata al design system FP: card hover, thead viola, input/textarea con focus ring, descrizioni

## [1.5.3] - 2026-03-23

### Added

- Pagina **Lista utenti**: tutti i distributori con filtro (Tutti/Approvati/In attesa), n. download, azioni Approva/Revoca

## [1.5.2] - 2026-03-23

### Added

- Compatibilità FP Mail SMTP: opzione "Usa mittente da FP Mail SMTP" in Impostazioni → Email

## [1.5.1] - 2026-03-23

### Fixed

- Banner grafica FP unificato su tutte le pagine: lista asset, aggiungi/modifica asset, categorie

## [1.5.0] - 2026-03-23

### Added

- Pagina admin **Report** con statistiche generali, attività per utente e per asset
- KPI: totale download, utenti attivi, asset pubblicati, distributori approvati, in attesa
- Attività recente: ultimi 15 download con asset, utente e data
- Top 10 asset più scaricati e Top 10 utenti per download
- Report "Cosa scarica ogni utente": tabella con utente, n. download, asset scaricati
- Report "Chi scarica ogni asset": tabella con asset, categoria, n. download, utenti
- Export CSV per report per utente e per asset

## [1.4.2] - 2026-03-23

### Changed

- Grafica admin unificata al design system FP: header con `color: white !important` e `text-shadow`, body selector `fpdmk-admin-shell`, menu position 56.13
- Enqueue CSS centralizzato in UserApprovalPage con pattern strpos + post_type/taxonomy per compatibilità menu parent
- Rimossi stili inline da SettingsPage e NotifyUsersPage (classi CSS dedicate)

### Fixed

- Enqueue admin su tutte le pagine plugin (CPT, taxonomy, submenu)

## [1.4.1] - 2026-03-23
### Added
- Eventi `fp_tracking_event` per integrazione FP-Marketing-Tracking-Layer: `dmk_asset_downloaded`, `dmk_login_success`, `dmk_login_failed`, `dmk_login_blocked_not_approved`, `dmk_registration_submitted`, `dmk_user_approved`

### Fixed
- Enqueue admin CSS su pagine fp-dmk con fallback su `$_GET['page']` per menu sotto parent

## [1.4.0] - 2025-03-20

### Added

- Validazione password forte: maiuscola, minuscola, numero (oltre a min 8 caratteri)
- Hint password in registrazione: "Minimo 8 caratteri, con maiuscola, minuscola e numero"
- Link "Non hai un account? Registrati" nella pagina login
- Link "Hai già un account? Accedi" nella pagina registrazione
- Hint "Riceverai un'email per reimpostare la password..." sotto il form login
- Filtri Media Kit: dropdown categoria e lingua con form GET
- Pagina admin "Log download" con tabella e pulsante Esporta CSV
- Export CSV log download (separatore ;, fino a 50000 record)
- Cron giornaliero pulizia log: opzione "Pulizia log download (giorni)" in Impostazioni
- Accessibilità: `role="alert"` su messaggi errore, `aria-describedby` e `aria-required` sui campi

### Changed

- Messaggio card asset senza file: "Nessun file associato" (coerenza terminologica)
- TrackingService: nuovi metodi `get_all_for_export()` e `purge_older_than_days()`

## [1.3.0] - 2025-03-20

### Added

- Checkbox "Ricordami" nel form login frontend
- Indicatore caricamento form: bottone disabilitato e testo "Invio in corso..." su submit
- Script frontend `assets/js/frontend.js` per gestione submit form login e registrazione
- Classe `.fpdmk-empty-state` per messaggio Media Kit vuoto

### Changed

- Messaggio Media Kit vuoto: testo esteso "Torna più tardi o contatta l'amministratore"
- ShortcodeRegister: sanitizzazione `$_GET['fp_dmk_registered']` (solo '1' mostra successo)
- Stili checkbox e empty state nel frontend CSS

## [1.2.0] - 2025-03-20

### Added

- Bypass approvazione per admin ed editor: accedono al Media Kit senza meta `fp_dmk_approved`
- Link "Password dimenticata?" nel form login frontend
- Supporto username oltre all'email nel campo login (placeholder "email@esempio.it o username")
- Attributi `autocomplete` su campi login per migliore UX

### Changed

- Token CSS design system: `--fpdms-secondary`, `--fpdms-gradient-secondary`, `--shadow-lg`
- Bottone success admin: usa gradiente verde `--fpdms-gradient-secondary` invece di colore piatto
- Sanitizzazione `$_GET` per notice admin (fp_dmk_saved, fp_dmk_approved)

### Fixed

- RegistrationHandler: usa `ApprovalService::is_approved()` per consistenza con bypass admin

## [1.1.0] - 2025-03-20

### Added

- Sezione **Aspetto** in Impostazioni per personalizzare colori e stili del frontend
- Opzioni: bottone primario (gradiente), bottone secondario, sfondo card, bordo card, sfondo sezione, bordi input, raggi bordi
- Color picker WordPress per tutti i campi colore

## [1.0.0] - 2025-03-20

### Added

- Registrazione utenti frontend con approvazione admin
- Form login e logout
- CPT `fp_dmk_asset` con categorie (Visual, Tech, Copy, Brand) e lingua (IT/EN)
- Shortcode `[fp_dmk_media_kit]` con griglia asset a card
- Shortcode `[fp_dmk_login]` e `[fp_dmk_register]`
- Download sicuro tramite proxy (nonce, verifica utente approvato)
- Tracking download in tabella `wp_fp_dmk_downloads`
- Pagina admin "Utenti da approvare" con azione Approva
- Pagina admin "Notifica utenti" per invio email a distributori approvati
- Pagina impostazioni (pagine Media Kit, Login, Registrazione, email, notifica automatica)
- Restrizione accesso pagina Media Kit a utenti loggati e approvati
