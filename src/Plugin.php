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
	 * Whether the plugin was loaded as a must-use plugin (rules are only applied then)
	 *
	 * @var bool
	 */
	private $is_mu = false;

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
		// Must-use plugins are included before `muplugins_loaded` fires; regular plugins after.
		$this->is_mu = ! did_action( 'muplugins_loaded' );

		// The generated loader is still present but the plugin was deactivated
		// (e.g. the deactivation hook could not delete it): stay inert and clean up.
		// A manually copied loader (Composer installs) runs the plugin without activation.
		if ( $this->is_mu && defined( 'PERDIVES_MANAGE_PLUGINS_MANAGED_LOADER' ) && ! self::is_activated() && ! MuLoader::has_manual_loader() ) {
			MuLoader::remove();
			return;
		}

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
		// Filtering the plugin list only works before regular plugins load.
		if ( $this->is_mu ) {
			$this->plugin_manager->register_hooks();
		}

		if ( is_admin() ) {
			add_action( 'admin_init', array( MuLoader::class, 'maybe_repair' ) );
			add_action( 'admin_notices', array( $this, 'mu_loader_notice' ) );
			add_action( 'network_admin_notices', array( $this, 'mu_loader_notice' ) );
		}
	}

	/**
	 * Warn when the plugin is active but not running as a must-use plugin.
	 *
	 * @return void
	 */
	public function mu_loader_notice() {
		if ( $this->is_mu || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Manage Plugins is not applying any rules.', 'manage-plugins' ); ?></strong>
				<?php
				printf(
					/* translators: %s: loader file path */
					esc_html__( 'It needs to run as a must-use plugin, but %s could not be created. Create it with the following contents:', 'manage-plugins' ),
					'<code>' . esc_html( str_replace( ABSPATH, '', MuLoader::path() ) ) . '</code>'
				);
				?>
			</p>
			<pre style="overflow:auto"><?php echo esc_html( MuLoader::contents() ); ?></pre>
		</div>
		<?php
	}

	/**
	 * Whether the plugin is activated in the stored plugin list, site- or network-wide
	 *
	 * @return bool
	 */
	public static function is_activated() {
		$basename = plugin_basename( PERDIVES_MANAGE_PLUGINS_FILE );

		if ( in_array( $basename, PluginManager::get_stored_active_plugins(), true ) ) {
			return true;
		}

		return isset( PluginManager::get_stored_network_active_plugins()[ $basename ] );
	}

	/**
	 * Whether the plugin is running as a must-use plugin
	 *
	 * @return bool
	 */
	public function is_mu() {
		return $this->is_mu;
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
