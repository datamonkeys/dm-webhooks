<?php
/**
 * Plugin Name:       DM Webhooks for WooCommerce
 * Plugin URI:        https://github.com/louisperner/dm-webhooks
 * Description:       Sends signed WooCommerce order events to any webhook (Zapier, Make, CRMs, ERPs) with automatic retries, delivery log and a REST test endpoint.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Louis Perner (Datamonkeys)
 * Author URI:        https://datamonkeys.ai
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dm-webhooks
 *
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'DMWH_VERSION', '1.0.0' );
define( 'DMWH_FILE', __FILE__ );
define( 'DMWH_DIR', plugin_dir_path( __FILE__ ) );

require_once DMWH_DIR . 'includes/class-dmwh-settings.php';
require_once DMWH_DIR . 'includes/class-dmwh-dispatcher.php';
require_once DMWH_DIR . 'includes/class-dmwh-log.php';
require_once DMWH_DIR . 'includes/class-dmwh-events.php';
require_once DMWH_DIR . 'includes/class-dmwh-rest.php';

register_activation_hook( __FILE__, array( 'DMWH_Log', 'install' ) );
register_uninstall_hook( __FILE__, 'dmwh_uninstall' );

/**
 * Removes plugin data on uninstall.
 */
function dmwh_uninstall() {
	global $wpdb;
	delete_option( DMWH_Settings::OPTION );
	$wpdb->query( 'DROP TABLE IF EXISTS ' . DMWH_Log::table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	wp_clear_scheduled_hook( DMWH_Dispatcher::RETRY_HOOK );
}

// Declare compatibility with WooCommerce High-Performance Order Storage.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DMWH_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		DMWH_Settings::init();
		DMWH_Dispatcher::init();
		DMWH_Rest::init();
		if ( class_exists( 'WooCommerce' ) ) {
			DMWH_Events::init();
		}
	}
);
