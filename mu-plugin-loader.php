<?php
/**
 * MU-Plugin Loader for Manage Plugins
 *
 * This file should be copied to wp-content/mu-plugins/ to ensure the
 * Manage Plugins plugin loads before all other plugins.
 *
 * MU-plugins load before regular plugins, which is essential for this
 * plugin to properly filter which plugins WordPress loads.
 *
 * @package Perdives\ManagePlugins
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load the Manage Plugins plugin
 *
 * This function attempts to locate and load the main plugin file
 * from various possible installation locations.
 */
function perdives_manage_plugins_load_from_mu_plugin() {
	$possible_paths = array(
		// Installed via Composer in project root.
		dirname( ABSPATH ) . '/vendor/perdives/manage-plugins/manage-plugins.php',
		// Installed via Composer in wp-content.
		WP_CONTENT_DIR . '/vendor/perdives/manage-plugins/manage-plugins.php',
		// Installed as regular plugin.
		WP_PLUGIN_DIR . '/manage-plugins/manage-plugins.php',
		// Installed as regular plugin with different folder name.
		WP_PLUGIN_DIR . '/perdives-manage-plugins/manage-plugins.php',
	);

	foreach ( $possible_paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}

	// Plugin not found - log error.
	error_log(
		'Manage Plugins MU-Plugin Loader: Could not locate the main plugin file. ' .
		'Please ensure the plugin is installed in one of the expected locations.'
	);
}

// Load the plugin.
perdives_manage_plugins_load_from_mu_plugin();
