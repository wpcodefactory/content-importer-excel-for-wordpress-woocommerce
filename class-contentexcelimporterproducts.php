<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
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
		add_action( 'wp_ajax_import_content', array( $this, 'import_content' ) );
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
						print "<input type='hidden' name='finalupload' value='" . esc_attr( $total ) . "' />
						   <input type='hidden' name='import' value='import' />
						   <input type='hidden' name='start' value='2' />
						   <input type='hidden' name='action' value='import_content' />";
							wp_nonce_field( 'excel_process', 'secNonce' );

							submit_button( 'Upload', 'primary', 'check' );
							print '</div>';
						print '</form></div>';

						if ( isset( $_FILES['file']['tmp_name'] ) ) move_uploaded_file( $_FILES['file']['tmp_name'], plugin_dir_path( __FILE__ ) . 'import.xlsx' );

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
			$filename = plugin_dir_path( __FILE__ ) . 'import.xlsx';

			$objPHPExcel      = IOFactory::load( $filename );
			$allDataInSheet   = $objPHPExcel->getActiveSheet()->toArray( null, true, true, true );
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

						// Check if post exists.
				if ( post_exists( $title ) === 0 || ! empty( $_POST['add_always_new'] ) ) {
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
					$id = post_exists( $title );
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

					} else {
						update_post_meta( $id, '_stock_status', 'instock' );
					}
					if ( $length !== '' ) {
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
					$product->set_stock_status( 'instock' );
					if ( get_post_meta( $id, '_stock', true ) === '0' ) {
						$product->set_stock_status( 'outofstock' );
					}
					$product->set_price( get_post_meta( $id, '_price', true ) );
					$product->set_sale_price( get_post_meta( $id, '_sale_price', true ) );
					$product->set_sale_price( get_post_meta( $id, '_regular_price', true ) );
					$product->save();
				}

				if ( isset( $_POST['finalupload'] ) ) {
					$finalUpload = sanitize_text_field( wp_unslash( $_POST['finalupload'] ) );
				}

				if ( $i == $_POST['finalupload'] ) {

					print "<div class='importMessageSussess'><h2>" . esc_html( $i ) . ' / ' . esc_html( $finalUpload ) . esc_html__( ' - Job Done! ', 'content-excel-importer' ) . "<a href='" . esc_url( admin_url( 'edit.php?post_type=' . esc_attr( $type ) ) ) . "' target='_blank'><i class='fa fa-eye'></i> " . esc_html__( 'GO VIEW YOUR ', 'content-excel-importer' ) . ' ' . esc_html( $type ) . 's!</a></h2></div>';

					wp_delete_file( $filename );

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
