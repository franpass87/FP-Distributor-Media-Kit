# Changelog

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
