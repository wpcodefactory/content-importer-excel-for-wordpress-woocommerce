<?php
/** This file contains the class that handles queries for the import process. **/

class ContentExcelImporterQuery {

	/** This class handles handles queries for the import process. **/
	public function selectPostType() {

		/** This method provides the post types availabe to display in postr type form. */

			?>

			<select  name="contentExcelImporter_post_type" id="contentExcelImporter_post_type"   value = "" >
			<option value=''><?php esc_html_e( 'Select', 'content-excel-importer' ); ?></option>

			<?php
			if ( isset( $_POST['contentExcelImporter_post_type'] ) ) {

				$the_post_type = sanitize_text_field( wp_unslash( $_POST['contentExcelImporterPro_post_type'] ) );

				?>
				<option value='<?php print esc_attr( $the_post_type ); ?>'><?php print esc_attr( $the_post_type ); ?></option>
				<?php
			}

			$postType = array( 'post', 'page', 'product' );

			foreach ( get_post_types( '', 'names' ) as $post_type ) {

				if ( in_array( $post_type, $postType ) ) {
					echo "<option value='" . esc_attr( $post_type ) . "'>" . esc_attr( $post_type ) . '</option>';
				}
			}
			?>
			</select>
			<?php


	}

	public function selectPostTypeForm() {

		/** This method provides the html for the import display form. */

		if ( isset( $_GET['tab'] ) && 'main' === $_GET['tab'] || empty( $_GET['tab'] ) ) {

			print "<form id='selectPostType' action= '" . esc_url( admin_url( 'admin.php?page=content-excel-importer' ) ) . "' method='POST'>";

		} else {
			print "<form id='selectPostType' action= '" . esc_url( admin_url( 'admin.php?page=content-excel-importer-pro' ) ) . "' method='POST'>";
		}
		?>

		<label><?php esc_html_e( 'SELECT POST TYPE', 'content-excel-importer' ); ?></label>

		<?php $this->selectPostType(); ?>

		<input type='hidden' name='getPostType' value='1'  />
		<?php wp_nonce_field( 'getPostType', 'getPostType' ); ?>

		</form>
		<?php
	}

	public function getFields( $post_type ) {

		/** This method detects any custom fields for post type to mapped with excel fields. */

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && current_user_can( 'manage_options' ) ) {

			print '<p><b>' . esc_html__( 'POST TYPE ', 'content-excel-importer' ) . "</b> <input type='text' name='post_type' id='post_type_insert' required readonly  value='" . esc_attr( $post_type ) . "' /></p>";

			$data = array( 'post_title', 'post_author', 'post_date', 'post_name', 'post_status', 'post_content', 'post_excerpt', 'image', '_virtual' );

			$pro = array( 'image_gallery', '_downloadable', 'download_name', 'download_file', '_download_limit', '_download_expiry' );

			foreach ( $data as $d ) {

				if ( 'post_name' === $d ) {

					print "<p><b>url </b> <input type='text' name='" . esc_attr( $d ) . "'  readonly class='droppable' placeholder='Drop here column' /></p>";

				} elseif ( 'image' === $d ) {

					echo "<p class='proVersion' >" . esc_html__( 'IMAGE', 'content-excel-importer' ) . " <input style='border:1px solid red;background:#ccc;' type='text' style='min-width:200px' name='image' required readonly class='' placeholder='" . esc_html__( 'PRO Version Only', 'content-excel-importer' ) . "'  /></p>";

				} else {
					print '<p><b>' . esc_html( $d ) . " </b> <input type='text' name='" . esc_attr( $d ) . "'  readonly class='droppable' placeholder='Drop here column' /></p>";
				}
			}

			if ( 'product' === $post_type ) {

				foreach ( $pro as $d ) {

					print "<p  class='proVersion' ><b>" . esc_html( $d ) . " </b> <input  style='border:1px solid red;background:#ccc;' type='text' style='min-width:200px' name='image' required readonly placeholder='" . esc_html__( 'PRO Version Only', 'content-excel-importer' ) . "' /></p>";

				}
			}

			if ( 'post' === $post_type ) {

				$taxonomy_objects = get_object_taxonomies( 'post', 'objects' );

				foreach ( $taxonomy_objects as $voc ) {

					// ADDITION : INCLUDE ONLY PRODUCT CATEGORY AND TAGS NOT CUSTOM TAXONOMIES.

					if ( 'post_tag' === $voc->name || 'category' === $voc->name || 'language' === $voc->name || 'post_translations' === $voc->name ) {

						$vocc = strtoupper( str_replace( '_', ' ', $voc->name ) );

						echo '<p>' . esc_html( $vocc ) . " <input type='text' style='min-width:200px' name='" . esc_attr( $voc->name ) . "' required readonly class='droppable' placeholder='" . esc_html__( 'Drop here column', 'content-excel-importer' ) . "' key /></p>";
					}
				}
			}

			if ( 'page' === $post_type ) {

				$taxonomy_objects = get_object_taxonomies( 'page', 'objects' );

				foreach ( $taxonomy_objects as $voc ) {

					// ADDITION : INCLUDE ONLY PRODUCT CATEGORY AND TAGS NOT CUSTOM TAXONOMIES.

					if ( 'language' === $voc->name || 'post_translations' === $voc->name ) {

						$vocc = strtoupper( str_replace( '_', ' ', $voc->name ) );

						echo '<p>' . esc_html( $vocc ) . " <input type='text' style='min-width:200px' name='" . esc_attr( $voc->name ) . "' required readonly class='droppable' placeholder='" . esc_html__( 'Drop here column', 'content-excel-importer' ) . "' key /></p>";
					}
				}
			}

			if ( 'product' === $post_type ) {

				$post_meta = array( '_sku', '_weight', '_regular_price', '_sale_price', '_stock' );

				foreach ( $post_meta as $meta ) {

					$metaa = strtoupper( str_replace( '_', ' ', $meta ) );

					echo '<p>' . esc_html( $metaa ) . " <input type='text' style='min-width:200px' name='" . esc_attr( $meta ) . "' required readonly class='droppable' placeholder='" . esc_html__( 'Drop here column', 'content-excel-importer' ) . "'  /></p>";
				}

				print '<h3>' . esc_html__( 'CATEGORY AND TAGS', 'content-excel-importer' ) . '</h3>';

				$taxonomy_objects = get_object_taxonomies( 'product', 'objects' );

				foreach ( $taxonomy_objects as $voc ) {

					// ADDITION : INCLUDE ONLY PRODUCT CATEGORY AND TAGS NOT CUSTOM TAXONOMIES.

					if ( 'product_tag' === $voc->name || 'product_cat' === $voc->name ) {

						$vocc = strtoupper( str_replace( '_', ' ', $voc->name ) );

						echo '<p>' . esc_html( $vocc ) . " <input type='text' style='min-width:200px' name='" . esc_attr( $voc->name ) . "' required readonly class='droppable' placeholder='" . esc_html__( 'Drop here column', 'content-excel-importer' ) . "' key /></p>";
					}
				}
			}

			echo '<p>' . esc_html__( 'Custom Taxonomy', 'content-excel-importer' ) . " <input type='text' name='custom_tax' style='border:1px solid red;background:#ccc;' readonly  placeholder='" . esc_html__( 'PRO Version Only', 'content-excel-importer' ) . "'  /></p>";

		}
	}
}