<?php
/**
 * Main Plugin Class
 *
 * @package Perdives\ManagePlugins
 */

namespace Perdives\ManagePlugins;

use Perdives\ManagePlugins\Config\ConfigLoader;
use Perdives\ManagePlugins\Manager\PluginManager;

/**
 * Main plugin class that orchestrates all components
 */
class Plugin {

	/**
	 * Plugin instance
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Configuration loader instance
	 *
	 * @var ConfigLoader
	 */
	private $config_loader;

	/**
	 * Plugin manager instance
	 *
	 * @var PluginManager
	 */
	private $plugin_manager;

	/**
	 * Current environment
	 *
	 * @var string
	 */
	private $environment;

	/**
	 * Get plugin instance
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->initialize();
	}

	/**
	 * Initialize the plugin
	 *
	 * @return void
	 */
	private function initialize() {
		// Detect current environment.
		$this->environment = Environment::detect();

		// Set config file path (in project root config directory).
		$config_file = dirname( ABSPATH ) . '/config/managed-plugins.json';

		// Initialize configuration loader.
		$this->config_loader = new ConfigLoader( $config_file, $this->environment );

		// Initialize plugin manager.
		$this->plugin_manager = new PluginManager( $this->config_loader );

		// Register hooks.
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Register plugin manager hooks.
		$this->plugin_manager->register_hooks();
	}

	/**
	 * Get configuration loader instance
	 *
	 * @return ConfigLoader
	 */
	public function get_config_loader() {
		return $this->config_loader;
	}

	/**
	 * Get plugin manager instance
	 *
	 * @return PluginManager
	 */
	public function get_plugin_manager() {
		return $this->plugin_manager;
	}

	/**
	 * Get current environment
	 *
	 * @return string
	 */
	public function get_environment() {
		return $this->environment;
	}
}
