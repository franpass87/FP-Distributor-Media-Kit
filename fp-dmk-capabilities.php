<?php
/**
 * Elenco capability del plugin (menu, CPT fp_dmk_asset, tassonomia).
 *
 * @package FP\DistributorMediaKit
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capability gestite dal plugin (assegnate a Administrator e al ruolo dedicato).
 *
 * @return list<string>
 */
function fp_dmk_plugin_capability_names(): array {
	return [
		'manage_fp_dmk',
		'edit_fp_dmk_asset',
		'read_fp_dmk_asset',
		'delete_fp_dmk_asset',
		'edit_fp_dmk_assets',
		'edit_others_fp_dmk_assets',
		'delete_fp_dmk_assets',
		'publish_fp_dmk_assets',
		'read_private_fp_dmk_assets',
		'delete_private_fp_dmk_assets',
		'delete_published_fp_dmk_assets',
		'delete_others_fp_dmk_assets',
		'edit_private_fp_dmk_assets',
		'edit_published_fp_dmk_assets',
		'manage_fp_dmk_categories',
	];
}

/**
 * Capability di base per il ruolo Gestore (accesso wp-admin, libreria media per metabox asset).
 *
 * @return list<string>
 */
function fp_dmk_manager_role_base_capability_names(): array {
	return [ 'read', 'upload_files' ];
}
