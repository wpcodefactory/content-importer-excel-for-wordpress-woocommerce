<?php
/**
 * Excel Importer for WooCommerce - the import screen: start, match columns, preview, importing, result, and the History page.
 *
 * The screen uploads the sheet once (cexl_upload), suggests a column match, shows a preview (cexl_preview) and then
 * imports row by row through the import action (import_content) with the same fields the old screen sent. The old
 * upload form request (importProducts posted to the page) still renders the old matching screen, so links, scripts
 * and bookmarks that post to the page keep working.
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import screen.
 */
class ContentExcelImporterApp {

	/**
	 * Page slug.
	 */
	const PAGE = 'content-excel-importer';

	/**
	 * Option with the update mode the matching screen starts with (mapped or all).
	 */
	const DEFAULT_OPTION = 'cexl_update_default';

	/**
	 * Sheet types the importer reads, with their PhpSpreadsheet reader.
	 */
	const READERS = array(
		'xlsx' => 'Xlsx',
		'xls'  => 'Xls',
		'ods'  => 'Ods',
		'csv'  => 'Csv',
	);

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'admin_init', array( __CLASS__, 'mark_existing_site' ) );
		add_filter( 'all_plugins', array( __CLASS__, 'short_name' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		add_action( 'wp_ajax_cexl_upload', array( $this, 'ajax_upload' ) );
		add_action( 'wp_ajax_cexl_report_get', array( $this, 'ajax_report' ) );
		add_action( 'wp_ajax_cexl_set_default', array( $this, 'ajax_set_default' ) );
	}

	/**
	 * New installs start in "only the columns in this sheet" (set on activation). A site that used the plugin before
	 * this version has no value yet: it keeps "every field", as before, until the owner picks the other mode.
	 */
	public static function activate() {
		if ( false === get_option( self::DEFAULT_OPTION ) ) {
			add_option( self::DEFAULT_OPTION, 'mapped', '', false );
		}
	}

	/**
	 * First admin load after an update: remember that this site keeps the old update mode.
	 */
	public static function mark_existing_site() {
		if ( false === get_option( self::DEFAULT_OPTION ) ) {
			add_option( self::DEFAULT_OPTION, 'all', '', false );
		}
	}

	/**
	 * The Plugins screen shows the short name, so the long listing name does not fill the row.
	 *
	 * @param array $plugins Installed plugins.
	 * @return array
	 */
	public static function short_name( $plugins ) {
		$file = plugin_basename( WPFACTORY_CEXL_FILE );
		if ( isset( $plugins[ $file ] ) ) {
			$plugins[ $file ]['Name'] = __( 'Excel Importer for WooCommerce', 'content-excel-importer' );
		}
		return $plugins;
	}

	/**
	 * Update mode the matching screen starts with.
	 *
	 * @return string mapped or all
	 */
	public static function update_default() {
		return 'mapped' === get_option( self::DEFAULT_OPTION, 'all' ) ? 'mapped' : 'all';
	}

	/**
	 * Whether this is the importer screen.
	 *
	 * @return bool
	 */
	public static function is_screen() {
		return isset( $_GET['page'] ) && self::PAGE === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Old form requests posted to the page (upload of the old matching screen, post type pick).
	 *
	 * @return bool
	 */
	public static function legacy_request() {
		return isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && ( isset( $_POST['importProducts'] ) || isset( $_POST['getPostType'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by the old handlers.
	}

	/**
	 * Body class of the importer screen.
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public function body_class( $classes ) {
		return self::is_screen() ? $classes . ' cexl-screen' : $classes;
	}

	/**
	 * Post types offered on the start screen: label => name.
	 *
	 * @return array
	 */
	public static function post_types() {
		$out = array();
		foreach ( array( 'product', 'post', 'page' ) as $type ) {
			$object = get_post_type_object( $type );
			if ( $object ) {
				$out[ $type ] = $object->labels->name;
			}
		}
		return $out;
	}

	/**
	 * Fields offered on the matching screen for a post type, in groups.
	 *
	 * @param string $type Post type.
	 * @return array list of { label, fields: key => label }
	 */
	public static function fields( $type ) {
		$is_product = 'product' === $type;
		$main       = array(
			'post_title'   => __( 'Title', 'content-excel-importer' ),
			'post_content' => $is_product ? __( 'Description', 'content-excel-importer' ) : __( 'Content', 'content-excel-importer' ),
			'post_excerpt' => $is_product ? __( 'Short description', 'content-excel-importer' ) : __( 'Excerpt', 'content-excel-importer' ),
			'post_status'  => __( 'Status (publish, draft...)', 'content-excel-importer' ),
			'post_date'    => __( 'Date', 'content-excel-importer' ),
			'post_name'    => __( 'URL slug', 'content-excel-importer' ),
			'post_author'  => __( 'Author (user ID)', 'content-excel-importer' ),
		);
		$groups     = array(
			array(
				'label'  => __( 'Main', 'content-excel-importer' ),
				'fields' => $main,
			),
		);
		if ( $is_product ) {
			$groups[] = array(
				'label'  => __( 'Product data', 'content-excel-importer' ),
				'fields' => array(
					'_sku'           => __( 'SKU', 'content-excel-importer' ),
					'_regular_price' => __( 'Regular price', 'content-excel-importer' ),
					'_sale_price'    => __( 'Sale price', 'content-excel-importer' ),
					'_stock'         => __( 'Stock quantity', 'content-excel-importer' ),
					'_weight'        => __( 'Weight', 'content-excel-importer' ),
					'_virtual'       => __( 'Virtual (yes or no)', 'content-excel-importer' ),
				),
			);
		}
		$taxonomies = array();
		$allowed    = array(
			'product' => array( 'product_cat', 'product_tag' ),
			'post'    => array( 'category', 'post_tag', 'language', 'post_translations' ),
			'page'    => array( 'language', 'post_translations' ),
		);
		foreach ( get_object_taxonomies( $type, 'objects' ) as $tax ) {
			if ( isset( $allowed[ $type ] ) && in_array( $tax->name, $allowed[ $type ], true ) ) {
				$taxonomies[ $tax->name ] = 'post_translations' === $tax->name ? __( 'Translation of (title)', 'content-excel-importer' ) : $tax->labels->name;
			}
		}
		if ( $taxonomies ) {
			$groups[] = array(
				'label'  => __( 'Categories and tags', 'content-excel-importer' ),
				'fields' => $taxonomies,
			);
		}
		$groups[] = array(
			'label'  => __( 'Finding items', 'content-excel-importer' ),
			'fields' => array( 'cexl_id' => __( 'ID (used to find the item, not imported)', 'content-excel-importer' ) ),
		);
		return $groups;
	}

	/**
	 * Every field name the import action reads, so the screen can send each one (empty when not used).
	 *
	 * @return array
	 */
	public static function post_fields() {
		return array( 'post_title', 'post_author', 'post_date', 'post_name', 'post_status', 'post_content', 'post_excerpt', '_virtual', '_sku', '_weight', '_regular_price', '_sale_price', '_stock', '_variation_description', '_length', '_width', '_height' );
	}

	/**
	 * Scripts and styles of the importer screen.
	 */
	public function assets() {
		if ( ! self::is_screen() ) {
			return;
		}
		$base = plugin_dir_url( __FILE__ );
		wp_enqueue_style( 'cexl-app', $base . 'css/cexl-app.css', array(), WPFACTORY_CEXL_VERSION );
		wp_enqueue_script( 'cexl-app', $base . 'js/cexl-app.js', array( 'jquery' ), WPFACTORY_CEXL_VERSION, true );
		wp_localize_script(
			'cexl-app',
			'cexlApp',
			array(
				'ajax'          => admin_url( 'admin-ajax.php' ),
				'edition'       => 'free',
				'extensions'    => array_keys( self::READERS ),
				'updateDefault' => self::update_default(),
				'editUrl'       => admin_url( 'edit.php?post_type=' ),
				'reportCsv'     => wp_nonce_url( admin_url( 'admin-post.php?action=cexl_report_csv' ), 'cexl_report_csv' ),
				'actions'       => array(
					'upload'     => 'cexl_upload',
					'preview'    => 'cexl_preview',
					'import'     => 'import_content',
					'report'     => 'cexl_report_get',
					'setDefault' => 'cexl_set_default',
					'aiMap'      => 'cexl_ai_map',
					'aiDescribe'    => 'cexl_ai_describe',
					'aiApply'       => 'cexl_ai_apply',
					'aiCheck'       => 'cexl_ai_check',
				),
				'ai'            => ContentExcelImporterAI::script_settings(),
				'nonces'        => array(
					'upload'     => wp_create_nonce( 'excel_upload' ),
					'process'    => wp_create_nonce( 'excel_process' ),
					'report'     => wp_create_nonce( 'cexl_report_csv' ),
					'setDefault' => wp_create_nonce( 'cexl_set_default' ),
				),
				'i18n'          => self::strings(),
			)
		);
	}

	/**
	 * Strings of the screen script.
	 *
	 * @return array
	 */
	public static function strings() {
		return array(
			/* translators: %s: list of file types */
			'badFile'       => __( 'This file type cannot be read. Use one of: %s.', 'content-excel-importer' ),
			'reading'       => __( 'Reading your sheet...', 'content-excel-importer' ),
			'failed'        => __( 'Something went wrong. Please try again.', 'content-excel-importer' ),
			'expired'       => __( 'Your session has expired. Reload the page and try again.', 'content-excel-importer' ),
			'skipColumn'    => __( 'Skip this column', 'content-excel-importer' ),
			'skipped'       => __( 'Skipped', 'content-excel-importer' ),
			'chosen'        => __( 'Set', 'content-excel-importer' ),
			'saved'         => __( 'Saved match', 'content-excel-importer' ),
			'matched'       => __( 'Matched', 'content-excel-importer' ),
			'check'         => __( 'Check', 'content-excel-importer' ),
			/* translators: %s: column letter */
			'column'        => __( 'Column %s', 'content-excel-importer' ),
			/* translators: %s: column name */
			'fieldFor'      => __( 'Field for %s', 'content-excel-importer' ),
			/* translators: 1: file name, 2: number of rows */
			'stepTwo'       => __( 'Step 2 of 3 · %1$s · rows: %2$s', 'content-excel-importer' ),
			'usedSaved'     => __( 'This sheet has the same columns as one you imported before, so your earlier matches are used.', 'content-excel-importer' ),
			'needTitle'     => __( 'Match a column to Title. It is needed for every import.', 'content-excel-importer' ),
			'needSku'       => __( 'To find products by SKU, match a column to SKU.', 'content-excel-importer' ),
			'needId'        => __( 'To find items by ID, match a column to "ID (used to find the item)".', 'content-excel-importer' ),
			'empty'         => __( 'empty', 'content-excel-importer' ),
			'someCheck'     => __( 'Some rows need a look: see the rows marked Check below.', 'content-excel-importer' ),
			'noChange'      => __( 'No change', 'content-excel-importer' ),
			'update'        => __( 'Update', 'content-excel-importer' ),
			'create'        => __( 'Create', 'content-excel-importer' ),
			'same'          => __( 'Same', 'content-excel-importer' ),
			'skip'          => __( 'Skip', 'content-excel-importer' ),
			'noTitle'       => __( '(no title)', 'content-excel-importer' ),
			'isNew'         => __( 'new', 'content-excel-importer' ),
			/* translators: %s: number of rows not shown */
			'moreRows'      => __( 'Rows not shown here: %s. They are imported too.', 'content-excel-importer' ),
			'noRows'        => __( 'This sheet has no rows to import below the column names.', 'content-excel-importer' ),
			'pause'         => __( 'Pause', 'content-excel-importer' ),
			'resume'        => __( 'Resume', 'content-excel-importer' ),
			/* translators: 1: current row, 2: number of rows */
			'rowOf'         => __( 'Row %1$s of %2$s', 'content-excel-importer' ),
			/* translators: %s: row number */
			'rowN'          => __( 'Row %s', 'content-excel-importer' ),
			'created'       => __( 'Created', 'content-excel-importer' ),
			'updated'       => __( 'Updated', 'content-excel-importer' ),
			'failedRow'     => __( 'Failed', 'content-excel-importer' ),
			'doneTitle'     => __( 'Import done', 'content-excel-importer' ),
			'stoppedTitle'  => __( 'Import stopped', 'content-excel-importer' ),
			'aiWorking'     => __( 'Matching your columns with AI...', 'content-excel-importer' ),
			'aiDone'        => __( 'AI matched your columns. Check the ones marked Check before you go on.', 'content-excel-importer' ),
			'aiMatch'       => __( 'AI match', 'content-excel-importer' ),
			'aiConnect'     => __( 'Turn on AI credits', 'content-excel-importer' ),
			/* translators: %s: number of items */
			'aiDescribeN'   => __( 'New items without a description: %s', 'content-excel-importer' ),
			/* translators: %s: number of credits */
			'aiAbout'       => __( 'about %s credits', 'content-excel-importer' ),
			/* translators: 1: item number, 2: number of items */
			'aiWriting'     => __( 'Writing %1$s of %2$s...', 'content-excel-importer' ),
			'aiReview'      => __( 'Check each text, change it if you like, then save it. Nothing is saved on the item until you click Save.', 'content-excel-importer' ),
			'aiShort'       => __( 'Short description', 'content-excel-importer' ),
			'aiSave'        => __( 'Save', 'content-excel-importer' ),
			'aiSkip'        => __( 'Skip', 'content-excel-importer' ),
			'aiSaved'       => __( 'Saved', 'content-excel-importer' ),
			'aiSkipped'     => __( 'Skipped', 'content-excel-importer' ),
			'aiChecking'    => __( 'Checking the planned changes with AI...', 'content-excel-importer' ),
			/* translators: %s: number of rows */
			'aiFlags'       => __( 'AI flagged rows to look at: %s. They are marked AI below. Nothing was changed.', 'content-excel-importer' ),
			'aiNoFlags'     => __( 'AI found nothing unusual in the planned changes.', 'content-excel-importer' ),
			'aiFlag'        => __( 'AI', 'content-excel-importer' ),
		);
	}

	/**
	 * Reads an uploaded sheet with the reader of its file type.
	 *
	 * @param string $path      Uploaded file.
	 * @param string $extension File type.
	 * @return array Rows keyed by row number, then column letter.
	 */
	public static function read_sheet( $path, $extension ) {
		if ( ! class_exists( 'PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
			include plugin_dir_path( __FILE__ ) . '/Classes/autoload.php';
		}
		$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader( self::READERS[ $extension ] );
		return $reader->load( $path )->getActiveSheet()->toArray( null, true, true, true );
	}

	/**
	 * AJAX: stores an uploaded sheet in the closed folder and returns its columns and a suggested match.
	 */
	public function ajax_upload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to import.', 'content-excel-importer' ) ), 403 );
		}
		check_ajax_referer( 'excel_upload', 'nonce' );
		$type  = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$types = self::post_types();
		if ( ! isset( $types[ $type ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose what you are importing first.', 'content-excel-importer' ) ) );
		}
		$tmp  = isset( $_FILES['file']['tmp_name'] ) ? $_FILES['file']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- a temporary path from PHP, checked with is_uploaded_file().
		$name = isset( $_FILES['file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ) : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( '' === $tmp || ! is_uploaded_file( $tmp ) || empty( $_FILES['file']['size'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The file did not arrive. It may be larger than your server allows.', 'content-excel-importer' ) ) );
		}
		if ( ! isset( self::READERS[ $ext ] ) ) {
			/* translators: %s: list of file types */
			wp_send_json_error( array( 'message' => sprintf( __( 'This file type cannot be read. Use one of: %s.', 'content-excel-importer' ), implode( ', ', array_keys( self::READERS ) ) ) ) );
		}
		try {
			$rows = self::read_sheet( $tmp, $ext );
		} catch ( \Throwable $e ) {
			wp_send_json_error( array( 'message' => __( 'This file could not be read. Open it in Excel, save it again as .xlsx and upload that copy.', 'content-excel-importer' ) ) );
		}
		if ( empty( $rows[1] ) ) {
			wp_send_json_error( array( 'message' => __( 'The first row of the sheet is empty. Put the column names in the first row.', 'content-excel-importer' ) ) );
		}

		// Remove sheets of imports that were never finished, then keep this one until its last row is imported.
		foreach ( (array) glob( ContentExcelImporterProducts::import_dir() . '/import-*' ) as $old_file ) {
			if ( filemtime( $old_file ) < time() - DAY_IN_SECONDS ) {
				wp_delete_file( $old_file );
			}
		}
		$token = wp_generate_password( 32, false, false );
		$file  = ContentExcelImporterProducts::import_file( $token );
		move_uploaded_file( $tmp, $file );
		file_put_contents( substr( $file, 0, -5 ) . '.json', (string) wp_json_encode( $rows ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$headers = array_map( 'strval', $rows[1] );
		$first   = isset( $rows[2] ) ? array_map( 'strval', $rows[2] ) : array();
		$groups  = self::fields( $type );
		wp_send_json_success(
			array(
				'token'       => $token,
				'file_name'   => $name,
				'total'       => count( $rows ),
				'rows'        => count( $rows ) - 1,
				'headers'     => array_map( 'sanitize_text_field', $headers ),
				'first'       => array_map( 'sanitize_text_field', $first ),
				'fields'      => $groups,
				'post_fields' => self::post_fields(),
				'suggestion'  => self::suggestion( $headers, $type, $groups ),
			)
		);
	}

	/**
	 * Suggested match for the header row, with the fields whose header is the field's own name ("sure").
	 *
	 * @param array  $headers Column letter => header.
	 * @param string $type    Post type.
	 * @param array  $groups  Field groups.
	 * @return array
	 */
	public static function suggestion( $headers, $type, $groups ) {
		$fields = array();
		foreach ( $groups as $group ) {
			$fields += $group['fields'];
		}
		$suggest         = ContentExcelImporterMappings::suggest( $headers, $type, $fields );
		$suggest['sure'] = array();
		foreach ( $suggest['map'] as $field => $letter ) {
			$header = ContentExcelImporterMappings::normalize( isset( $headers[ $letter ] ) ? $headers[ $letter ] : '' );
			$label  = isset( $fields[ $field ] ) ? $fields[ $field ] : '';
			$suggest['sure'][ $field ] = in_array( $header, array( ContentExcelImporterMappings::normalize( $field ), ContentExcelImporterMappings::normalize( $label ), ContentExcelImporterMappings::normalize( preg_replace( '/\s*\(.*\)$/', '', $label ) ) ), true );
		}
		$suggest['map'] = (object) $suggest['map'];
		$suggest['sure'] = (object) $suggest['sure'];
		return $suggest;
	}

	/**
	 * AJAX: counts of an import's report.
	 */
	public function ajax_report() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(), 403 );
		}
		check_ajax_referer( 'cexl_report_csv', 'nonce' );
		$report = ContentExcelImporterReport::get( isset( $_POST['report'] ) ? sanitize_text_field( wp_unslash( $_POST['report'] ) ) : '' );
		if ( null === $report ) {
			wp_send_json_error();
		}
		wp_send_json_success(
			array(
				'counts'   => $report['counts'],
				'describe' => ContentExcelImporterAI::available() ? ContentExcelImporterAI::describe_candidates( $report ) : array(),
			)
		);
	}

	/**
	 * AJAX: the matching screen's "use this as the default" box.
	 */
	public function ajax_set_default() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array(), 403 );
		}
		check_ajax_referer( 'cexl_set_default', 'nonce' );
		$update = isset( $_POST['update'] ) && 'mapped' === $_POST['update'] ? 'mapped' : 'all';
		update_option( self::DEFAULT_OPTION, $update, false );
		wp_send_json_success();
	}

	/**
	 * Date of a report in the site's format.
	 *
	 * @param int $time Unix time.
	 * @return string
	 */
	public static function when( $time ) {
		return wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), (int) $time );
	}

	/**
	 * Result chips of a report.
	 *
	 * @param array $counts Counts.
	 * @return string HTML.
	 */
	public static function count_chips( $counts ) {
		$counts = wp_parse_args( $counts, array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0 ) );
		$out    = '';
		if ( $counts['updated'] ) {
			/* translators: %s: number of items */
			$out .= '<span class="chip info">' . esc_html( sprintf( _n( '%s updated', '%s updated', $counts['updated'], 'content-excel-importer' ), number_format_i18n( $counts['updated'] ) ) ) . '</span> ';
		}
		if ( $counts['created'] ) {
			/* translators: %s: number of items */
			$out .= '<span class="chip ok">' . esc_html( sprintf( _n( '%s created', '%s created', $counts['created'], 'content-excel-importer' ), number_format_i18n( $counts['created'] ) ) ) . '</span> ';
		}
		if ( $counts['skipped'] ) {
			/* translators: %s: number of rows */
			$out .= '<span class="chip plain">' . esc_html( sprintf( _n( '%s skipped', '%s skipped', $counts['skipped'], 'content-excel-importer' ), number_format_i18n( $counts['skipped'] ) ) ) . '</span> ';
		}
		if ( $counts['failed'] ) {
			/* translators: %s: number of rows */
			$out .= '<span class="chip bad">' . esc_html( sprintf( _n( '%s failed', '%s failed', $counts['failed'], 'content-excel-importer' ), number_format_i18n( $counts['failed'] ) ) ) . '</span> ';
		}
		return '' === $out ? '<span class="chip plain">' . esc_html__( 'Nothing imported', 'content-excel-importer' ) . '</span>' : $out;
	}

	/**
	 * Top bar.
	 *
	 * @param string $current Current view.
	 */
	public static function topbar( $current ) {
		$views = array(
			'import'  => __( 'Import', 'content-excel-importer' ),
			'history' => __( 'History', 'content-excel-importer' ),
		);
		?>
		<header class="topbar">
			<div class="left">
				<p class="brand"><img class="brand-mark" src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'images/icon.svg' ); ?>" width="32" height="32" alt=""> <?php esc_html_e( 'Excel Importer', 'content-excel-importer' ); ?></p>
				<nav class="nav" aria-label="<?php esc_attr_e( 'Importer', 'content-excel-importer' ); ?>">
					<?php foreach ( $views as $view => $label ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE . ( 'import' === $view ? '' : '&view=' . $view ) ) ); ?>"<?php echo $view === $current ? ' class="on" aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>
			<div class="row-actions">
				<a class="btn btn-text" target="_blank" rel="noopener" href="https://wpfactory.com/item/content-importer-for-wordpress-woocommerce-with-excel/"><?php esc_html_e( 'Pro version', 'content-excel-importer' ); ?></a>
				<?php echo wp_kses_post( ContentExcelImporterAI::pill() ); ?>
			</div>
		</header>
		<?php
	}

	/**
	 * The page.
	 */
	public static function render() {
		$view = isset( $_GET['view'] ) && 'history' === $_GET['view'] ? 'history' : 'import'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap cexl-app">';
		echo '<h1 class="screen-reader-text">' . esc_html__( 'Excel Importer', 'content-excel-importer' ) . '</h1>';
		self::topbar( $view );
		echo '<main class="page">';
		if ( 'history' === $view ) {
			self::render_history();
		} else {
			self::render_import();
		}
		echo '</main></div>';
	}

	/**
	 * History page.
	 */
	public static function render_history() {
		$reports = ContentExcelImporterReport::all();
		$types   = self::post_types();
		?>
		<div class="page-head"><div><div class="eyebrow"><?php esc_html_e( 'History', 'content-excel-importer' ); ?></div><h2 class="cexl-h1"><?php esc_html_e( 'Past imports', 'content-excel-importer' ); ?></h2>
		<p class="lead"><?php esc_html_e( 'Every import made on this screen, kept for 30 days. Download a report to see the result of each row.', 'content-excel-importer' ); ?></p></div></div>
		<div class="card"><div class="table-wrap"><table class="cexl-table stacked">
			<thead><tr><th><?php esc_html_e( 'File', 'content-excel-importer' ); ?></th><th><?php esc_html_e( 'Type', 'content-excel-importer' ); ?></th><th><?php esc_html_e( 'When', 'content-excel-importer' ); ?></th><th><?php esc_html_e( 'Result', 'content-excel-importer' ); ?></th><th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'content-excel-importer' ); ?></span></th></tr></thead>
			<tbody>
			<?php if ( ! $reports ) : ?>
				<tr><td colspan="5" class="muted"><?php esc_html_e( 'No imports yet. Imports you make from now on are listed here.', 'content-excel-importer' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $reports as $report ) : ?>
				<tr>
					<td><b><?php echo esc_html( '' !== $report['file'] ? $report['file'] : __( '(no file name)', 'content-excel-importer' ) ); ?></b></td>
					<td><?php echo esc_html( isset( $types[ $report['type'] ] ) ? $types[ $report['type'] ] : $report['type'] ); ?></td>
					<td class="nowrap"><?php echo esc_html( self::when( $report['started'] ) ); ?></td>
					<td><?php echo wp_kses_post( self::count_chips( $report['counts'] ) ); ?></td>
					<td class="nowrap"><a href="<?php echo esc_url( ContentExcelImporterReport::csv_url( $report['token'] ) ); ?>"><?php esc_html_e( 'Report', 'content-excel-importer' ); ?></a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table></div></div>
		<?php
	}

	/**
	 * Import screens (start, match columns, preview, importing, result); the script shows one at a time.
	 */
	public static function render_import() {
		$types   = self::post_types();
		$first   = isset( $types['product'] ) ? 'product' : 'post';
		$recent  = array_slice( ContentExcelImporterReport::all(), 0, 3 );
		$samples = plugin_dir_url( __FILE__ ) . 'example_excel/';
		?>
		<section data-screen="start">
			<div class="page-head"><div><div class="eyebrow"><?php esc_html_e( 'Import', 'content-excel-importer' ); ?></div><h2 class="cexl-h1"><?php esc_html_e( 'Import from Excel', 'content-excel-importer' ); ?></h2>
			<p class="lead"><?php esc_html_e( 'Update your products, posts and pages from a sheet. You see every change before it is saved.', 'content-excel-importer' ); ?></p></div></div>
			<div class="grid">
				<div class="stack">
					<div id="cexl-start-msg" aria-live="polite"></div>
					<div class="card">
						<div class="card-head"><h3 class="card-title"><?php esc_html_e( 'What are you importing?', 'content-excel-importer' ); ?></h3></div>
						<div class="card-body">
							<div class="seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Content type', 'content-excel-importer' ); ?>">
								<?php foreach ( $types as $type => $label ) : ?>
									<label><input type="radio" name="cexl_type" value="<?php echo esc_attr( $type ); ?>"<?php checked( $type, $first ); ?>><span><?php echo esc_html( $label ); ?></span></label>
								<?php endforeach; ?>
							</div>
							<div class="drop" data-drop="import">
								<strong><?php esc_html_e( 'Drop your sheet here', 'content-excel-importer' ); ?></strong>
								<span class="muted"><?php esc_html_e( '.xlsx, .xls, .ods or .csv. The first row holds the column names.', 'content-excel-importer' ); ?></span>
								<div class="row-actions">
									<input type="file" id="cexl-file" class="file-input" accept=".xlsx,.xls,.ods,.csv">
									<label for="cexl-file" id="cexl-choose" class="btn btn-primary"><?php esc_html_e( 'Choose a file', 'content-excel-importer' ); ?></label>
								</div>
							</div>
						</div>
						<div class="card-foot"><span class="muted small"><?php esc_html_e( 'No sheet yet? Start from a sample:', 'content-excel-importer' ); ?>
							<a data-for-type="product" href="<?php echo esc_url( $samples . 'import_update_products.xlsx' ); ?>"><?php esc_html_e( 'Products', 'content-excel-importer' ); ?></a>
							<a data-for-type="post page" href="<?php echo esc_url( $samples . 'content.xlsx' ); ?>"><?php esc_html_e( 'Posts and pages', 'content-excel-importer' ); ?></a></span></div>
					</div>
				</div>
				<div class="stack">
					<div class="card">
						<div class="card-head"><h3 class="card-title"><?php esc_html_e( 'Recent imports', 'content-excel-importer' ); ?></h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE . '&view=history' ) ); ?>"><?php esc_html_e( 'All', 'content-excel-importer' ); ?></a></div>
						<div class="card-body">
							<?php if ( ! $recent ) : ?>
								<p class="empty"><?php esc_html_e( 'No imports yet.', 'content-excel-importer' ); ?></p>
							<?php else : ?>
								<ul class="list">
									<?php foreach ( $recent as $report ) : ?>
										<li><span><b><?php echo esc_html( '' !== $report['file'] ? $report['file'] : __( '(no file name)', 'content-excel-importer' ) ); ?></b><br><span class="muted small"><?php echo esc_html( ( isset( $types[ $report['type'] ] ) ? $types[ $report['type'] ] : $report['type'] ) . ' · ' . self::when( $report['started'] ) ); ?></span></span><span><?php echo wp_kses_post( self::count_chips( $report['counts'] ) ); ?></span></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</div>
					<div class="card"><div class="card-body"><b><?php esc_html_e( 'Same sheet every week?', 'content-excel-importer' ); ?></b><p class="muted"><?php esc_html_e( 'Your column matches are saved per sheet layout. Next time the columns match themselves.', 'content-excel-importer' ); ?></p></div></div>
				</div>
			</div>
		</section>

		<?php self::render_map_screen( false ); ?>
		<?php self::render_run_screens( false ); ?>
		<?php
	}

	/**
	 * Match columns screen.
	 *
	 * @param bool $pro Pro options (attributes only).
	 */
	public static function render_map_screen( $pro ) {
		?>
		<section data-screen="map" hidden>
			<div class="page-head">
				<div><div class="eyebrow" id="cexl-map-eyebrow"></div><h2 class="cexl-h1"><?php esc_html_e( 'Match your columns', 'content-excel-importer' ); ?></h2>
				<p class="lead"><?php esc_html_e( 'We matched what we could. Check the highlighted ones; columns set to "Skip this column" are not imported.', 'content-excel-importer' ); ?></p></div>
				<?php if ( ContentExcelImporterAI::available() ) : ?>
					<div class="row-actions"><button type="button" class="btn btn-secondary" id="cexl-ai-map"><span class="spark" aria-hidden="true">&#10022;</span> <?php esc_html_e( 'Match with AI', 'content-excel-importer' ); ?> <span class="muted small"><?php esc_html_e( 'about 2 credits', 'content-excel-importer' ); ?></span></button></div>
				<?php endif; ?>
			</div>
			<div id="cexl-map-msg" aria-live="polite"></div>
			<div class="grid">
				<div class="card">
					<div class="table-wrap"><table class="cexl-table mapping">
						<thead><tr><th><?php esc_html_e( 'Your column', 'content-excel-importer' ); ?></th><th><?php esc_html_e( 'First value', 'content-excel-importer' ); ?></th><th><?php esc_html_e( 'Goes to', 'content-excel-importer' ); ?></th><th><span class="screen-reader-text"><?php esc_html_e( 'Status', 'content-excel-importer' ); ?></span></th></tr></thead>
						<tbody id="cexl-map-rows"></tbody>
					</table></div>
					<div class="card-foot"><a class="btn btn-text" href="#" data-go="start"><?php esc_html_e( 'Back', 'content-excel-importer' ); ?></a><button type="button" class="btn btn-primary" id="cexl-to-preview"><?php esc_html_e( 'Preview changes', 'content-excel-importer' ); ?></button></div>
				</div>
				<div class="stack">
					<div id="cexl-map-problem" aria-live="polite"></div>
					<div class="card" data-map-options>
						<div class="card-head"><h3 class="card-title"><?php esc_html_e( 'How to update', 'content-excel-importer' ); ?></h3></div>
						<div class="card-body">
							<p class="group-label" id="cexl-match-label"><?php esc_html_e( 'Find existing items by', 'content-excel-importer' ); ?></p>
							<div class="seg" role="radiogroup" aria-labelledby="cexl-match-label">
								<label data-only-product><input type="radio" name="cexl_match" value="sku"><span><?php esc_html_e( 'SKU', 'content-excel-importer' ); ?></span></label>
								<label><input type="radio" name="cexl_match" value="id"><span><?php esc_html_e( 'ID', 'content-excel-importer' ); ?></span></label>
								<label><input type="radio" name="cexl_match" value="title" checked><span><?php esc_html_e( 'Title', 'content-excel-importer' ); ?></span></label>
							</div>
							<p class="group-label"><?php esc_html_e( 'When an item exists', 'content-excel-importer' ); ?></p>
							<label class="choice"><input type="radio" name="cexl_update" value="mapped"> <span><?php esc_html_e( 'Change only the columns in this sheet', 'content-excel-importer' ); ?></span></label>
							<label class="choice"><input type="radio" name="cexl_update" value="all"> <span><?php esc_html_e( 'Overwrite every field (the old behaviour)', 'content-excel-importer' ); ?></span></label>
							<label class="choice"><input type="radio" name="cexl_update" value="new"> <span><?php esc_html_e( 'Add a new item anyway', 'content-excel-importer' ); ?></span></label>
							<label class="choice small" id="cexl-remember" hidden><input type="checkbox" id="cexl-remember-box"> <span><?php esc_html_e( 'Use this choice for my next imports too', 'content-excel-importer' ); ?></span></label>
							<div data-when-matching>
								<p class="group-label"><?php esc_html_e( 'When nothing matches', 'content-excel-importer' ); ?></p>
								<label class="choice"><input type="radio" name="cexl_missing" value="create" checked> <span><?php esc_html_e( 'Create it', 'content-excel-importer' ); ?></span></label>
								<label class="choice"><input type="radio" name="cexl_missing" value="skip"> <span><?php esc_html_e( 'Skip the row', 'content-excel-importer' ); ?></span></label>
							</div>
							<?php if ( $pro ) : ?>
								<div data-only-product>
									<p class="group-label"><?php esc_html_e( 'Attributes', 'content-excel-importer' ); ?></p>
									<label class="choice"><input type="checkbox" id="cexl-attributes-only"> <span><?php esc_html_e( 'Add attributes only, no variations (one row per product)', 'content-excel-importer' ); ?></span></label>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * Preview, importing and result screens.
	 *
	 * @param bool $pro Pro edition.
	 */
	public static function render_run_screens( $pro ) {
		?>
		<section data-screen="preview" hidden>
			<div class="page-head">
				<div><div class="eyebrow"><?php esc_html_e( 'Step 3 of 3 · nothing is saved yet', 'content-excel-importer' ); ?></div><h2 class="cexl-h1"><?php esc_html_e( 'Preview the changes', 'content-excel-importer' ); ?></h2></div>
				<div class="row-actions">
				<?php if ( ContentExcelImporterAI::available() ) : ?><button type="button" class="btn btn-secondary" id="cexl-ai-check"><span class="spark" aria-hidden="true">&#10022;</span> <?php esc_html_e( 'Check with AI', 'content-excel-importer' ); ?> <span class="muted small" id="cexl-ai-check-cost"></span></button><?php endif; ?>
				<a class="btn btn-text" href="#" data-go="map"><?php esc_html_e( 'Back', 'content-excel-importer' ); ?></a><button type="button" class="btn btn-primary" id="cexl-start-import"><?php esc_html_e( 'Start the import', 'content-excel-importer' ); ?></button></div>
			</div>
			<div class="tiles">
				<div class="tile"><b id="cexl-tile-update">0</b><span><?php esc_html_e( 'will be updated', 'content-excel-importer' ); ?></span></div>
				<div class="tile"><b id="cexl-tile-create">0</b><span><?php esc_html_e( 'will be created', 'content-excel-importer' ); ?></span></div>
				<div class="tile"><b id="cexl-tile-skip">0</b><span><?php esc_html_e( 'skipped or unchanged', 'content-excel-importer' ); ?></span></div>
				<div class="tile"><b id="cexl-tile-check">0</b><span><?php esc_html_e( 'to check', 'content-excel-importer' ); ?></span></div>
			</div>
			<div id="cexl-preview-msg" aria-live="polite"></div>
			<div class="card"><div class="table-wrap"><table class="cexl-table stacked">
				<thead><tr><th><?php esc_html_e( 'Row', 'content-excel-importer' ); ?></th><th><?php esc_html_e( 'Item', 'content-excel-importer' ); ?></th><th><?php esc_html_e( 'What changes', 'content-excel-importer' ); ?></th><th><span class="screen-reader-text"><?php esc_html_e( 'Action', 'content-excel-importer' ); ?></span></th></tr></thead>
				<tbody id="cexl-preview-rows"></tbody>
			</table></div></div>
		</section>

		<section data-screen="run" hidden>
			<div class="page-head"><div><div class="eyebrow" id="cexl-run-eyebrow"></div><h2 class="cexl-h1"><?php esc_html_e( 'Importing', 'content-excel-importer' ); ?></h2>
				<p class="lead"><?php esc_html_e( 'Keep this page open until the import finishes. You can pause or stop at any row.', 'content-excel-importer' ); ?></p></div>
				<div class="row-actions"><button type="button" class="btn btn-secondary" id="cexl-pause"><?php esc_html_e( 'Pause', 'content-excel-importer' ); ?></button><button type="button" class="btn btn-danger" id="cexl-stop"><?php esc_html_e( 'Stop', 'content-excel-importer' ); ?></button></div></div>
			<div id="cexl-run-msg" aria-live="polite"></div>
			<div class="card"><div class="card-body">
				<div class="progress-head"><b id="cexl-run-count"></b></div>
				<div class="bar" id="cexl-run-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" aria-label="<?php esc_attr_e( 'Import progress', 'content-excel-importer' ); ?>"><i></i></div>
				<ul class="list" id="cexl-run-list" aria-live="polite" style="margin-top:16px"></ul>
			</div></div>
		</section>

		<section data-screen="result" hidden>
			<div class="page-head"><div><div class="eyebrow" id="cexl-result-eyebrow"></div><h2 class="cexl-h1" id="cexl-result-title"><?php esc_html_e( 'Import done', 'content-excel-importer' ); ?></h2></div>
				<div class="row-actions"><a class="btn btn-secondary" id="cexl-report-csv" href="#"><?php esc_html_e( 'Download the report', 'content-excel-importer' ); ?></a><a class="btn btn-text" href="#" id="cexl-again"><?php esc_html_e( 'Import another file', 'content-excel-importer' ); ?></a><a class="btn btn-primary" id="cexl-view-items" href="#"><?php esc_html_e( 'View items', 'content-excel-importer' ); ?></a></div></div>
			<div class="tiles">
				<div class="tile"><b id="cexl-res-updated">0</b><span><?php esc_html_e( 'updated', 'content-excel-importer' ); ?></span></div>
				<div class="tile"><b id="cexl-res-created">0</b><span><?php esc_html_e( 'created', 'content-excel-importer' ); ?></span></div>
				<div class="tile"><b id="cexl-res-skipped">0</b><span><?php esc_html_e( 'skipped', 'content-excel-importer' ); ?></span></div>
				<div class="tile"><b id="cexl-res-failed">0</b><span><?php esc_html_e( 'failed', 'content-excel-importer' ); ?></span></div>
			</div>
						<div class="card" id="cexl-ai-describe" hidden><div class="card-body" style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap">
				<div><b><span class="spark" aria-hidden="true">&#10022;</span> <span id="cexl-ai-describe-title"></span></b><p class="muted" style="margin:4px 0 0"><?php esc_html_e( 'Write them from each item\'s own data (title, categories, attributes, SKU). You check every text before it is saved.', 'content-excel-importer' ); ?></p></div>
				<button type="button" class="btn btn-secondary" id="cexl-ai-write"><?php esc_html_e( 'Write with AI', 'content-excel-importer' ); ?> <span class="muted small" id="cexl-ai-estimate"></span></button>
			</div><div id="cexl-ai-describe-msg" aria-live="polite" style="padding:0 20px"></div><ul class="list" id="cexl-ai-drafts" style="padding:0 20px"></ul></div>
			<div class="card"><div class="card-body"><p class="muted"><?php esc_html_e( 'The report lists every row with its result and a link to the item. It is also on the History page for 30 days.', 'content-excel-importer' ); ?></p></div></div>
		</section>
		<?php
	}
}

new ContentExcelImporterApp();
