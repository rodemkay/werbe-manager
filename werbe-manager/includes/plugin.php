<?php
namespace Werbe_Manager;

use Werbe_Manager\Admin\Admin;
use Werbe_Manager\DB\Migrations;
use Werbe_Manager\Frontend\Resolver;
use Werbe_Manager\Frontend\Shortcode;
use Werbe_Manager\Support\Context;
use Werbe_Manager\Support\Assets;

/**
 * Core bootstrap for the Werbe Manager plugin.
 */
class Plugin {

	private static ?Plugin $instance = null;

	private Admin $admin;

	private Shortcode $shortcode;

	private Assets $assets;

	/**
	 * Singleton accessor.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hook registration.
	 */
	public function register(): void {
		$this->register_autoloader();

		$this->assets    = new Assets();
		$this->admin     = new Admin();
		$context         = new Context();
		$resolver        = new Resolver( $context );
		$this->shortcode = new Shortcode( $resolver, $context );

		add_action( 'plugins_loaded', array( $this->assets, 'load_textdomain' ) );
		add_action( 'init', array( $this->shortcode, 'register' ) );
		add_action( 'init', array( $this->assets, 'register_shortcodes_styles' ) );
		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
		add_action( 'admin_init', array( $this->admin, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this->assets, 'enqueue_admin' ) );
	}

	/**
	 * Create tables and default data.
	 */
	public static function activate(): void {
		self::instance()->register_autoloader();
		( new Migrations() )->up();
	}

	/**
	 * Plugin deactivation handler.
	 */
	public static function deactivate(): void {
		// Reserved for future cleanup (option flags etc.).
	}

	/**
	 * PSR-4 Autoloader registration for plugin namespace.
	 */
	private function register_autoloader(): void {
		spl_autoload_register(
			static function ( string $class ): void {
				$prefix = __NAMESPACE__ . '\\';

				if ( ! str_starts_with( $class, $prefix ) ) {
					return;
				}

				$relative = substr( $class, strlen( $prefix ) );
				$relative = str_replace( '\\', '/', strtolower( $relative ) );
				$file     = WERBE_MANAGER_PATH . '/includes/' . $relative . '.php';

				if ( file_exists( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}
