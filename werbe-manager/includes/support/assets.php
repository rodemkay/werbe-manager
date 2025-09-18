<?php
namespace Werbe_Manager\Support;

/**
 * Handles loading of assets and translations.
 */
class Assets {

	public function load_textdomain(): void {
		load_plugin_textdomain( 'werbe-manager', false, dirname( plugin_basename( WERBE_MANAGER_FILE ) ) . '/languages/' );
	}

	public function register_shortcodes_styles(): void {
		wp_register_style(
			'werbe-manager-unit',
			plugin_dir_url( WERBE_MANAGER_FILE ) . 'assets/css/unit.css',
			array(),
			WERBE_MANAGER_VERSION
		);
	}

	public function enqueue_admin( string $hook ): void {
		if ( str_contains( $hook, 'werbe_manager' ) ) {
			wp_enqueue_style( 'werbe-manager-unit' );
		}
	}
}
