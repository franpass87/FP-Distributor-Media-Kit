# Changelog

## [1.18.0] - 2026-04-23

### Added

- **Caricamento multiplo · ordinamento colonne** nella tabella file in coda: File, Titolo, Lingua e Cartella sono ordinabili. Click sull'intestazione per alternare asc/desc, con indicatore ▲/▼ e `aria-sort`. Le nuove righe (aggiunte da upload o dalla Libreria media) vengono inserite nel posto giusto mantenendo l'ordine corrente.

## [1.17.0] - 2026-04-23

### Added

- **Caricamento multiplo · breadcrumb cartella attiva** nella toolbar: mostra il percorso («Radice › Brochure › 2026») con ogni segmento cliccabile per saltare a quel livello. Si aggiorna automaticamente dopo selezione, creazione, spostamento o eliminazione di cartelle.

## [1.16.0] - 2026-04-23

### Added

- **Caricamento multiplo · drag multiplo**: se trascini una riga che fa parte del set selezionato (checkbox attiva), tutte le righe selezionate vengono spostate insieme nella cartella target. Durante il drag multipli viene mostrato un **badge ghost** con il contatore («N file selezionati»). Se la riga trascinata non è selezionata, il comportamento resta singolo come prima.

## [1.15.0] - 2026-04-23

### Added

- **Caricamento multiplo · albero cartelle**: spostamento delle cartelle tramite **drag-and-drop del nodo** su un altro nodo (o sulla radice per promuoverla al livello superiore). Validazione anti-ciclo lato client (blocco visivo del drop target) e server (un nodo non può finire dentro un suo discendente). Il DOM viene riorganizzato in tempo reale con aggiornamento della profondità di tutti i discendenti; le etichette delle select cartella restano valide (value = term_id) e l'indentazione `—` si riallinea al prossimo ricarico pagina.
- Nuovo endpoint AJAX `fp_dmk_move_folder` (stesso nonce/capability degli altri endpoint cartelle).

## [1.14.0] - 2026-04-23

### Added

- **Caricamento multiplo · albero cartelle**: azioni di gestione direttamente dai nodi (appaiono in hover/focus):
  - **Rinomina** inline con input sibling al nodo (invio = salva, Esc = annulla).
  - **Elimina** con conferma: se la cartella è vuota chiede semplice conferma; se contiene asset avverte quanti e li sposta nella cartella superiore (o li scollega se la cartella era in radice); se ci sono sottocartelle blocca l'operazione.
- Nuovi endpoint AJAX server-side: `fp_dmk_rename_folder`, `fp_dmk_delete_folder` (riutilizzano il nonce `fp_dmk_create_folder`, capability `manage_fp_dmk_categories`).

## [1.13.0] - 2026-04-23

### Added

- **Caricamento multiplo · albero cartelle**: ogni nodo mostra a destra un **badge con il conteggio degli asset** contenuti (ricorsivo, somma asset della cartella + discendenti). Nodo attivo: badge invertito per leggibilità.
- `AssetManager::get_folder_tree_nested()` espone i nuovi campi `count` (asset diretti, da `WP_Term->count`) e `count_deep` (cumulativo).

## [1.12.0] - 2026-04-23

### Added

- **Caricamento multiplo**: supporto al **drop di directory intere** dal sistema operativo (Chromium, Firefox, Safari). Il plugin scansiona ricorsivamente la struttura via `webkitGetAsEntry` (limiti di sicurezza: 2000 file, profondità 20), chiede conferma con riepilogo file/cartelle, crea in batch le cartelle mancanti nella tassonomia tramite nuovo endpoint AJAX `fp_dmk_ensure_folder_paths`, quindi carica ogni file associandolo automaticamente alla sotto-cartella corrispondente. Richiede la capability `manage_fp_dmk_categories` per creare cartelle: senza, i file nelle sottocartelle vengono scartati e vengono caricati solo quelli al livello radice.

## [1.11.0] - 2026-04-23

### Added

