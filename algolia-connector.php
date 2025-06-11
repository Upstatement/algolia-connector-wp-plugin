<?php
/**
 * Plugin Name: Algolia Connector
 * Description: Index content with Algolia.
 * Text Domain: algolia-connector
 * Version: 0.1.0
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * Author: Upstatement
 * Author URI: https://upstatement.com
 *
 * @package AlgoliaConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

use Upstatement\AlgoliaConnector\AlgoliaConnector;
use Upstatement\AlgoliaConnector\Console\AlgoliaCli;

define( 'ALGOLIA_CONNECTOR_VERSION', '0.1.0' );
define( 'ALGOLIA_CONNECTOR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ALGOLIA_CONNECTOR_PLUGIN_PATH', __DIR__ );
define( 'ALGOLIA_CONNECTOR_PLUGIN_URL', plugins_url( '/', __FILE__ ) );

add_action(
	'init',
	function () {
		$algolia_connector = AlgoliaConnector::get_instance();
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'algolia', new AlgoliaCli( $algolia_connector ) );
		}
	}
);
