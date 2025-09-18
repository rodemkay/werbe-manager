<?php
/**
 * Plugin Name:       Werbe Manager
 * Description:       Dynamisches Kampagnen- und Slot-Management für Werbeeinheiten.
 * Version:           1.0.0
 * Author:            ForexSignale Magazine
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Text Domain:       werbe-manager
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WERBE_MANAGER_VERSION = '1.0.0';
const WERBE_MANAGER_FILE    = __FILE__;
const WERBE_MANAGER_PATH    = __DIR__;
const WERBE_MANAGER_SLUG    = 'werbe-manager';

require_once WERBE_MANAGER_PATH . '/includes/plugin.php';

\Werbe_Manager\Plugin::instance()->register();

register_activation_hook( WERBE_MANAGER_FILE, array( '\\Werbe_Manager\\Plugin', 'activate' ) );
register_deactivation_hook( WERBE_MANAGER_FILE, array( '\\Werbe_Manager\\Plugin', 'deactivate' ) );
