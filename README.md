# FP Distributor Media Kit

![Version](https://img.shields.io/badge/version-1.19.1-blue)

Area riservata per distributori: registrazione, approvazione admin, download asset protetti e notifiche email.

## Caratteristiche

- **Registrazione utenti** (frontend) con approvazione admin obbligatoria
- **Login/Logout** dedicato
- **Media Kit** protetto: solo utenti approvati
- **Asset** organizzati per **categoria** (tipo di materiale) e opzionalmente per **cartelle** gerarchiche (ordinamento nel Media Kit)
- **Download sicuro** tramite proxy (nessun link diretto ai file)
- **Tracking download** per ogni asset
- **Lista utenti** con tutti i distributori, filtro stato e azioni Approva/Revoca
- **Report** con statistiche generali, attività per utente (cosa scaricano) e per asset (chi scarica)
- **Notifica email** a tutti i distributori approvati (manual o automatica su nuovo asset)
- **Tipi di accesso** (opzionale): in registrazione l’utente sceglie il tipo (es. distributore / giornalista); in impostazioni puoi limitare quali **categorie di asset** vede ogni tipo

## Compatibilità FP Mail SMTP

Le email del Media Kit usano `wp_mail()`, quindi **FP Mail SMTP** gestisce automaticamente l'invio via SMTP quando è attivo. In **FP Media Kit → Impostazioni → Email** trovi l'opzione *Usa mittente da FP Mail SMTP*: se attivata, le notifiche useranno il mittente configurato in FP Mail SMTP invece di quello del Media Kit. Le email vengono registrate nel log di FP Mail SMTP.

## Requisiti

- WordPress 6.0+
- PHP 8.0+

## Installazione

1. Carica la cartella `FP-Distributor-Media-Kit` in `wp-content/plugins/`
2. Attiva il plugin dalla schermata Plugin
3. Vai in **FP Media Kit → Impostazioni** per configurare pagine e aspetto
4. Crea le pagine necessarie e inserisci gli shortcode
5. Personalizza colori e stili nella sezione **Aspetto** (bottoni, card, sfondi)

## Ruoli e permessi

- **Administrator**: ha tutte le funzioni del plugin (capability assegnate automaticamente all’attivazione / al primo caricamento).
- **FP Media Kit Manager** (`fp_dmk_manager`): ruolo dedicato creato dal plugin. Assegnalo da **Utenti → il profilo** (o con un plugin ruoli) agli operatori che devono gestire approvazioni, asset, report e impostazioni del Media Kit **senza** essere amministratori del sito. Include `read`, `upload_files` (libreria media per i file asset) e tutte le capability `manage_fp_dmk` / `fp_dmk_asset*` / `manage_fp_dmk_categories`. Per anteprima e test, può accedere anche al frontend (login, shortcode, download) come staff grazie a `manage_fp_dmk`.

Alla **disattivazione** del plugin le capability del Media Kit vengono rimosse da Administrator e dal ruolo Gestore (il ruolo resta nel sito ma senza permessi finché non riattivi il plugin).

## Shortcode

| Shortcode | Descrizione |
|-----------|-------------|
| `[fp_dmk_register]` | Form registrazione (email, password, nome; se abilitato: tipo di accesso) |
| `[fp_dmk_login]` | Form login |
| `[fp_dmk_media_kit]` | Griglia asset per cartella e categoria (solo utenti approvati); attributi opzionali: `category`, `language`, `folder` (slug cartella) |

## Pagine da configurare

1. **Pagina Media Kit**: inserisci `[fp_dmk_media_kit]` — accesso limitato agli utenti approvati
2. **Pagina Login**: inserisci `[fp_dmk_login]`
3. **Pagina Registrazione**: inserisci `[fp_dmk_register]`

Dopo aver creato le pagine, configurale in **FP Media Kit → Impostazioni**.

## Hook e filtri

| Hook/Filtro | Descrizione |
|-------------|-------------|
| `do_action('fp_dmk_asset_published', $asset_id)` | Eseguito dopo publish di un asset |
| `apply_filters('fp_dmk_allowed_mime_types', $types)` | Estende i tipi file consentiti per upload |
| `apply_filters('fp_dmk_email_subject', $subject)` | Personalizza l'oggetto email notifica |
| `apply_filters('fp_dmk_allowed_asset_category_slugs', $slugs, $user_id, $segment_slug)` | Elenco slug categorie asset consentite per l’utente (solo con audience attiva) |
| `apply_filters('fp_dmk_bulk_first_admin_menu', true)` | Se `false`, menu admin classico (nessun redirect al bulk dal top-level; voci Categorie, Cartelle, CPT e Aggiungi nuovo standard) |

## Struttura

```
fp-dmk-capabilities.php   # elenco capability (bootstrap + uninstall)
src/
├── Core/Plugin.php
├── Admin/AssetManager (CPT, categorie `fp_dmk_category`, cartelle `fp_dmk_folder`), UserApprovalPage, UsersListPage, SettingsPage, NotifyUsersPage
├── User/RegistrationHandler, ApprovalService, AudienceService
├── Frontend/ShortcodeMediaKit, ShortcodeLogin, ShortcodeRegister, RestrictedContent
├── Download/ProxyController, TrackingService
└── Email/NotificationService
```

## Autore

**Francesco Passeri**
- Sito: [francescopasseri.com](https://francescopasseri.com)
- Email: [info@francescopasseri.com](mailto:info@francescopasseri.com)
- GitHub: [github.com/franpass87](https://github.com/franpass87)
