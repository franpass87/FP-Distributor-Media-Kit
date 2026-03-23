=== FP Distributor Media Kit ===

Contributors: franpass87
Tags: media kit, distributor, download, private area, user approval
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.5.2
Requires PHP: 8.0
License: Proprietary
License URI: https://francescopasseri.com

Area riservata per distributori: registrazione, approvazione admin, download asset protetti.

== Description ==

Plugin per creare un'area riservata "Media Kit" dove i distributori approvati possono scaricare asset (immagini, PDF, video).

* Registrazione frontend con approvazione admin obbligatoria
* Login/Logout dedicato
* Asset organizzati per categoria e lingua
* Download sicuro (nessun link diretto ai file)
* Tracking download
* Notifica email a distributori approvati

== Installation ==

1. Carica la cartella in wp-content/plugins/
2. Attiva il plugin
3. Configura le pagine in FP Media Kit → Impostazioni
4. Inserisci gli shortcode nelle pagine create

Shortcode: [fp_dmk_register], [fp_dmk_login], [fp_dmk_media_kit]

== Changelog ==

= 1.5.2 =
* Compatibilità FP Mail SMTP: opzione mittente in Impostazioni

= 1.5.1 =
* Banner grafica FP unificato su lista asset, aggiungi/modifica asset, categorie

= 1.5.0 =
* Pagina Report: statistiche generali, attività per utente e per asset
* KPI, Top 10 asset/utenti, export CSV

= 1.4.2 =
* Grafica admin unificata al design system FP
* Enqueue CSS centralizzato con pattern strpos + post_type/taxonomy
* Menu position 56.13, admin body class fpdmk-admin-shell

= 1.4.1 =
* Eventi fp_tracking_event per integrazione FP-Marketing-Tracking-Layer
* Fix enqueue admin CSS su menu sotto parent

= 1.4.0 =
* Validazione password forte (maiuscola, minuscola, numero)
* Link incrociati Login/Registrazione
* Filtri Media Kit (categoria, lingua)
* Pagina Log download con export CSV
* Cron pulizia log automatica
* Accessibilità migliorata

= 1.3.0 =
* Checkbox "Ricordami" nel form login
* Indicatore caricamento su submit form
* Messaggio Media Kit vuoto migliorato
* Sanitizzazione fp_dmk_registered

= 1.2.0 =
* Bypass approvazione per admin/editor
* Link "Password dimenticata?" nel form login
* Supporto username nel login (oltre all'email)
* Token CSS design system allineati
* Sanitizzazione $_GET nelle notice admin

= 1.1.0 =
* Aggiunta sezione Aspetto: personalizza colori bottoni, sfondi card, bordi

= 1.0.0 =
* Release iniziale