- **Caricamento multiplo**: **selezione multipla** delle righe in coda (checkbox per riga + master nel thead con stato indeterminato) e **toolbar azioni in blocco**: «Sposta in cartella selezionata» (usa la cartella evidenziata nell'albero), «Imposta lingua predefinita», «Applica categorie predefinite», «Rimuovi» (con conferma). Le azioni toccano solo le righe selezionate, non i file ancora in upload.

## [1.10.0] - 2026-04-23

### Added

- **Caricamento multiplo**: feedback UX durante l'upload — riga placeholder per ogni file con **barra di avanzamento** in tempo reale (via `XMLHttpRequest.upload.onprogress`, fino a 3 in parallelo), **anteprima thumbnail** nella prima colonna per le immagini (fallback con icona dashicons per gli altri tipi), **conferma `beforeunload`** se si prova a lasciare la pagina con righe pronte e non ancora salvate.

## [1.9.2] - 2026-04-21

### Changed

- Pagina **Caricamento multiplo**: layout tipo explorer con **albero cartelle** a sinistra (espansione nodi, cartella attiva, drop target per righe), **zona drag-and-drop** per upload diretto nella Libreria media via REST (`/wp/v2/media`, fino a **3 upload in parallelo**), filtro opzionale «solo righe con la cartella selezionata», accessibilità tastiera sulla dropzone. Script dedicato `assets/js/bulk-upload.js` + `AssetManager::get_folder_tree_nested()` per i dati gerarchici.

## [1.9.0] - 2026-04-21

### Added

- Nuova voce di menu **FP Media Kit → Caricamento multiplo**: seleziona N file dalla Libreria media in una sola volta e crea in blocco gli asset del Media Kit. Valori predefiniti (lingua, cartella, categorie) applicati a tutti i file, con override per singolo file su titolo, descrizione, lingua, cartella e categorie. Gli asset vengono creati in stato pubblicato e innescano l'hook `fp_dmk_asset_published` (quindi scatta anche la notifica automatica ai distributori, se abilitata).
- Bottone **"+ Nuova cartella"** inline nel metabox del singolo asset e nella pagina Caricamento multiplo: crea una cartella (con parent opzionale) via AJAX senza ricaricare la pagina; la nuova cartella viene aggiunta a tutte le select cartella della schermata corrente (bulk: anche alle righe file già presenti). Richiede la capability `manage_fp_dmk_categories`.

## [1.8.0] - 2026-03-27

### Added

- Tassonomia gerarchica **Cartelle** (`fp_dmk_folder`): organizza gli asset in cartelle/sottocartelle; voce di menu **FP Media Kit → Cartelle** (stessi permessi delle Categorie).
- Metabox asset: select **Cartella** (una sola; se ne restano più assegnate, viene mantenuta la più specifica in profondità).
- Lista asset in admin: colonna **Cartella** (percorso con « › »).
- Media Kit frontend: raggruppamento per **cartella** poi per **categoria**; filtro **Tutte le cartelle** nel form; attributo shortcode `folder="slug"`.

## [1.7.1] - 2026-03-27

### Fixed

- `AudienceService::is_audience_enabled()`: considera la segmentazione disattiva se non c’è alcun segmento valido (evita registrazioni bloccate con `invalid_segment` quando il flag è on ma l’elenco segmenti è vuoto).

## [1.7.0] - 2026-03-27

### Added

- **Tipi di accesso (audience)**: in **Impostazioni** puoi abilitare segmenti (es. distributore / giornalista), opzionalmente **limitare le categorie asset** per tipo (checkbox «Limita categorie» + selezione categorie; senza limite = tutte le categorie).
- Shortcode registrazione: campo **Tipo di accesso** obbligatorio quando la segmentazione è attiva.
- **Lista utenti**: colonna e form per modificare il tipo di accesso (meta `fp_dmk_segment`).
- Email «nuova registrazione in attesa» all’admin: riga con **Tipo di accesso** se presente.
- Filtro `fp_dmk_allowed_asset_category_slugs` sulle categorie consentite per utente (vedi `AudienceService::get_allowed_category_slugs_for_user`).

## [1.6.1] - 2026-03-27

### Fixed

- `ApprovalService::is_approved()`: bypass anche per capability **`manage_fp_dmk`** (ruolo FP Media Kit Manager), così lo staff può usare login frontend, shortcode Media Kit e download proxy senza essere bloccato come “non approvato”.

## [1.6.0] - 2026-03-27

### Added

- Ruolo WordPress **FP Media Kit Manager** (`fp_dmk_manager`): accesso completo alle schermate del plugin, asset, categorie, upload in libreria media (`upload_files`), senza permessi da amministratore generico.

### Changed

- CPT **Media Kit Asset** e tassonomia **Categorie** usano capability dedicate (non più `edit_posts` / `manage_categories` di WordPress). **Administrator** riceve automaticamente le nuove capability al caricamento del plugin. Altri ruoli (es. Editore) che in passato potevano toccare gli asset solo perché avevano `edit_posts` **non** hanno più accesso, salvo assegnazione manuale delle capability `fp_dmk_*` o del nuovo ruolo.

## [1.5.8] - 2026-03-27

### Fixed

- Capability `manage_fp_dmk` per il ruolo **Administrator** riassegnata automaticamente al caricamento del plugin se mancante (installazione/aggiornamento senza riattivazione: prima compariva “non hai il permesso” pur essendo admin).

## [1.5.7] - 2026-03-24

### Changed

- Email HTML del plugin (distributori, registrazione in attesa, report download giornaliero): corpo frammento avvolto da **FP Mail SMTP** (`fp_fpmail_brand_html`) se disponibile; documenti HTML completi lasciati invariati.

## [1.5.6] - 2026-03-23

### Fixed

- Intestazioni tabella Categorie e Asset: testo e icone ordinamento bianchi su sfondo viola (contrasto e leggibilità)

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
