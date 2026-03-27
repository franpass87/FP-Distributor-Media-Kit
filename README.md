# FP Distributor Media Kit

![Version](https://img.shields.io/badge/version-1.5.8-blue)

Area riservata per distributori: registrazione, approvazione admin, download asset protetti e notifiche email.

## Caratteristiche

- **Registrazione utenti** (frontend) con approvazione admin obbligatoria
- **Login/Logout** dedicato
- **Media Kit** protetto: solo utenti approvati
- **Asset** organizzati per categoria (Visual Assets, Tech Sheets, Copy Templates, Brand Voice Guide)
- **Download sicuro** tramite proxy (nessun link diretto ai file)
- **Tracking download** per ogni asset
- **Lista utenti** con tutti i distributori, filtro stato e azioni Approva/Revoca
- **Report** con statistiche generali, attività per utente (cosa scaricano) e per asset (chi scarica)
- **Notifica email** a tutti i distributori approvati (manual o automatica su nuovo asset)

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

## Shortcode

| Shortcode | Descrizione |
|-----------|-------------|
| `[fp_dmk_register]` | Form registrazione (email, password, nome) |
| `[fp_dmk_login]` | Form login |
| `[fp_dmk_media_kit]` | Griglia asset per categoria (solo utenti approvati) |

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

## Struttura

```
src/
├── Core/Plugin.php
├── Admin/AssetManager, UserApprovalPage, UsersListPage, SettingsPage, NotifyUsersPage
├── User/RegistrationHandler, ApprovalService
├── Frontend/ShortcodeMediaKit, ShortcodeLogin, ShortcodeRegister, RestrictedContent
├── Download/ProxyController, TrackingService
└── Email/NotificationService
```

## Autore

**Francesco Passeri**
- Sito: [francescopasseri.com](https://francescopasseri.com)
- Email: [info@francescopasseri.com](mailto:info@francescopasseri.com)
- GitHub: [github.com/franpass87](https://github.com/franpass87)
