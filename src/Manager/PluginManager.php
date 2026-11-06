<?php
/**
 * Plugin Manager
 *
 * @package Perdives\ManagePlugins
 */

namespace Perdives\ManagePlugins\Manager;

use Perdives\ManagePlugins\Config\ConfigLoader;

/**
 * Manages plugin activation/deactivation based on configuration
 */
class PluginManager {

	/**
	 * Configuration loader instance
	 *
	 * @var ConfigLoader
	 */
	private $config_loader;

	/**
	 * Disabled plugins cache
	 *
	 * @var array|null
	 */
	private $disabled_plugins = null;

	/**
	 * Required plugins cache
	 *
	 * @var array|null
	 */
	private $required_plugins = null;

	/**
	 * Constructor
	 *
	 * @param ConfigLoader $config_loader Configuration loader.
	 */
	public function __construct( ConfigLoader $config_loader ) {
		$this->config_loader = $config_loader;
	}

	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Filter active plugins before WordPress loads them.
		add_filter( 'option_active_plugins', array( $this, 'filter_active_plugins' ), 1 );
		add_filter( 'site_option_active_sitewide_plugins', array( $this, 'filter_network_active_plugins' ), 1 );

		// When WordPress saves the (filtered) list, e.g. because an admin activates another
		// plugin, restore the stored state of managed plugins so the rules never end up
		// persisted in the database.
		add_filter( 'pre_update_option_active_plugins', array( $this, 'protect_active_plugins' ), PHP_INT_MAX );
		add_filter( 'pre_update_site_option_active_sitewide_plugins', array( $this, 'protect_network_active_plugins' ), PHP_INT_MAX );
	}

	/**
	 * Get list of disabled plugins
	 *
	 * @return array
	 */
	private function get_disabled_plugins() {
		if ( $this->disabled_plugins === null ) {
			$this->disabled_plugins = $this->config_loader->get_disabled_plugins();
		}
		return $this->disabled_plugins;
	}

	/**
	 * Get list of required plugins
	 *
	 * @return array
	 */
	private function get_required_plugins() {
		if ( $this->required_plugins === null ) {
			$this->required_plugins = $this->config_loader->get_required_plugins();
		}
		return $this->required_plugins;
	}

	/**
	 * Filter active plugins
	 *
	 * @param array $plugins Active plugins.
	 * @return array Filtered active plugins.
	 */
	public function filter_active_plugins( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return $plugins;
		}

		// Step 1: Remove disabled plugins.
		$plugins = $this->remove_disabled_plugins( $plugins );

		// Step 2: Add required plugins.
		$plugins = $this->add_required_plugins( $plugins );

		return $plugins;
	}

	/**
	 * Filter network active plugins
	 *
	 * @param array $plugins Network active plugins.
	 * @return array Filtered network active plugins.
	 */
	public function filter_network_active_plugins( $plugins ) {
		if ( ! is_array( $plugins ) ) {
			return $plugins;
		}

		$disabled_plugins = $this->get_disabled_plugins();
		$required_plugins = $this->get_required_plugins();

		// Step 1: Remove disabled plugins.
		// Network plugins are stored as 'plugin/file.php' => timestamp.
		$filtered = array();
		foreach ( $plugins as $plugin => $timestamp ) {
			if ( ! in_array( $plugin, $disabled_plugins, true ) ) {
				$filtered[ $plugin ] = $timestamp;
			}
		}

		// Step 2: Add required plugins.
		$installed_plugins = $this->get_installed_plugins();
		foreach ( $required_plugins as $plugin ) {
			if ( isset( $installed_plugins[ $plugin ] ) && ! isset( $filtered[ $plugin ] ) ) {
				$filtered[ $plugin ] = time();
			}
		}

		return $filtered;
	}

	/**
	 * Restore the stored state of managed plugins before the active plugins list is saved.
	 *
	 * Disabled plugins that are really active stay active in the database; required
	 * plugins that were only added at runtime are not written to it.
	 *
	 * @param array $value New active plugins.
	 * @return array Active plugins to store.
	 */
	public function protect_active_plugins( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$stored   = self::get_stored_active_plugins();
		$required = $this->get_required_plugins();

		foreach ( array_intersect( $stored, $this->get_disabled_plugins() ) as $plugin ) {
			if ( ! in_array( $plugin, $value, true ) ) {
				$value[] = $plugin;
			}
		}

		$value = array_filter(
			$value,
			function ( $plugin ) use ( $stored, $required ) {
				return in_array( $plugin, $stored, true ) || ! in_array( $plugin, $required, true );
			}
		);

		return array_values( array_unique( $value ) );
	}

	/**
	 * Restore the stored state of managed plugins before the network active plugins list is saved.
	 *
	 * @param array $value New network active plugins ('plugin/file.php' => timestamp).
	 * @return array Network active plugins to store.
	 */
	public function protect_network_active_plugins( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$stored   = self::get_stored_network_active_plugins();
		$required = $this->get_required_plugins();

		foreach ( $this->get_disabled_plugins() as $plugin ) {
			if ( isset( $stored[ $plugin ] ) && ! isset( $value[ $plugin ] ) ) {
				$value[ $plugin ] = $stored[ $plugin ];
			}
		}

		foreach ( $required as $plugin ) {
			if ( ! isset( $stored[ $plugin ] ) ) {
				unset( $value[ $plugin ] );
			}
		}

		return $value;
	}

	/**
	 * Get the active plugins as stored in the database, bypassing the option cache and filters.
	 *
	 * @return array Active plugin paths.
	 */
	public static function get_stored_active_plugins() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Must bypass the (filtered) option cache.
		$value = maybe_unserialize( (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'active_plugins' ) ) );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Get the network active plugins as stored in the database, bypassing the cache and filters.
	 *
	 * @return array Network active plugins ('plugin/file.php' => timestamp).
	 */
	public static function get_stored_network_active_plugins() {
		global $wpdb;

		if ( ! is_multisite() ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Must bypass the (filtered) option cache.
		$value = maybe_unserialize( (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->sitemeta} WHERE meta_key = %s AND site_id = %d", 'active_sitewide_plugins', get_current_network_id() ) ) );

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Remove disabled plugins from active plugins list
	 *
	 * @param array $plugins Active plugins.
	 * @return array Filtered plugins.
	 */
	private function remove_disabled_plugins( $plugins ) {
		$disabled_plugins = $this->get_disabled_plugins();

		if ( empty( $disabled_plugins ) ) {
			return $plugins;
		}

		return array_values( array_diff( $plugins, $disabled_plugins ) );
	}

	/**
	 * Add required plugins to active plugins list
	 *
	 * @param array $plugins Active plugins.
	 * @return array Updated plugins list.
	 */
	private function add_required_plugins( $plugins ) {
		$required_plugins = $this->get_required_plugins();

		if ( empty( $required_plugins ) ) {
			return $plugins;
		}

		// Get list of installed plugins.
		$installed_plugins = $this->get_installed_plugins();

		// Add required plugins that are installed but not active.
		foreach ( $required_plugins as $plugin ) {
			if ( isset( $installed_plugins[ $plugin ] ) && ! in_array( $plugin, $plugins, true ) ) {
				$plugins[] = $plugin;
			}
		}

		return array_values( array_unique( $plugins ) );
	}

	/**
	 * Get list of installed plugins
	 *
	 * @return array Array of installed plugin paths.
	 */
	private function get_installed_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return get_plugins();
	}

	/**
	 * Get count of disabled plugins that are installed
	 *
	 * @return int Number of installed plugins that are in the disabled list.
	 */
	public function get_disabled_installed_count() {
		$disabled_plugins = $this->get_disabled_plugins();

		if ( empty( $disabled_plugins ) ) {
			return 0;
		}

		$installed_plugins = array_keys( $this->get_installed_plugins() );
		$installed_disabled = array_intersect( $disabled_plugins, $installed_plugins );

		return count( $installed_disabled );
	}

	/**
	 * Get count of required plugins that are installed
	 *
	 * @return int Number of installed plugins that are in the required list.
	 */
	public function get_required_installed_count() {
		$required_plugins = $this->get_required_plugins();

		if ( empty( $required_plugins ) ) {
			return 0;
		}

		$installed_plugins = array_keys( $this->get_installed_plugins() );
		$installed_required = array_intersect( $required_plugins, $installed_plugins );

		return count( $installed_required );
	}

	/**
	 * Get count of required plugins that are NOT installed
	 *
	 * @return int Number of required plugins that are missing.
	 */
	public function get_required_missing_count() {
		$required_plugins = $this->get_required_plugins();

		if ( empty( $required_plugins ) ) {
			return 0;
		}

		$installed_plugins = array_keys( $this->get_installed_plugins() );
		$missing_required = array_diff( $required_plugins, $installed_plugins );

		return count( $missing_required );
	}
}
