<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.scoredetect.com/
 * @since             1.5.0
 * @package           SDCOM_Timestamps
 *
 * @wordpress-plugin
 * Plugin Name:       Timestamps
 * Description:       Timestamp your WordPress content to empower your content authenticity and increase user trust. No blockchain skills needed.
 * Version:           1.5.0
 * Author:            ScoreDetect.com
 * Author URI:        https://www.scoredetect.com/
 * License:           AGPL-3.0-only
 * License URI:       https://spdx.org/licenses/AGPL-3.0-only.html
 * Text Domain:       timestamps
 * Domain Path:       /languages
 */

namespace SDCOM_Timestamps;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Useful global constants.
define( 'SDCOM_TIMESTAMPS_VERSION', '1.5.0' );
define( 'SDCOM_TIMESTAMPS_OPTIONS', 'sdcom_timestamps' );
define( 'SDCOM_TIMESTAMPS_URL', plugin_dir_url( __FILE__ ) );
define( 'SDCOM_TIMESTAMPS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SDCOM_TIMESTAMPS_INC', SDCOM_TIMESTAMPS_PATH . 'includes/' );
define( 'SDCOM_TIMESTAMPS_DIST_URL', SDCOM_TIMESTAMPS_URL . 'dist/' );
define( 'SDCOM_TIMESTAMPS_DIST_PATH', SDCOM_TIMESTAMPS_PATH . 'dist/' );

$is_local_env = in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
$is_local_url = strpos( home_url(), '.test' ) || strpos( home_url(), '.local' );
$is_local     = $is_local_env || $is_local_url;

if ( $is_local && file_exists( __DIR__ . '/dist/fast-refresh.php' ) ) {
	require_once __DIR__ . '/dist/fast-refresh.php';
	\TenUpToolkit\set_dist_url_path( basename( __DIR__ ), SDCOM_TIMESTAMPS_DIST_URL, SDCOM_TIMESTAMPS_DIST_PATH );
}

// Require Composer autoloader if it exists.
if ( file_exists( SDCOM_TIMESTAMPS_PATH . 'vendor/autoload.php' ) ) {
	require_once SDCOM_TIMESTAMPS_PATH . 'vendor/autoload.php';
}

/**
 * PSR-4-ish autoloading.
 *
 * @since 1.0.0
 */
spl_autoload_register(
	function ( $_class ) {
		// project-specific namespace prefix.
		$prefix = 'SDCOM_Timestamps\\';

		// base directory for the namespace prefix.
		$base_dir = __DIR__ . '/includes/classes/';

		// does the class use the namespace prefix?
		$len = strlen( $prefix );

		if ( strncmp( $prefix, $_class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $_class, $len );

		$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		// if the file exists, require it.
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

require_once SDCOM_TIMESTAMPS_INC . 'utils.php';

/**
 * Registers the features.
 *
 * @return void
 */
function register_features() {

	/**
	 * Handle features.
	 */
	Features::factory()->register_feature(
		new Feature\Timestamp\Timestamp()
	);
	Features::factory()->register_feature(
		new Feature\Timestamp\Screenshot()
	);
	Features::factory()->register_feature(
		new Feature\WooCommerce\Orders()
	);
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\register_features' );

/**
 * Setup blocks.
 */
require_once SDCOM_TIMESTAMPS_INC . 'blocks.php';
\SDCOM_Timestamps\Blocks\setup();

/**
 * Setup screen.
 */
Screen::factory();

/**
 * Load text domain.
 *
 * @since 1.0.0
 */
function setup_misc() {
	load_plugin_textdomain( 'timestamps', false, basename( __DIR__ ) . '/languages' ); // Load any available translations first.
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\setup_misc' );

/**
 * Fires after the plugin is loaded.
 *
 * @since 1.0.0
 * @hook timestamps_loaded
 */
do_action( 'timestamps_loaded' );
