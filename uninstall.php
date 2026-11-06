<?php
/**
 * Uninstall: remove the generated must-use loader.
 *
 * @package Perdives\ManagePlugins
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$perdives_manage_plugins_loader = WPMU_PLUGIN_DIR . '/manage-plugins-loader.php';

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
if ( is_file( $perdives_manage_plugins_loader ) && strpos( (string) file_get_contents( $perdives_manage_plugins_loader ), 'perdives-manage-plugins-loader v1' ) !== false ) {
	wp_delete_file( $perdives_manage_plugins_loader );
}
