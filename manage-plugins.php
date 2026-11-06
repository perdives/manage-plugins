<?php
/**
 * Plugin Name: Manage Plugins
 * Plugin URI: https://perdives.com/plugins/manage-plugins
 * Description: Manage which WordPress plugins are allowed to run. Control plugin activation through configuration with support for environment-based rules and global settings.
 * Version: 1.1.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Perdives
 * Author URI: https://perdives.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: manage-plugins
 * Domain Path: /languages
 *
 * @package Perdives\ManagePlugins
 * @copyright 2025 Perdives
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Loaded twice when running as must-use plugin (loader + regular plugin list): only boot once.
if ( defined( 'PERDIVES_MANAGE_PLUGINS_FILE' ) ) {
	return;
}

define( 'PERDIVES_MANAGE_PLUGINS_FILE', __FILE__ );

/**
 * Initialize the Manage Plugins plugin.
 */
function perdives_manage_plugins_init() {
	Perdives\ManagePlugins\Plugin::get_instance();
}

// Use the Perdives PHP Support Notices package to check compatibility BEFORE loading autoloader.
// This prevents fatal parse errors from Composer dependencies that require PHP 7.4+ syntax.
require_once __DIR__ . '/vendor/perdives/php-support-notices-for-wordpress/standalone-checker.php';

if ( ! perdives_check_php_version( __FILE__, '7.4' ) ) {
	// PHP version not supported - admin notice hooked, stop loading.
	return;
}

// PHP version is supported - safe to load autoloader and dependencies.
require_once __DIR__ . '/vendor/autoload.php';

// Initialize Plugin Update Checker.
$perdives_manage_plugins_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/perdives/manage-plugins',
	__FILE__,
	'manage-plugins'
);

// Check for GitHub releases instead of branch commits. Releases also carry a .sha256
// checksum file, so only accept the zip as update package.
$perdives_manage_plugins_update_checker->getVcsApi()->enableReleaseAssets( '/\.zip$/i' );

// Install the must-use loader on activation, remove it on deactivation.
register_activation_hook( __FILE__, array( Perdives\ManagePlugins\MuLoader::class, 'install' ) );
register_deactivation_hook( __FILE__, array( Perdives\ManagePlugins\MuLoader::class, 'remove' ) );

// Initialize the plugin immediately (not on plugins_loaded).
// This must run early to filter plugins before they load.
perdives_manage_plugins_init();
