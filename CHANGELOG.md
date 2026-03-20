# Changelog

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
