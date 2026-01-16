<?php
/**
 * Environment Detection
 *
 * @package Perdives\ManagePlugins
 */

namespace Perdives\ManagePlugins;

/**
 * Detects the current WordPress environment
 */
class Environment {

	/**
	 * Detect current WordPress environment
	 *
	 * Checks multiple sources in order of priority:
	 * 1. WP_ENV environment variable
	 * 2. WP_ENVIRONMENT_TYPE constant in wp-config
	 * 3. Deployer's .environment file
	 * 4. Defaults to 'production'
	 *
	 * @return string The detected environment (e.g., 'production', 'staging', 'development').
	 */
	public static function detect() {
		// Method 1: Check for WP_ENV environment variable (allows override).
		$env = getenv( 'WP_ENV' );
		if ( $env !== false && ! empty( $env ) ) {
			return $env;
		}

		// Method 2: Check for WP_ENVIRONMENT_TYPE constant in wp-config.
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
			$constant_value = constant( 'WP_ENVIRONMENT_TYPE' );
			if ( ! empty( $constant_value ) ) {
				return $constant_value;
			}
		}

		// Method 3: Read from deployer's environment file.
		if ( isset( $_SERVER['HOME'] ) ) {
			$env_file = $_SERVER['HOME'] . '/deploy/shared/.environment';
			if ( file_exists( $env_file ) ) {
				$env_content = trim( file_get_contents( $env_file ) );
				if ( ! empty( $env_content ) ) {
					return $env_content;
				}
			}
		}

		// Default to production.
		return 'production';
	}
}
