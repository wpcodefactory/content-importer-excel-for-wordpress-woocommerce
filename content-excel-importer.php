<?php
/*
 * Plugin Name: Import Content in WordPress & WooCommerce with Excel
 * Plugin URI: https://wpfactory.com/item/content-importer-for-wordpress-woocommerce-with-excel/
 * Description: Import Posts, Pages, Simple Products for WooCommerce & WordPress with Excel. Migrate Easily. No more CSV Hassle.
 * Version: 5.0.4
 * Author: WPFactory
 * Author URI: https://wpfactory.com
 * WC requires at least: 2.2
 * WC tested up to: 10.7
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Created On: 04-06-2018
 * Updated On: 24-05-2026
 * Text Domain: content-excel-importer
 * Domain Path: /langs
 */

defined( 'ABSPATH' ) || exit;

defined( 'WPFACTORY_CEXL_VERSION' ) || define( 'WPFACTORY_CEXL_VERSION', '5.0.4' );

defined( 'WPFACTORY_CEXL_FILE' ) || define( 'WPFACTORY_CEXL_FILE', __FILE__ );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpfactory-cexl.php';

if ( ! function_exists( 'wpfactory_cexl' ) ) {
	/**
	 * Returns the main instance of WPFactory_CEXL to prevent the need to use globals.
	 *
	 * @version 5.0.0
	 * @since   5.0.0
	 */
	function wpfactory_cexl() {
		return WPFactory_CEXL::instance();
	}
}

add_action( 'plugins_loaded', 'wpfactory_cexl' );

/**
 * require.
 */
require plugin_dir_path( __FILE__ ) . '/class-contentexcelimporterquery.php';
require plugin_dir_path( __FILE__ ) . '/class-contentexcelimporterproducts.php';
require plugin_dir_path( __FILE__ ) . '/class-contentexcelimporterpreview.php';
require plugin_dir_path( __FILE__ ) . '/class-contentexcelimportermappings.php';
require plugin_dir_path( __FILE__ ) . '/class-contentexcelimporterreport.php';

/**
 * load_contentExceIimporter_js.
 *
 * This function enqueues css and js files needed.
 *
 * @version 5.0.0
 */
function load_contentExceIimporter_js() {

	$screen = get_current_screen();

	if ( 'wpfactory_page_content-excel-importer' !== $screen->base ) {
		return;
	}

	wp_enqueue_script( 'jquery' );
	wp_enqueue_script( 'jquery-ui-core' );
	wp_enqueue_script( 'jquery-ui-accordion' );
	wp_enqueue_script( 'jquery-ui-tabs' );
	wp_enqueue_script( 'jquery-ui-draggable' );
	wp_enqueue_script( 'jquery-ui-droppable' );

	// ENQUEUED CSS FILE INSTEAD OF INLINE CSS.
	wp_enqueue_style( 'contentExceIimporter_css', plugins_url( '/css/contentExceIimporter.css', __FILE__ ) );
	wp_enqueue_style( 'contentExceIimporter_css' );

	wp_enqueue_script( 'contentExceIimporter_js', plugins_url( '/js/contentExceIimporter.js?v=555', __FILE__ ), array( 'jquery', 'jquery-ui-core', 'jquery-ui-tabs', 'jquery-ui-draggable', 'jquery-ui-droppable' ), null, true );
	wp_enqueue_script( 'contentExceIimporter_js' );

	$cei = array(
		'RestRoot'   => esc_url_raw( rest_url() ),
		'plugin_url' => esc_url( plugins_url( '', __FILE__ ) ),
		'siteUrl'    => esc_url( site_url() ),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'ajax_url'   => esc_url( admin_url( 'admin-ajax.php' ) ),
	);

	wp_localize_script( 'contentExceIimporter_js', 'contentExcelImporter', $cei );
}
add_action( 'admin_enqueue_scripts', 'load_contentExceIimporter_js' );

/**
 * contentExceIimporter_main.
 *
 * This function is main wrapper for plugin display page.
 */
function contentExceIimporter_main() {
	?>

		<div class = 'left_wrap' >

			<div class='freeContent'>
			<?php
				$products = new ContentExcelImporterProducts();
				$products->importProductsDisplay();
			?>
			</div>
		</div>

		<p class='cexl-pro-note'>
			<?php esc_html_e( 'Need variable products, images by URL, custom fields, WPML or deleting by sheet?', 'content-excel-importer' ); ?>
			<a target='_blank' href='<?php echo esc_url( 'https://wpfactory.com/item/content-importer-for-wordpress-woocommerce-with-excel/' ); ?>'><?php esc_html_e( 'See the Pro version', 'content-excel-importer' ); ?></a>
		</p>
	<?php
}

/**
 * contentExceIimporter_init.
 *
 * This function contains plugin content.
 */
function contentExceIimporter_init() {
	contentExceIimporter_form_header();

	?>
	<div class = "content-excel-importer" >
<div class='msg'></div>

	<h2 class = "nav-tab-wrapper" >
		<a class = 'nav-tab nav-tab-active' href="<?php print esc_url( admin_url( '?page=content-excel-importer' ) ); ?>" ><?php esc_html_e( 'Import Content', 'content-excel-importer' ); ?></a>
	</h2>

	<?php
		contentExceIimporter_main();
	?>

	</div>

	<?php
	contentExceIimporter_form_footer();
}

/**
 * contentExceIimporter_form_header.
 *
 * This function is plugin header.
 */
function contentExceIimporter_form_header() {
	?>
	<h1><?php esc_html_e( 'Import Content in WordPress & WooCommerce with Excel', 'content-excel-importer' ); ?></h1>

	<?php
}

/**
 * contentExceIimporter_form_footer.
 *
 * This function is plugin footer.
 */
function contentExceIimporter_form_footer() {
	?>
	<hr>
		<div></div>

	<?php
}

