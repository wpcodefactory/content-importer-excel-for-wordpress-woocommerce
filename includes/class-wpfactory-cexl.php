<?php
/**
 * Import Content in WordPress & WooCommerce with Excel - Main Class
 *
 * @version 5.0.0
 * @since   5.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WPFactory_CEXL' ) ) :

final class WPFactory_CEXL {

	/**
	 * Plugin version.
	 *
	 * @var   string
	 * @since 5.0.0
	 */
	public $version = WPFACTORY_CEXL_VERSION;

	/**
	 * @var   WPFactory_CEXL The single instance of the class
	 * @since 5.0.0
	 */
	protected static $_instance = null;

	/**
	 * Main WPFactory_CEXL Instance.
	 *
	 * Ensures only one instance of WPFactory_CEXL is loaded or can be loaded.
	 *
	 * @version 5.0.0
	 * @since   5.0.0
	 *
	 * @static
	 * @return  WPFactory_CEXL - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * WPFactory_CEXL Constructor.
	 *
	 * @version 5.0.0
	 * @since   5.0.0
	 *
	 * @access  public
	 */
	function __construct() {

		// Admin
		if ( is_admin() ) {
			$this->admin();
		}

	}

	/**
	 * admin.
	 *
	 * @version 5.0.0
	 * @since   5.0.0
	 */
	function admin() {
		return true;
	}

	/**
	 * plugin_url.
	 *
	 * @version 5.0.0
	 * @since   5.0.0
	 *
	 * @return  string
	 */
	function plugin_url() {
		return untrailingslashit( plugin_dir_url( WPFACTORY_CEXL_FILE ) );
	}

	/**
	 * plugin_path.
	 *
	 * @version 5.0.0
	 * @since   5.0.0
	 *
	 * @return  string
	 */
	function plugin_path() {
		return untrailingslashit( plugin_dir_path( WPFACTORY_CEXL_FILE ) );
	}

}

endif;
