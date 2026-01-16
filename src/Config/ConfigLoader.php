<?php
/**
 * Configuration Loader
 *
 * @package Perdives\ManagePlugins
 */

namespace Perdives\ManagePlugins\Config;

/**
 * Loads and validates plugin management configuration
 */
class ConfigLoader {

	/**
	 * Path to configuration file
	 *
	 * @var string
	 */
	private $config_file;

	/**
	 * Current environment
	 *
	 * @var string
	 */
	private $environment;

	/**
	 * Parsed configuration data
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Constructor
	 *
	 * @param string $config_file Path to config file.
	 * @param string $environment Current environment.
	 */
	public function __construct( $config_file, $environment ) {
		$this->config_file = $config_file;
		$this->environment = $environment;
		$this->load_config();
	}

	/**
	 * Load configuration from JSON file
	 *
	 * @return void
	 */
	private function load_config() {
		// Config file is optional - if it doesn't exist, use empty config.
		if ( ! file_exists( $this->config_file ) ) {
			$this->config = $this->get_empty_config();
			return;
		}

		// Try to read the config file.
		$json_content = @file_get_contents( $this->config_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Intentional error suppression for file read.
		if ( false === $json_content ) {
			$this->handle_config_error( 'Failed to read config file: ' . $this->config_file );
			return;
		}

		$config = json_decode( $json_content, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$this->handle_config_error( 'Failed to parse config file: ' . json_last_error_msg() );
			return;
		}

		$this->config = $this->validate_config( $config );
	}

	/**
	 * Handle configuration errors
	 *
	 * @param string $error_message Error message.
	 * @return void
	 */
	private function handle_config_error( $error_message ) {
		$log_message = 'Manage Plugins: ' . $error_message;
		error_log( $log_message );

		// Only die if we're in a non-production environment.
		if ( 'production' !== $this->environment ) {
			wp_die(
				'<h1>Plugin Configuration Error</h1>' .
				'<p>' . esc_html( $error_message ) . '</p>' .
				'<p>File: <code>' . esc_html( $this->config_file ) . '</code></p>' .
				'<p>Check file permissions and JSON syntax.</p>',
				'Plugin Configuration Error',
				array( 'response' => 500 )
			);
		}

		$this->config = $this->get_empty_config();
	}

	/**
	 * Validate and normalize configuration structure
	 *
	 * @param array $config Raw configuration.
	 * @return array Validated configuration.
	 */
	private function validate_config( $config ) {
		if ( ! is_array( $config ) ) {
			return $this->get_empty_config();
		}

		// Ensure environments key exists and is an array.
		if ( ! isset( $config['environments'] ) || ! is_array( $config['environments'] ) ) {
			$config['environments'] = array();
		}

		// Ensure global key exists and is an array.
		if ( ! isset( $config['global'] ) || ! is_array( $config['global'] ) ) {
			$config['global'] = array();
		}

		// Normalize each environment's structure.
		foreach ( $config['environments'] as $env => $rules ) {
			$config['environments'][ $env ] = $this->normalize_rules( $rules );
		}

		// Normalize global rules.
		$config['global'] = $this->normalize_rules( $config['global'] );

		return $config;
	}

	/**
	 * Normalize rules structure
	 *
	 * @param mixed $rules Rules to normalize.
	 * @return array Normalized rules with 'disabled' and 'required' keys.
	 */
	private function normalize_rules( $rules ) {
		if ( ! is_array( $rules ) ) {
			return array(
				'disabled' => array(),
				'required' => array(),
			);
		}

		// Ensure both disabled and required keys exist.
		if ( ! isset( $rules['disabled'] ) || ! is_array( $rules['disabled'] ) ) {
			$rules['disabled'] = array();
		}
		if ( ! isset( $rules['required'] ) || ! is_array( $rules['required'] ) ) {
			$rules['required'] = array();
		}

		return array(
			'disabled' => array_values( array_filter( $rules['disabled'], 'is_string' ) ),
			'required' => array_values( array_filter( $rules['required'], 'is_string' ) ),
		);
	}

	/**
	 * Get empty configuration structure
	 *
	 * @return array
	 */
	private function get_empty_config() {
		return array(
			'environments' => array(),
			'global'       => array(
				'disabled' => array(),
				'required' => array(),
			),
		);
	}

	/**
	 * Get disabled plugins for current environment
	 *
	 * @return array Array of plugin paths to disable.
	 */
	public function get_disabled_plugins() {
		$disabled = array();

		// Add global disabled plugins.
		if ( isset( $this->config['global']['disabled'] ) ) {
			$disabled = array_merge( $disabled, $this->config['global']['disabled'] );
		}

		// Add environment-specific disabled plugins.
		if ( isset( $this->config['environments'][ $this->environment ]['disabled'] ) ) {
			$disabled = array_merge(
				$disabled,
				$this->config['environments'][ $this->environment ]['disabled']
			);
		}

		return array_unique( $disabled );
	}

	/**
	 * Get required plugins for current environment
	 *
	 * @return array Array of plugin paths to require.
	 */
	public function get_required_plugins() {
		$required = array();

		// Add global required plugins.
		if ( isset( $this->config['global']['required'] ) ) {
			$required = array_merge( $required, $this->config['global']['required'] );
		}

		// Add environment-specific required plugins.
		if ( isset( $this->config['environments'][ $this->environment ]['required'] ) ) {
			$required = array_merge(
				$required,
				$this->config['environments'][ $this->environment ]['required']
			);
		}

		return array_unique( $required );
	}

	/**
	 * Get full configuration
	 *
	 * @return array
	 */
	public function get_config() {
		return $this->config;
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
