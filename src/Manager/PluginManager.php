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
	}

	/**
	 * Get list of disabled plugins
	 *
	 * @return array
	 */
	private function get_disabled_plugins() {
		if ( null === $this->disabled_plugins ) {
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
		if ( null === $this->required_plugins ) {
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
