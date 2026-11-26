<?php
/*
 * Plugin Name: Excel Importer for WooCommerce: Products, Posts & Pages with Preview
 * Plugin URI: https://wpfactory.com/item/content-importer-for-wordpress-woocommerce-with-excel/
 * Description: Update products, posts and pages from an Excel sheet: match by SKU or ID, preview every change before it is saved.
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

// Libraries. Loaded on every request: the WPFactory AI library answers its site check on the REST API.
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

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
require plugin_dir_path( __FILE__ ) . '/class-contentexcelimporterapp.php';
require plugin_dir_path( __FILE__ ) . '/class-contentexcelimporterai.php';
require plugin_dir_path( __FILE__ ) . '/class-contentexcelimporterbackground.php';

register_activation_hook( __FILE__, array( 'ContentExcelImporterApp', 'activate' ) );

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
	// The new screen; the old upload form posted to the page still gets the old matching screen.
	if ( ! ContentExcelImporterApp::legacy_request() ) {
		ContentExcelImporterApp::render();
		return;
	}
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
	<h1><?php esc_html_e( 'Excel Importer', 'content-excel-importer' ); ?></h1>

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

