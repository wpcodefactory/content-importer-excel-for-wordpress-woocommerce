<?php
/**
 * Import Content in WordPress & WooCommerce with Excel - ContentExcelImporterProducts Class
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

/** this file contains the class that handles import of content **/

if ( ! class_exists( 'PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
	include plugin_dir_path( __FILE__ ) . '/Classes/autoload.php';
}
use PhpOffice\PhpSpreadsheet\Helper\Sample;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ContentExcelImporterProducts extends ContentExcelImporterQuery {
	/** this class handles import of content **/

	public $numberOfRows = 1;

public function __construct() {

		add_action( 'wp_ajax_import_content', array( $this, 'import_content' ) );
}

	/**
	 * Folder that holds uploaded sheets while an import runs (uploads/content-excel-importer), closed to visitors.
	 *
	 * @return string
	 */
	public static function import_dir() {
		$upload = wp_upload_dir( null, false );
		$dir    = trailingslashit( $upload['basedir'] ) . 'content-excel-importer';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		return $dir;
	}

	/**
	 * Path of the sheet for one import, from the random name the mapping screen carries.
	 *
	 * @param string $token Random 32 character name.
	 * @return string Empty when the name is not valid.
	 */
	public static function import_file( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ) {
			return '';
		}
		return self::import_dir() . '/import-' . $token . '.xlsx';
	}

	/**
	 * Existing item for a sheet row. Default: same title inside the chosen post type (no type chosen: posts). The import
	 * screen can ask for SKU or ID matching instead (cexl_match = sku or id, key column letter in cexl_match_column).
	 *
	 * @param string $title Title from the row.
	 * @param string $type  Post type of the import.
	 * @param array  $row   The row, keyed by column letter.
	 * @return int Post ID or 0 (then a new item is created).
	 */
	/**
	 * Update mode sent by the import screen: cexl_update = mapped changes only the columns of the sheet that have a value
	 * in the row. Anything else keeps the 5.0.4 behaviour (every field is written again).
	 *
	 * @return bool
	 */
	public static function only_mapped_columns() {
		return isset( $_POST['cexl_update'] ) && 'mapped' === $_POST['cexl_update']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in import_content().
	}

	/**
	 * For an update in "only the columns in this sheet" mode: keeps only the post fields whose column is mapped and has
	 * a value in this row. Fields left out keep their current value (status, date, short description, slug...).
	 *
	 * @param array $post Post fields the import would write.
	 * @param array $row  The row, keyed by column letter.
	 * @return array
	 */
	public static function keep_mapped_fields( $post, $row ) {
		$keep = array( 'ID' => $post['ID'] );
		foreach ( array( 'post_title', 'post_author', 'post_date', 'post_name', 'post_content', 'post_status', 'post_excerpt' ) as $field ) {
			$column = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( array_key_exists( $field, $post ) && '' !== $column && isset( $row[ $column ] ) && '' !== trim( (string) $row[ $column ] ) ) {
				$keep[ $field ] = $post[ $field ];
			}
		}
		return $keep;
	}

	public static function find_existing( $title, $type, $row ) {
		$type  = '' === (string) $type ? 'post' : (string) $type;
		$match = isset( $_POST['cexl_match'] ) ? sanitize_key( wp_unslash( $_POST['cexl_match'] ) ) : 'title'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in import_content().
		if ( 'sku' === $match || 'id' === $match ) {
			$column = isset( $_POST['cexl_match_column'] ) ? sanitize_text_field( wp_unslash( $_POST['cexl_match_column'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$key    = ( '' !== $column && isset( $row[ $column ] ) ) ? trim( (string) $row[ $column ] ) : '';
			if ( '' === $key ) {
				return 0;
			}
			if ( 'sku' === $match ) {
				$id = function_exists( 'wc_get_product_id_by_sku' ) ? (int) wc_get_product_id_by_sku( $key ) : 0;
			} else {
				$id = ctype_digit( $key ) ? (int) $key : 0;
			}
			return ( $id && get_post_type( $id ) === $type ) ? $id : 0;
		}
		return '' === (string) $title ? 0 : (int) post_exists( $title, '', '', $type );
	}

public function importProductsDisplay() {
	/** this method provides the html for the import display form */
	?>
		<h2><?php esc_html_e( 'IMPORT / UPDATE CONTENT', 'content-excel-importer' ); ?></h2>

		<div class='product_content'>

			<p><?php esc_html_e( 'Download the sample excel file, save it and add your products. You can add your Custom Columns. Upload it using the form below.', 'content-excel-importer' ); ?> <a href='<?php echo esc_url( plugins_url( '/example_excel/import_update_products.xlsx', __FILE__ ) ); ?>'><?php esc_html_e( 'SAMPLE EXCEL FILE', 'content-excel-importer' ); ?></a></p>
			<p>
		</div>
		<div class = 'randomContent' >
			<p><?php esc_html_e( 'Download the sample excel file, save it and add your content. You can add your Custom Columns. Upload it using the form below.', 'content-excel-importer' ); ?> <a href='<?php echo esc_url( plugins_url( '/example_excel/content.xlsx', __FILE__ ) ); ?>'><?php esc_html_e( 'CONTENT SAMPLE FILE', 'content-excel-importer' ); ?></a></p>
		</div>

		<div>

		<?php $this->selectPostTypeForm(); ?>

			<form method="post" id='product_import' class='excel_import' enctype="multipart/form-data" action= "<?php echo esc_url( admin_url( 'admin.php?page=content-excel-importer-pro&tab=main' ) ); ?>">
					<table class="form-table">
						<tr valign="top">

						<td><?php wp_nonce_field( 'excel_upload' ); ?>
						<input type="hidden"   name="importProducts" value="1" />
							<div class="uploader" style="background:url(<?php print esc_url( plugins_url( 'images/default.png', __FILE__ ) ); ?> ) no-repeat left center;" >
								<img src="" class='userSelected'/>
								<input type="file"  required name="file" id='file'  accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" />
							</div>
						</td>
						</tr>
					</table>
				<?php submit_button( 'Upload', 'primary', 'upload' ); ?>
					</form>
					<div class='result' ><?php $this->importProducts(); ?></div>

			</div>
		<?php
}

public function importProducts() {

	/** this method will get the excel data and provide the mapping screen of excel fields to post type fields */

	if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && current_user_can( 'manage_options' ) && $_POST['importProducts'] ) {

		check_admin_referer( 'excel_upload' );
		check_ajax_referer( 'excel_upload' );

		if ( isset( $_FILES['file']['tmp_name'] ) ) $filename = $_FILES['file']['tmp_name'] ;

			if ( isset( $_FILES['file']['size'] ) && $_FILES['file']['size'] > 0 ) {

				if ( $_FILES['file']['type'] === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ) {

					$objPHPExcel    = IOFactory::load( $filename );
					$allDataInSheet = $objPHPExcel->getActiveSheet()->toArray( null, true, true, true );
					$data           = count( $allDataInSheet );  // Here get total count of row in that Excel sheet
					$total          = $data;
					$totals         = $total - 1;
					// GET ROW NAMES ----- WORKING!!!!!
					$rownumber    = 1;
					$row          = $objPHPExcel->getActiveSheet()->getRowIterator( $rownumber )->current();
					$cellIterator = $row->getCellIterator();
					$cellIterator->setIterateOnlyExistingCells( false );

					$titleArray = array();

					$post_type = sanitize_text_field( $_POST['getPostType'] );

					print "<span style='display:none' class='thisNum'></span>";
					print "	<div class='ajaxResponse'></div>";

					print "<div style='overflow:hidden;min-height:400px;width:100%;'>
					 <form method='POST' style='overflow:hidden;min-height:400px;width:100%;' id='product_process' action= " . esc_url( admin_url( 'admin.php?page=content-excel-importer' ) ) . '>';

					print "<p style='font-style:italic'>" . esc_html__( 'DATA MAPPING: Drag and drop excel columns on the right to product properties on the left.', 'content-excel-importer' ) . '</p>';
					?>
					<i><b> <?php esc_html_e( 'Auto Match Columns', 'content-excel-importer' ); ?> <input type='checkbox' name='automatch_columns' id='automatch_columns' value='yes'  /> </b></i>
					<?php

						print "<p class=''><input type='checkbox' name='add_always_new' id='add_always_new' value='yes'  /> <b> " . esc_html__( 'Always add new content even if title is the same', 'content-excel-importer' ) . '</b></p>';

						print "<div style='float:right;width:48%'>";
							print "<h2 style='color:#0073aa;'>" . esc_html__( 'EXCEL COLUMNS', 'content-excel-importer' ) . '</h2><p>';
					foreach ( $cellIterator as $cell ) {
						// getValue.
						echo "<input type='button' class='draggable' style='min-width:200px' key ='" . esc_attr( $cell->getColumn() ) . "' value='" . esc_attr( $cell->getValue() ) . "' />  <br/>";
					}
						print '</p></div>';
						print "<div style='float:right;width:50%;text-align:right;padding-right:20px'>";
						print "<h2 style='color:#0073aa;'>" . esc_html__( 'FIELDS', 'content-excel-importer' ) . '</h2>';
					?>

						<?php $this->getFields( $post_type ); ?>

						<?php
						$token = wp_generate_password( 32, false, false );
						print "<input type='hidden' name='cexl_file' value='" . esc_attr( $token ) . "' />";
						print "<input type='hidden' name='finalupload' value='" . esc_attr( $total ) . "' />
						   <input type='hidden' name='import' value='import' />
						   <input type='hidden' name='start' value='2' />
						   <input type='hidden' name='action' value='import_content' />";
							wp_nonce_field( 'excel_process', 'secNonce' );

							submit_button( 'Upload', 'primary', 'check' );
							print '</div>';
						print '</form></div>';

						// Remove sheets of imports that were never finished, then keep this one until its last row is imported.
						foreach ( (array) glob( self::import_dir() . '/import-*' ) as $old_file ) {
							if ( filemtime( $old_file ) < time() - DAY_IN_SECONDS ) {
								wp_delete_file( $old_file );
							}
						}
						if ( isset( $_FILES['file']['tmp_name'] ) ) move_uploaded_file( $_FILES['file']['tmp_name'], self::import_file( $token ) );
						file_put_contents( substr( self::import_file( $token ), 0, -5 ) . '.json', (string) wp_json_encode( $allDataInSheet ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

				} else {
					'<h3>' . esc_html_e( 'Invalid File:Please Upload Excel File', 'content-excel-importer' ) . '</h3>';
				}
			}
		}
	}

	public function import_content() {

			/** this function is responsible to import the content */

		if ( isset( $_POST['finalupload'] ) && current_user_can( 'manage_options' ) ) {

			$time_start = microtime( true );
			check_admin_referer( 'excel_process', 'secNonce' );
			check_ajax_referer( 'excel_process', 'secNonce' );

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			$token    = isset( $_POST['cexl_file'] ) ? sanitize_text_field( wp_unslash( $_POST['cexl_file'] ) ) : '';
			$filename = self::import_file( $token );
			if ( '' === $filename || ! file_exists( $filename ) ) {
				print "<p class='warning' style='color:red'>" . esc_html__( 'The uploaded file was not found. Please upload it again.', 'content-excel-importer' ) . '</p>';
				die;
			}

			// The sheet is read once, when it is uploaded; every row request reads that copy (5.0.4 re-read the whole file per row).
			$sheet_cache    = substr( $filename, 0, -5 ) . '.json';
			$allDataInSheet = file_exists( $sheet_cache ) ? json_decode( (string) file_get_contents( $sheet_cache ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( ! is_array( $allDataInSheet ) ) {
				$objPHPExcel    = IOFactory::load( $filename );
				$allDataInSheet = $objPHPExcel->getActiveSheet()->toArray( null, true, true, true );
			}
			$data             = count( $allDataInSheet );  // Here get total count of row in that Excel sheet.
			$images           = array();
			$finalIdsArray    = array();
			$finalVarIdsArray = array();

			$multiarray = array();

			if ( ! empty( $_POST['post_title'] ) ) {

				$idsArray = array();

				// HERE ALL THE MAGIC HAPPENs.
				if ( isset( $_POST['start'] ) ) {

					$i     = (int) $_POST['start'];
					$start = $i - 1;

				}

				if ( '' !== $_POST['post_status'] ) {

					$status = sanitize_text_field( $allDataInSheet[ $i ][ wp_unslash( $_POST['post_status'] ) ] );

				} else {
					$status = 'publish';
				}

				$title = sanitize_text_field( $allDataInSheet[ $i ][ wp_unslash( $_POST['post_title'] ) ] );

				// The import screen can ask to remember this mapping for the next sheet with the same columns.
				if ( 2 === (int) $i && ! empty( $_POST['cexl_save_mapping'] ) && isset( $allDataInSheet[1] ) && class_exists( 'ContentExcelImporterMappings' ) ) {
					$used = array();
					foreach ( $_POST as $field => $column ) {
						if ( is_string( $column ) && preg_match( '/^[A-Z]{1,3}$/', $column ) ) {
							$used[ sanitize_key( $field ) ] = $column;
						}
					}
					ContentExcelImporterMappings::save( array_map( 'strval', $allDataInSheet[1] ), sanitize_key( $_POST['post_type'] ), $used );
				}

				if ( ! empty( $allDataInSheet[ $i ][ wp_unslash( $_POST['post_name'] ) ] ) ) {
					$url = sanitize_title_with_dashes( $allDataInSheet[ $i ][ $_POST['post_name'] ] );
				}

					$author = sanitize_text_field( $allDataInSheet[ $i ][ $_POST['post_author'] ] );
					$type   = sanitize_text_field( $_POST['post_type'] );

					$excerpt = wp_specialchars_decode( $allDataInSheet[ $i ][ $_POST['post_excerpt'] ], $quote_style = ENT_QUOTES );
					$excerpt = preg_replace( '#<script(.*?)>(.*?)</script>#is', '', $excerpt );

					$content = wp_specialchars_decode( $allDataInSheet[ $i ][ $_POST['post_content'] ], $quote_style = ENT_QUOTES );
					$content = preg_replace( '#<script(.*?)>(.*?)</script>#is', '', $content );

				if ( empty( $allDataInSheet[ $i ][ $_POST['post_date'] ] ) ) {
					$date = current_time( 'mysql' );
				} else {
					$date_string = $allDataInSheet[ $i ][ $_POST['post_date'] ];
					$date_stamp  = strtotime( $date_string );
					$date        = date( 'Y-m-d H:i:s', $date_stamp );
				}

						// Check if post exists: same title inside the chosen post type only (a page or image with that title is never touched).
				$existing_id = self::find_existing( $title, $type, $allDataInSheet[ $i ] );
				// An update in "only the columns in this sheet" mode leaves every unmapped field as it is.
				$keep_unmapped = self::only_mapped_columns() && 0 !== $existing_id && empty( $_POST['add_always_new'] );
				if ( 0 === $existing_id || ! empty( $_POST['add_always_new'] ) ) {
					$post = array(
						'post_author'  => $author,
						'post_date'    => $date,
						'post_title'   => $title,
						'post_name'    => $url,
						'post_content' => $content,
						'post_status'  => $status,
						'post_excerpt' => $excerpt,
						'post_type'    => $type,
					);

					// Create.
					$id = wp_insert_post( $post, $wp_error );
					print "<p class='success'><a href='" . esc_url( get_permalink( $id ) ) . "' target='_blank'>" . esc_html( $title ) . '</a> ' . esc_html__( 'created', 'content-excel-importer' ) . '.</p>';
					wp_set_object_terms( $id, 'simple', 'product_type' );

					if ( in_array( $id, $idsArray ) ) {
					} else {
						array_push( $idsArray, $id );
					}
				} else {
					// Update.
					$id = $existing_id;
					if ( in_array( $id, $idsArray ) ) {
					} else {
						array_push( $idsArray, $id );
					}

					if ( $content !== '' ) {

						$post = array(
							'ID'           => $id,
							'post_author'  => $author,
							'post_date'    => $date,
							'post_title'   => $title,
							'post_name'    => $url,
							'post_content' => $content,
							'post_status'  => $status,
							'post_excerpt' => $excerpt,
							'post_type'    => $type,
						);
					} else {

						$post = array(
							'ID'           => $id,
							'post_author'  => $author,
							'post_date'    => $date,
							'post_title'   => $title,
							'post_name'    => $url,
							'post_status'  => $status,
							'post_excerpt' => $excerpt,
							'post_type'    => $type,
						);

					}
					if ( $keep_unmapped ) {
						$post = self::keep_mapped_fields( $post, $allDataInSheet[ $i ] );
					}
					wp_update_post( $post, $wp_error );
					print "<p class='warning'><a href='" . esc_url( get_permalink( $id ) ) . "' target='_blank'>" . esc_html( $title ) . '</a> ' . esc_html__( 'already exists. Updated', 'content-excel-importer' ) . '.</p>';
				}

				if ( $type === 'product' ) {

					// SANITIZE AND VALIDATE meta data.

					$sale_price = sanitize_text_field( $allDataInSheet[ $i ][ wp_unslash( $_POST['_sale_price'] ) ] );
					if ( ! $sale_price && ! empty( $allDataInSheet[ $i ][ $_POST['_sale_price'] ] ) ) {
						$sale_price = '';
						if ( ! empty( $sale_price ) ) {
							print esc_html__( 'For sale price of', 'content-excel-importer' ) . ' ' . esc_html( $title ) . ' ' . esc_html__( 'you need numbers entered.', 'content-excel-importer' ) . '<br/>';
						}
					}
					$regular_price = sanitize_text_field( $allDataInSheet[ $i ][ wp_unslash( $_POST['_regular_price'] ) ] );
					if ( ! $regular_price && ! empty( $allDataInSheet[ $i ][ $_POST['_regular_price'] ] ) ) {
						$regular_price = '';
						if ( ! empty( $regular_price ) ) {
							print esc_html__( 'For regular price of', 'content-excel-importer' ) . ' ' . esc_html( $title ) . ' ' . esc_html__( 'you need numbers entered.', 'content-excel-importer' ) . '<br/>';
						}
					}
					$sku = sanitize_text_field( $allDataInSheet[ $i ][ wp_unslash( $_POST['_sku'] ) ] );
					if ( ! $sku && ! empty( $allDataInSheet[ $i ][ $_POST['_sku'] ] ) ) {
						$sku = '';
					}
					$weight = sanitize_text_field( $allDataInSheet[ $i ][ wp_unslash( $_POST['_weight'] ) ] );
					if ( ! $weight && ! empty( $allDataInSheet[ $i ][ $_POST['_weight'] ] ) ) {
						$weight = '';
						if ( ! empty( $weight ) ) {
							print esc_html__( 'For weight of', 'content-excel-importer' ) . ' ' . esc_html( $title ) . ' ' . esc_html__( 'you need numbers entered.', 'content-excel-importer' ) . '<br/>';
						}
					}
					$stock = sanitize_text_field( $allDataInSheet[ $i ][ wp_unslash( $_POST['_stock'] ) ] );
					if ( ! $stock && ! empty( $allDataInSheet[ $i ][ $_POST['_stock'] ] ) ) {
						$stock = '';
						if ( ! empty( $stock ) ) {
							print esc_html__( 'For stock of', 'content-excel-importer' ) . ' ' . esc_html( $title ) . ' ' . esc_html__( 'you need numbers entered.', 'content-excel-importer' ) . '<br/>';
						}
					}
					$varDescription = sanitize_text_field( $allDataInSheet[ $i ][ wp_unslash( $_POST['_variation_description'] ) ] );
					if ( ! $varDescription && ! empty( $_POST['_variation_description'] ) ) {
						$varDescription = '';
					}
					$length = (int) $allDataInSheet[ $i ][ wp_unslash( $_POST['_length'] ) ];
					if ( ! $length && ! empty( $allDataInSheet[ $i ][ $_POST['_length'] ] ) ) {
						$length = '';
						if ( ! empty( $weight ) ) {
							print esc_html__( 'For length of', 'content-excel-importer' ) . ' ' . esc_html( $title ) . ' ' . esc_html__( 'you need numbers entered.', 'content-excel-importer' ) . '<br/>';
						}
					}
					$width = sanitize_text_field( $allDataInSheet[ $i ][ wp_unslash( $_POST['_width'] ) ] );
					if ( ! $width && ! empty( $allDataInSheet[ $i ][ $_POST['_width'] ] ) ) {
						$width = '';
						if ( ! empty( $width ) ) {
							print esc_html__( 'For width of', 'content-excel-importer' ) . ' ' . esc_html( $title ) . ' ' . esc_html__( 'you need numbers entered.', 'content-excel-importer' ) . '<br/>';
						}
					}
					$height = sanitize_text_field( $allDataInSheet[ $i ][ wp_unslash( $_POST['_height'] ) ] );
					if ( ! $height && ! empty( $_POST['_height'] ) ) {
						$height = '';
					}
					$virtual = sanitize_text_field( $allDataInSheet[ $i ][ wp_unslash( $_POST['_virtual'] ) ] );
					if ( ! $virtual && ! empty( $_POST['_virtual'] ) ) {
						$virtual = '';
					}
				}
						$x          = 0;
						$attributes = array();
						$attrVal    = array();
						$concat     = array();

						$taxonomy_objects = get_object_taxonomies( $type, 'objects' );

				if ( 'product' === $type ) {

					if ( $sku !== '' ) {
						update_post_meta( $id, '_sku', $sku );
					}
					if ( $weight != '' ) {
						update_post_meta( $id, '_weight', $weight );
					}
					if ( $regular_price !== '' ) {
						update_post_meta( $id, '_regular_price', $regular_price );
					}
					if ( $sale_price !== '' ) {
						update_post_meta( $id, '_sale_price', $sale_price );
					}
					if ( $stock !== '' ) {
						update_post_meta( $id, '_stock', $stock );
					}
							update_post_meta( $id, '_visibility', 'visible' );
					if ( $sale_price !== '' ) {
						update_post_meta( $id, '_price', $sale_price );
					}
					if ( $stock !== '' ) {
						if ( $stock === '0' ) {
											update_post_meta( $id, '_stock_status', 'outofstock' );
						} else {
							update_post_meta( $id, '_stock_status', 'instock' );
						}
						update_post_meta( $id, '_manage_stock', 'yes' );

					} elseif ( ! $keep_unmapped ) {
						update_post_meta( $id, '_stock_status', 'instock' );
					}
					// The length is cast to a number, so a missing column writes 0; the "only my columns" mode skips it.
					if ( $length !== '' && ! ( $keep_unmapped && ( empty( $_POST['_length'] ) || '' === trim( (string) $allDataInSheet[ $i ][ $_POST['_length'] ] ) ) ) ) {
						update_post_meta( $id, '_length', $length );
					}
					if ( $width !== '' ) {
						update_post_meta( $id, '_width', $width );
					}
					if ( $height !== '' ) {
						update_post_meta( $id, '_height', $height );
					}
					if ( $virtual !== '' ) {
						update_post_meta( $id, '_virtual', $virtual );
					}
							wc_delete_product_transients( $id );
				}

				foreach ( $taxonomy_objects as $voc ) {

					if ( 'product_tag' === $voc->name || 'product_cat' === $voc->name || 'language' === $voc->name || 'post_translations' === $voc->name || 'category' === $voc->name || 'post_tag' === $voc->name ) {

						if ( isset( $_POST[ $voc->name ] ) ) {
							$taxToImport = explode( ',', sanitize_text_field( $allDataInSheet[ $i ][ wp_unslash( $_POST[ $voc->name ] ) ] ) );
						}

						foreach ( $taxToImport as $taxonomy ) {

							if ( isset( $_POST['language'] ) ) {
								$lang = sanitize_text_field( wp_unslash( $_POST['language'] ) );
							}

							if ( $voc->name === 'language' ) {
								if ( class_exists( 'Polylang' ) ) {
										global $polylang;
										$polylang->model->set_post_language( $id, $taxonomy );
								}
							}

							if ( 'post_translations' === $voc->name ) {

								if ( class_exists( 'Polylang' ) ) {
									global $polylang;

									$translateId   = post_exists( $taxonomy );
									$translateLang = pll_get_post_language( $translateId, 'slug' );
									$translations  = $polylang->model->post->get_translations( $translateId );

									$polylang->model->save_translations( $type, $id, array( $translateLang => $translateId ) );
								}

								wp_set_object_terms( $id, post_exists( $taxonomy ), 'post_translations', true );

							} else {

								wp_set_object_terms( $id, $taxonomy, $voc->name, true );

							}

								// GET ALL ASSIGNED TERMS AND ADD PARENT FOR PRODUCT_CAT TAXONOMY!!!
								$terms = wp_get_post_terms( $id, $voc->name );
							foreach ( $terms as $term ) {
								while ( $term->parent !== 0 && ! has_term( $term->parent, $voc->name, $post ) ) {
									// move upward until we get to 0 level terms.
									wp_set_object_terms( $id, array( $term->parent ), $voc->name, true );
									$term = get_term( $term->parent, $voc->name );
								}
							}
						}
							$categories = wp_get_object_terms( $id, 'category' );
						if ( count( $categories ) > 1 ) {
									wp_remove_object_terms( $id, 'uncategorized', 'category' );
						}
					}
				}// End for each taxonomy.

				if ( $type === 'product' ) {
					$product = wc_get_product( $id );
					$product->set_stock_quantity( get_post_meta( $id, '_stock', true ) );
					if ( ! $keep_unmapped || '' !== (string) $stock ) {
						$product->set_stock_status( 'instock' );
						if ( get_post_meta( $id, '_stock', true ) === '0' ) {
							$product->set_stock_status( 'outofstock' );
						}
					}
					$product->set_price( get_post_meta( $id, '_price', true ) );
					// The prices were written straight to the database above, so WooCommerce would not see them change. Write them
					// again through WooCommerce so that it applies its own rules on save: a sale price at or above the regular price
					// is dropped, the shop price follows, the lookup table is refreshed (5.0.4 got this by overwriting the sale price).
					$no_sale_price = '' === (string) get_post_meta( $id, '_sale_price', true );
					foreach ( array( '_regular_price' => 'set_regular_price', '_sale_price' => 'set_sale_price' ) as $price_key => $price_setter ) {
						$price_value = get_post_meta( $id, $price_key, true );
						if ( '' !== (string) $price_value ) {
							delete_post_meta( $id, $price_key );
							$product->{$price_setter}( '' );
							$product->{$price_setter}( $price_value );
						}
					}
					if ( $no_sale_price && '' !== (string) $product->get_regular_price( 'edit' ) ) {
						update_post_meta( $id, '_sale_price', '' );
					}
					$product->save();
				}

				if ( isset( $_POST['finalupload'] ) ) {
					$finalUpload = sanitize_text_field( wp_unslash( $_POST['finalupload'] ) );
				}

				if ( $i == $_POST['finalupload'] ) {

					print "<div class='importMessageSussess'><h2>" . esc_html( $i ) . ' / ' . esc_html( $finalUpload ) . esc_html__( ' - Job Done! ', 'content-excel-importer' ) . "<a href='" . esc_url( admin_url( 'edit.php?post_type=' . esc_attr( $type ) ) ) . "' target='_blank'><i class='fa fa-eye'></i> " . esc_html__( 'GO VIEW YOUR ', 'content-excel-importer' ) . ' ' . esc_html( $type ) . 's!</a></h2></div>';

					wp_delete_file( $filename );
					wp_delete_file( $sheet_cache );

				} else {

					print "<div class='importMessage'><h2>" . esc_html( $i ) . ' / ' . esc_html( $finalUpload ) . ' ' . esc_html__( 'Please dont close this page. Your ', 'content-excel-importer' ) . ' ' . esc_html( $type ) . 's ' . esc_html__( 'are imported...', 'content-excel-importer' ) . "</h2>
							<p><img  src='" . esc_url( plugins_url( 'images/loading.gif', __FILE__ ) ) . "' /></p>
							</div>";
				}

					die;

			} else {
				print "<p class = 'warning' style = 'color:red' >" . esc_html__( 'No title selected for your content.', 'content-excel-importer' ) . '</p>';
			}

			$time_end = microtime( true );
			// Dividing with 60 will give the execution time in minutes other wise seconds.
			$execution_time = ( $time_end - $time_start ) / 60;
			// Execution time of the script.
			echo '<b>' . esc_html__( 'Total Execution Time:', 'content-excel-importer' ) . '</b> ' . esc_html( $execution_time ) . esc_html__( 'Mins', 'content-excel-importer' ) . '</b> ';

		}
	}
}

$products = new ContentExcelImporterProducts();
