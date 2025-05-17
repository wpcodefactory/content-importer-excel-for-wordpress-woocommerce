<?php
/*
 * Plugin Name: Import Content in WordPress & WooCommerce with Excel
 * Plugin URI: https://extend-wp.com/content-excel-importer-for-wordpress/
 * Description: Import Posts, Pages, Simple Products for WooCommerce & WordPress with Excel. Migrate Easily. No more CSV Hassle
 * Version: 5.0.0-dev
 * Author: extendWP
 * Author URI: https://extend-wp.com
 *
 * WC requires at least: 2.2
 * WC tested up to: 9.5
 *
 * License: GPL2s
 * Created On: 04-06-2018
 * Updated On: 20-11-2024
 * Text Domain: content-excel-importer
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require plugin_dir_path( __FILE__ ) . '/class-contentexcelimporterquery.php';
require plugin_dir_path( __FILE__ ) . '/class-contentexcelimporterproducts.php';

function contentExceIimporter_translate() {
	/** This function is responsible for translations. */

	load_plugin_textdomain( 'content-excel-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'contentExceIimporter_translate' );

function load_contentExceIimporter_js() {
	/** This function enqueues css and js files needed. */

	$screen = get_current_screen();

	if ( 'toplevel_page_content-excel-importer' !== $screen->base ) {
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


// ADD MENU LINK AND PAGE FOR WOOCOMMERCE IMPORTER.
add_action( 'admin_menu', 'contentExceIimporter_menu' );

function contentExceIimporter_menu() {
	/** This function adds menu page. */

	add_menu_page( 'Content Excel Importer Settings', esc_html__( 'Content Excel Importer', 'content-excel-importer' ), 'manage_options', 'content-excel-importer', 'contentExceIimporter_init', 'dashicons-upload', '50' );
}


add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'add_contentExceIimporter_links' );

function add_contentExceIimporter_links( $links ) {
	/** This function adds links to plugins page for this plugin. */

	$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=content-excel-importer' ) ) . '">' . esc_html__( 'Settings', 'content-excel-importer' ) . '</a>';
	$links[] = '<a href="https://extend-wp.com/product/content-importer-wordpress-woocommerce-excel/" target="_blank">' . esc_html__( 'PRO Version', 'content-excel-importer' ) . '</a>';
	$links[] = '<a href="https://extend-wp.com" target="_blank">' . esc_html__( 'More plugins', 'content-excel-importer' ) . '</a>';
	return $links;
}

function contentExceIimporter_main() {
	/** This function is main wrapper for plugin display page. */

	?>


		<div class = 'left_wrap' >

			<div class = 'premium_msg'>
				<p>
					<strong>
					<?php esc_html_e( 'Only available on PRO Version', 'content-excel-importer' ); ?>  <a class='premium_button' target='_blank'  href='https://extend-wp.com/product/content-importer-wordpress-woocommerce-excel/'><?php esc_html_e( 'Get it Here', 'content-excel-importer' ); ?></a>
					</strong>
				</p>
			</div>
			<div class='freeContent'>
			<?php
				$products = new ContentExcelImporterProducts();
				$products->importProductsDisplay();
			?>
			</div>
		</div>

		<div class='right_wrap rightToLeft'>
			<h2  class='center'><?php esc_html_e( 'NEED MORE FEATURES?', 'content-excel-importer' ); ?> </h2>
				<ul>
					<li> - <?php esc_html_e( 'Import Any Custom Post Type', 'content-excel-importer' ); ?></li>
					<li> - <?php esc_html_e( 'Import post type WPML translation', 'content-excel-importer' ); ?></li>
					<li> - <?php esc_html_e( 'Import ACF and other Custom fields ( including Images and Galleries )', 'content-excel-importer' ); ?></li>
					<li> - <?php esc_html_e( 'Import Any Category / Custom Taxonomy with Excel', 'content-excel-importer' ); ?> </li>
					<li> - <?php esc_html_e( 'Delete Category / Custom Taxonomy with Excel', 'content-excel-importer' ); ?> </li>
					<li> - <?php esc_html_e( 'Import Featured Image along with Post', 'content-excel-importer' ); ?></li>
					<li> - <?php esc_html_e( 'Import Variable Woocommerce Products', 'content-excel-importer' ); ?></li>
					<li> - <?php esc_html_e( 'Import Product Featured Image from URL', 'content-excel-importer' ); ?></li>
					<li> - <?php esc_html_e( 'Import Product Gallery Images from URL!', 'content-excel-importer' ); ?></li>
					<li> - <?php esc_html_e( 'Import YOAST SEO Meta Title & Description', 'content-excel-importer' ); ?></li>
					<li> - <?php esc_html_e( 'Define Downloadable, name, URL for file, expiry date & limit!', 'content-excel-importer' ); ?></li>
					<li> - <?php esc_html_e( 'Import Category Term Description with HTML Support', 'content-excel-importer' ); ?></li>
				</ul>
			<p class='center'>
				<a target='_blank'  href="<?php echo esc_url( 'https://extend-wp.com/product/content-importer-wordpress-woocommerce-excel' ); ?>">
					<img class='premium_img' src='<?php echo esc_url( plugins_url( 'images/content-excel-importer-pro.png', __FILE__ ) ); ?>' alt='<?php esc_html_e( 'Content Excel Importer PRO', 'content-excel-importer' ); ?>' title='<?php esc_html_e( 'Content Excel Importer PRO', 'content-excel-importer' ); ?>' />
				</a>
			<p  class='center'>'
				<a class='premium_button' target='_blank'  href='<?php echo esc_url( 'https://extend-wp.com/product/content-importer-wordpress-woocommerce-excel' ); ?> '>
					<?php esc_html_e( 'Get it here', 'content-excel-importer' ); ?>
				</a>
			</p>
		</div>
	<?php
}


function contentExceIimporter_init() {
	/** This function contains plugin content. */

	contentExceIimporter_form_header();
	?>
	<div class = "content-excel-importer" >
<div class='msg'></div>

	<h2 class = "nav-tab-wrapper" >
		<a class = 'nav-tab nav-tab-active' href="<?php print esc_url( admin_url( '?page=content-excel-importer' ) ); ?>" ><?php esc_html_e( 'Import Content', 'content-excel-importer' ); ?></a>
		<a class = 'nav-tab premium' href='#'><?php esc_html_e( 'Delete Content', 'content-excel-importer' ); ?></a>
		<a class = 'nav-tab premium' href='#'><?php esc_html_e( 'Import Categories', 'content-excel-importer' ); ?></a>
		<a class = 'nav-tab premium' href='#'><?php esc_html_e( 'Delete Categories', 'content-excel-importer' ); ?></a>
	</h2>

	<?php
		contentExceIimporter_main();
	?>

	</div>


	<?php
	contentExceIimporter_form_footer();
}

function contentExceIimporter_form_header() {
	/** This function is plugin header. */

	?>
	<h1 style='display:flex;align-items:center;' ><a target='_blank' href='<?php echo esc_url( 'https://extend-wp.com/wordpress-premium-plugins' ); ?> '>

	<img   style='width:170px;padding-right:30px' src='<?php echo esc_url( plugins_url( 'images/extendwp.png', __FILE__ ) ); ?>' alt='<?php esc_html_e( 'Get more plugins by extendWP', 'content-excel-importer' ); ?> title='<?php esc_html_e( 'Get more plugins by extendWP', 'content-excel-importer' ); ?> />
		</a> <span style='color:#2271b1;'><?php esc_html_e( 'Import Content in WordPress & WooCommerce with Excel', 'content-excel-importer' ); ?></span></h1>

	<?php
}

function contentExceIimporter_form_footer() {
	/** This function is plugin footer. */

	?>
	<hr>
		<div></div>
		<?php contentExceIimporter_rating(); ?>

	<?php
}

function contentExceIimporter_rating() {
	/** This function is plugin rating from wordpress.org. */
	?>
		<div class="notices notice-success rating is-dismissible">

			<?php esc_html_e( 'You like this plugin? ', 'content-excel-importer' ); ?><i class='fa fa-smile-o' ></i> <?php esc_html_e( 'Then please give us ', 'content-excel-importer' ); ?>
				<a target='_blank' href = '<?php echo esc_url( 'https://wordpress.org/support/plugin/content-excel-importer/reviews/#new-post' ); ?> '>
					<i class='fa fa-star' style='color:gold' ></i><i class='fa fa-star' style = 'color:gold' ></i><i class = 'fa fa-star' style = 'color:gold' ></i><i class='fa fa-star' style='color:gold' ></i><i class = 'fa fa-star' style = 'color:gold' ></i>
				</a>

		</div>
	<?php
}

// HPOS compatibility declaration.

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);


// Deactivation survey.

require plugin_dir_path( __FILE__ ) . '/lib/codecabin/plugin-deactivation-survey/deactivate-feedback-form.php';
add_filter(
	'codecabin_deactivate_feedback_form_plugins',
	function ( $plugins ) {

		$plugins[] = (object) array(
			'slug'    => 'content-excel-importer',
			'version' => '4.4',
		);

		return $plugins;
	}
);

// Email notification form.

register_activation_hook( __FILE__, 'contentExceIimporter_notification_hook' );

function contentExceIimporter_notification_hook() {
	/** This function sets a transient for displaying the notification message. */
	set_transient( 'contentExceIimporter_notification', true );
}

add_action( 'admin_notices', 'contentExceIimporter_notification' );

function contentExceIimporter_notification() {
	/** This function shows notification message. */

	$screen = get_current_screen();
	if ( 'toplevel_page_content-excel-importer' !== $screen->base ) {
		return;
	}

	// Check transient, if available display notice.
	if ( get_transient( 'contentExceIimporter_notification' ) ) {
		?>
		<div class="updated notice contentExceIimporter_notification">
			<a href = "#" class = 'dismiss' style = 'float:right;padding:4px' ><?php esc_html_e( 'close', 'content-excel-importer' ); ?></a>
			<h3><i><?php esc_html_e( 'Add your Email below & get ', 'content-excel-importer' ); ?><strong style='color:#00a32a'><?php esc_html_e( ' discounts', 'content-excel-importer' ); ?></strong><?php esc_html_e( ' in our pro plugins at', 'content-excel-importer' ); ?> <a href='<?php echo esc_url( 'https://extend-wp.com' ); ?>' target='_blank' >extend-wp.com!</a></i></h3>
			<form method='post' id = 'contentExceIimporter_signup' >
				<p>
				<input required type='email' name='woopei_email' />
				<input required type='hidden' name='product' value='1407' />
				<input type='submit' class='button button-primary' name='submit' value='<?php esc_html_e( 'Sign up', 'content-excel-importer' ); ?>' />
				</p>
			</form>
		</div>
		<?php
	}
}
add_action( 'wp_ajax_nopriv_contentExceIimporter_push_not', 'contentExceIimporter_push_not' );
add_action( 'wp_ajax_contentExceIimporter_push_not', 'contentExceIimporter_push_not' );

function contentExceIimporter_push_not() {
	/** This function deletes transient if user closes popup window. */

	delete_transient( 'contentExceIimporter_notification' );
}

?>