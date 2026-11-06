<?php
/**
 * MU-Plugin Loader Installer
 *
 * @package Perdives\ManagePlugins
 */

namespace Perdives\ManagePlugins;

/**
 * Installs and removes the must-use loader that makes the plugin load before regular plugins.
 *
 * When the plugin is activated as a regular plugin, a small loader is written to
 * wp-content/mu-plugins/. It requires the main plugin file from the plugins directory
 * (relative to WP_PLUGIN_DIR, so it survives moving the site) and does nothing when
 * the plugin folder is gone. A symlink would do the same, but isn't allowed on every
 * host and causes include warnings once its target is removed.
 */
class MuLoader {

	/**
	 * Loader file name in the mu-plugins directory.
	 *
	 * @var string
	 */
	const FILENAME = 'manage-plugins-loader.php';

	/**
	 * Marker used to recognise (and only ever delete) the generated loader.
	 *
	 * @var string
	 */
	const MARKER = 'perdives-manage-plugins-loader v1';

	/**
	 * Function defined by the manually copied mu-plugin-loader.php.
	 *
	 * @var string
	 */
	const MANUAL_LOADER_FUNCTION = 'perdives_manage_plugins_load_from_mu_plugin';

	/**
	 * Absolute path of the generated loader.
	 *
	 * @return string
	 */
	public static function path() {
		return WPMU_PLUGIN_DIR . '/' . self::FILENAME;
	}

	/**
	 * Contents of the generated loader.
	 *
	 * @return string
	 */
	public static function contents() {
		$relative = plugin_basename( PERDIVES_MANAGE_PLUGINS_FILE );
		$marker   = self::MARKER;

		return <<<PHP
<?php
/**
 * Plugin Name: Manage Plugins (loader)
 * Description: Loads Manage Plugins as a must-use plugin so its rules apply before regular plugins load. Managed automatically: removed when Manage Plugins is deactivated.
 */

// {$marker}

\$perdives_manage_plugins_file = WP_PLUGIN_DIR . '/{$relative}';
if ( is_readable( \$perdives_manage_plugins_file ) ) {
	define( 'PERDIVES_MANAGE_PLUGINS_MANAGED_LOADER', true );
	require_once \$perdives_manage_plugins_file;
}
unset( \$perdives_manage_plugins_file );

PHP;
	}

	/**
	 * Whether the generated loader exists and is up to date.
	 *
	 * @return bool
	 */
	public static function is_current() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		return is_readable( self::path() ) && file_get_contents( self::path() ) === self::contents();
	}

	/**
	 * Whether a manually copied mu-plugin-loader.php is present (e.g. Composer installs).
	 *
	 * @return bool
	 */
	public static function has_manual_loader() {
		foreach ( (array) glob( WPMU_PLUGIN_DIR . '/*.php' ) as $file ) {
			if ( basename( $file ) === self::FILENAME ) {
				continue;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
			if ( strpos( (string) file_get_contents( $file ), self::MANUAL_LOADER_FUNCTION ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Write the loader (activation hook). Skipped when a manual loader is already present.
	 *
	 * @return bool True when a loader is in place.
	 */
	public static function install() {
		if ( self::has_manual_loader() ) {
			return true;
		}

		if ( ! wp_mkdir_p( WPMU_PLUGIN_DIR ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Runs before WP_Filesystem is available.
		return file_put_contents( self::path(), self::contents(), LOCK_EX ) !== false;
	}

	/**
	 * Delete the generated loader (deactivation hook). Never touches a manually copied loader.
	 *
	 * @return void
	 */
	public static function remove() {
		$path = self::path();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		if ( is_file( $path ) && strpos( (string) file_get_contents( $path ), self::MARKER ) !== false ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Re-create a missing or outdated loader while the plugin is active.
	 *
	 * @return void
	 */
	public static function maybe_repair() {
		if ( ! current_user_can( 'activate_plugins' ) || ! Plugin::is_activated() || self::has_manual_loader() ) {
			return;
		}

		if ( ! self::is_current() ) {
			self::install();
		}
	}
}
