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

		// Set up localisation
		add_action( 'init', array( $this, 'localize' ) );

		// Declare compatibility with custom order tables for WooCommerce
		if ( function_exists( 'WC' ) ) {
			add_action( 'before_woocommerce_init', array( $this, 'wc_declare_compatibility' ) );
		}

		// Admin
		if ( is_admin() ) {
			$this->admin();
		}

	}

	/**
	 * localize.
	 *
	 * @version 5.0.0
	 * @since   5.0.0
	 */
	function localize() {
		load_plugin_textdomain(
			'content-excel-importer',
			false,
			dirname( plugin_basename( WPFACTORY_CEXL_FILE ) ) . '/langs/'
		);

	}

	/**
	 * wc_declare_compatibility.
	 *
	 * @version 5.0.0
	 * @since   5.0.0
	 *
	 * @see     https://developer.woocommerce.com/docs/hpos-extension-recipe-book/
	 */
	function wc_declare_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				WPFACTORY_CEXL_FILE,
				true
			);
		}
	}

	/**
	 * admin.
	 *
	 * @version 5.0.0
	 * @since   5.0.0
	 */
	function admin() {

		// Load libs
		require_once plugin_dir_path( WPFACTORY_CEXL_FILE ) . 'vendor/autoload.php';

		// Action links
		add_filter(
			'plugin_action_links_' . plugin_basename( WPFACTORY_CEXL_FILE ),
			array( $this, 'action_links' )
		);

		// "Recommendations" page
		add_action( 'init', array( $this, 'add_cross_selling_library' ) );

		// Settings
		add_action( 'admin_menu', array( $this, 'add_settings' ), 11 );

	}

	/**
	 * action_links.
	 *
	 * @version 5.0.0
	 * @since   5.0.0
	 *
	 * @param   mixed $links
	 * @return  array
	 */
	function action_links( $links ) {
		$custom_links = array();

		$custom_links[] = '<a href="' . admin_url( 'admin.php?page=content-excel-importer' ) . '">' .
			__( 'Settings', 'content-excel-importer' ) .
		'</a>';

		$pro_url = 'https://extend-wp.com/product/content-importer-wordpress-woocommerce-excel/';
		$custom_links[] = '<a target="_blank" style="font-weight: bold; color: green;" href="' . $pro_url . '">' .
			__( 'Go Pro', 'content-excel-importer' ) .
		'</a>';

		return array_merge( $custom_links, $links );
	}

	/**
	 * add_cross_selling_library.
	 *
	 * @version 5.0.0
	 * @since   5.0.0
	 */
	function add_cross_selling_library() {

		if ( ! class_exists( '\WPFactory\WPFactory_Cross_Selling\WPFactory_Cross_Selling' ) ) {
			return;
		}

		$cross_selling = new \WPFactory\WPFactory_Cross_Selling\WPFactory_Cross_Selling();
		$cross_selling->setup( array( 'plugin_file_path' => WPFACTORY_CEXL_FILE ) );
		$cross_selling->init();

	}

	/**
	 * add_settings.
	 *
	 * @version 5.0.0
	 * @since   5.0.0
	 */
	function add_settings() {

		if ( ! class_exists( 'WPFactory\WPFactory_Admin_Menu\WPFactory_Admin_Menu' ) ) {
			return;
		}

		$admin_menu = WPFactory\WPFactory_Admin_Menu\WPFactory_Admin_Menu::get_instance();

		add_submenu_page(
			$admin_menu->get_menu_slug(),
			__( 'Content Excel Importer Settings', 'content-excel-importer' ),
			__( 'Content Excel Importer', 'content-excel-importer' ),
			'manage_options',
			'content-excel-importer',
			'contentExceIimporter_init',
			30
		);

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
