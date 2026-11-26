<?php
/**
 * Excel Importer for WooCommerce - dry run (preview of an import, nothing is saved).
 *
 * Takes the same request as one import row (mapping, post type, file token, match and update options) and returns,
 * for every row, what the import will do: create, update or skip, the fields that change (old and new value) and
 * warnings. Follows the same rules as ContentExcelImporterProducts::import_content().
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dry run of an import.
 */
class ContentExcelImporterPreview {

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_cexl_preview', array( $this, 'ajax' ) );
	}

	/**
	 * AJAX: JSON preview of the whole sheet.
	 */
	public function ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to import.', 'content-excel-importer' ) ), 403 );
		}
		check_ajax_referer( 'excel_process', 'secNonce' );
		$token = isset( $_POST['cexl_file'] ) ? sanitize_text_field( wp_unslash( $_POST['cexl_file'] ) ) : '';
		$rows  = self::rows( $token );
		if ( null === $rows ) {
			wp_send_json_error( array( 'message' => __( 'The uploaded file was not found. Please upload it again.', 'content-excel-importer' ) ), 404 );
		}
		wp_send_json_success( self::preview( $rows, wp_unslash( $_POST ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is sanitized where it is used.
	}

	/**
	 * Rows of an uploaded sheet (the JSON copy made at upload), keyed by row number then column letter.
	 *
	 * @param string $token File token.
	 * @return array|null
	 */
	public static function rows( $token ) {
		$file = ContentExcelImporterProducts::import_file( $token );
		if ( '' === $file ) {
			return null;
		}
		$cache = substr( $file, 0, -5 ) . '.json';
		$rows  = file_exists( $cache ) ? json_decode( (string) file_get_contents( $cache ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $rows ) && file_exists( $file ) ) {
			$rows = \PhpOffice\PhpSpreadsheet\IOFactory::load( $file, 0, array( 'Xlsx', 'Xls', 'Ods', 'Csv' ) )->getActiveSheet()->toArray( null, true, true, true );
		}
		return is_array( $rows ) ? $rows : null;
	}

	/**
	 * The preview.
	 *
	 * @param array $rows    Sheet rows (row 1 = headers).
	 * @param array $request Mapping and options, as the import screen sends them (unslashed).
	 * @return array { rows: list, counts: create/update/skip/warnings }
	 */
	public static function preview( $rows, $request ) {
		$type      = isset( $request['post_type'] ) ? sanitize_key( $request['post_type'] ) : '';
		$col       = function ( $field ) use ( $request ) {
			return isset( $request[ $field ] ) ? sanitize_text_field( $request[ $field ] ) : '';
		};
		$always    = ! empty( $request['add_always_new'] );
		$mapped    = isset( $request['cexl_update'] ) && 'mapped' === $request['cexl_update'];
		$is_prod   = 'product' === $type;
		$out       = array();
		$counts    = array( 'create' => 0, 'update' => 0, 'skip' => 0, 'warnings' => 0 );
		$seen_keys = array();
		$created   = array(); // key => row number of a row that creates a new item

		// The import reads $_POST for the match options; the preview passes the same request.
		$saved_post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST      = array_merge( $_POST, $request ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$total = count( $rows );
		for ( $i = 2; $i <= $total; $i++ ) {
			$row = isset( $rows[ $i ] ) && is_array( $rows[ $i ] ) ? $rows[ $i ] : array();
			$get = function ( $field ) use ( $row, $col ) {
				$c = $col( $field );
				return ( '' !== $c && isset( $row[ $c ] ) ) ? (string) $row[ $c ] : '';
			};
			if ( '' === $col( 'post_title' ) ) {
				break;
			}
			$title    = sanitize_text_field( $get( 'post_title' ) );
			$warnings = array();
			$existing = $always ? 0 : ContentExcelImporterProducts::find_existing( $title, $type, $row );
			$action   = $existing ? 'update' : 'create';
			$changes  = array();

			// Duplicate keys inside the sheet: the second row updates what the first one created or changed.
			$key = strtolower( $title );
			if ( isset( $request['cexl_match'] ) && in_array( $request['cexl_match'], array( 'sku', 'id' ), true ) ) {
				$kc  = $col( 'cexl_match_column' );
				$key = strtolower( trim( ( '' !== $kc && isset( $row[ $kc ] ) ) ? (string) $row[ $kc ] : '' ) );
			}
			if ( '' !== $key && isset( $seen_keys[ $key ] ) ) {
				/* translators: %d: row number */
				$warnings[] = sprintf( __( 'Same item as row %d: this row changes it again.', 'content-excel-importer' ), $seen_keys[ $key ] );
				if ( ! $existing && ! $always && isset( $created[ $key ] ) ) {
					$action = 'update'; // An earlier row of this sheet creates the item; this row updates it.
				}
			} elseif ( '' !== $key ) {
				$seen_keys[ $key ] = $i;
				if ( ! $existing ) {
					$created[ $key ] = $i;
				}
			}
			if ( '' === $title ) {
				$warnings[] = __( 'This row has no title.', 'content-excel-importer' );
			}
			// "Skip the row" when nothing matches: the import leaves the row out.
			if ( 'create' === $action && ! $always && isset( $request['cexl_missing'] ) && 'skip' === $request['cexl_missing'] ) {
				if ( isset( $created[ $key ] ) && $created[ $key ] === $i ) {
					unset( $created[ $key ] ); // Nothing is created, so a later row with the same key is skipped too.
				}
				$counts['skip']++;
				$out[] = array(
					'row'      => $i,
					'title'    => $title,
					'id'       => 0,
					'action'   => 'skip',
					'changes'  => array(),
					'warnings' => array(),
					'note'     => __( 'Not found, so this row is skipped.', 'content-excel-importer' ),
				);
				continue;
			}
			if ( ! $existing && '' !== $title && ! $always ) {
				$other = (int) post_exists( $title );
				if ( $other && get_post_type( $other ) !== ( '' === $type ? 'post' : $type ) ) {
					/* translators: %s: post type label, for example "Page" */
					$warnings[] = sprintf( __( 'A %s has the same title; it is not touched, a new item is created.', 'content-excel-importer' ), get_post_type( $other ) );
				}
			}

			// Post fields.
			$current = $existing ? get_post( $existing ) : null;
			$fields  = array(
				'post_title'   => array( __( 'Title', 'content-excel-importer' ), $title ),
				'post_content' => array( __( 'Description', 'content-excel-importer' ), preg_replace( '#<script(.*?)>(.*?)</script>#is', '', wp_specialchars_decode( $get( 'post_content' ), ENT_QUOTES ) ) ),
				'post_excerpt' => array( __( 'Short description', 'content-excel-importer' ), preg_replace( '#<script(.*?)>(.*?)</script>#is', '', wp_specialchars_decode( $get( 'post_excerpt' ), ENT_QUOTES ) ) ),
				'post_status'  => array( __( 'Status', 'content-excel-importer' ), '' !== $col( 'post_status' ) ? sanitize_text_field( $get( 'post_status' ) ) : 'publish' ),
				'post_date'    => array( __( 'Date', 'content-excel-importer' ), '' !== $get( 'post_date' ) ? gmdate( 'Y-m-d H:i:s', (int) strtotime( $get( 'post_date' ) ) ) : 'now' ),
				'post_name'    => array( __( 'URL slug', 'content-excel-importer' ), '' !== $get( 'post_name' ) ? sanitize_title_with_dashes( $get( 'post_name' ) ) : '' ),
				'post_author'  => array( __( 'Author', 'content-excel-importer' ), sanitize_text_field( $get( 'post_author' ) ) ),
			);
			foreach ( $fields as $field => $label_value ) {
				list( $label, $new ) = $label_value;
				$has_value = '' !== trim( $get( $field ) );
				if ( $existing ) {
					if ( $mapped && ! $has_value ) {
						continue; // "Only the columns in this sheet": unmapped or empty cells keep the current value.
					}
					if ( ! $mapped && in_array( $field, array( 'post_content', 'post_name' ), true ) && ! $has_value ) {
						continue; // Written only when the cell has a value, as in import_content().
					}
					if ( ! $mapped && 'post_author' === $field && ! $has_value ) {
						$new = '0'; // The old mode writes the empty author column, which WordPress stores as 0.
					}
				} elseif ( '' === $new || ( 'post_status' === $field && ! $has_value ) || ( 'post_date' === $field && ! $has_value ) ) {
					continue; // New items: show what the sheet sets.
				}
				$old = $current ? (string) $current->$field : '';
				if ( 'post_date' === $field && 'now' === $new ) {
					$changes[] = array( 'field' => $field, 'label' => $label, 'old' => $old, 'new' => __( 'time of the import', 'content-excel-importer' ) );
					continue;
				}
				if ( ! $existing || (string) $new !== $old ) {
					$changes[] = array( 'field' => $field, 'label' => $label, 'old' => $existing ? $old : '', 'new' => (string) $new );
				}
			}

			// Publishing an item that has no URL slug yet makes WordPress create one from the title.
			if ( $current && '' === (string) $current->post_name && '' === trim( $get( 'post_name' ) ) ) {
				$new_status = $current->post_status;
				foreach ( $changes as $change ) {
					if ( 'post_status' === $change['field'] ) {
						$new_status = $change['new'];
					}
				}
				if ( ! in_array( $new_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
					$changes[] = array(
						'field' => 'post_name',
						'label' => __( 'URL slug', 'content-excel-importer' ),
						'old'   => '',
						'new'   => wp_unique_post_slug( sanitize_title( $title ), $existing, $new_status, $current->post_type, $current->post_parent ),
					);
				}
			}

			// Product data.
			if ( $is_prod ) {
				$meta = array(
					'_sku'     => __( 'SKU', 'content-excel-importer' ),
					'_weight'  => __( 'Weight', 'content-excel-importer' ),
					'_stock'   => __( 'Stock quantity', 'content-excel-importer' ),
					'_virtual' => __( 'Virtual', 'content-excel-importer' ),
				);
				foreach ( $meta as $key_name => $label ) {
					$new = sanitize_text_field( $get( $key_name ) );
					if ( '' === $new ) {
						continue;
					}
					$old = $existing ? (string) get_post_meta( $existing, $key_name, true ) : '';
					if ( $new !== $old ) {
						$changes[] = array( 'field' => $key_name, 'label' => $label, 'old' => $old, 'new' => $new );
					}
				}
				// 5.0.4 casts the length to a number: a missing or empty length column writes 0 (kept in the old mode).
				$length_cell = trim( $get( '_length' ) );
				if ( ! ( $mapped && $existing && '' === $length_cell ) ) {
					$new_length = (string) (int) $length_cell;
					$old_length = $existing ? (string) get_post_meta( $existing, '_length', true ) : '';
					if ( $new_length !== $old_length ) {
						$changes[] = array( 'field' => '_length', 'label' => __( 'Length', 'content-excel-importer' ), 'old' => $old_length, 'new' => $new_length );
					}
				}
				// Prices with WooCommerce's rule: a sale price at or above the regular price is dropped.
				$old_regular = $existing ? (string) get_post_meta( $existing, '_regular_price', true ) : '';
				$old_sale    = $existing ? (string) get_post_meta( $existing, '_sale_price', true ) : '';
				$regular     = '' !== sanitize_text_field( $get( '_regular_price' ) ) ? sanitize_text_field( $get( '_regular_price' ) ) : $old_regular;
				$sale        = '' !== sanitize_text_field( $get( '_sale_price' ) ) ? sanitize_text_field( $get( '_sale_price' ) ) : $old_sale;
				foreach ( array( $regular, $sale ) as $price ) {
					if ( '' !== $price && ! is_numeric( $price ) ) {
						/* translators: %s: the price as written in the sheet */
						$warnings[] = sprintf( __( 'Price "%s" is not a plain number.', 'content-excel-importer' ), $price );
					}
				}
				if ( '' !== $sale && '' !== $regular && is_numeric( $sale ) && is_numeric( $regular ) && (float) $sale >= (float) $regular ) {
					/* translators: 1: sale price, 2: regular price */
					$warnings[] = sprintf( __( 'Sale price %1$s is not below the regular price %2$s, so it is dropped.', 'content-excel-importer' ), $sale, $regular );
					$sale       = '';
				}
				if ( $regular !== $old_regular ) {
					$changes[] = array( 'field' => '_regular_price', 'label' => __( 'Regular price', 'content-excel-importer' ), 'old' => $old_regular, 'new' => $regular );
				}
				if ( $sale !== $old_sale ) {
					$changes[] = array( 'field' => '_sale_price', 'label' => __( 'Sale price', 'content-excel-importer' ), 'old' => $old_sale, 'new' => $sale );
				}
				// Stock status.
				$stock      = sanitize_text_field( $get( '_stock' ) );
				$old_status = $existing ? (string) get_post_meta( $existing, '_stock_status', true ) : '';
				$new_status = '' !== $stock ? ( '0' === $stock ? 'outofstock' : 'instock' ) : ( ( $existing && $mapped ) ? $old_status : 'instock' );
				if ( $existing && $new_status !== $old_status ) {
					$changes[] = array( 'field' => '_stock_status', 'label' => __( 'Stock status', 'content-excel-importer' ), 'old' => $old_status, 'new' => $new_status );
				}
			}

			// Categories and tags are added to the ones the item already has.
			foreach ( get_object_taxonomies( '' === $type ? 'post' : $type, 'objects' ) as $tax ) {
				if ( ! in_array( $tax->name, array( 'product_cat', 'product_tag', 'category', 'post_tag' ), true ) ) {
					continue;
				}
				$names = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( $get( $tax->name ) ) ) ), 'strlen' );
				if ( ! $names ) {
					continue;
				}
				$has = $existing ? wp_get_object_terms( $existing, $tax->name, array( 'fields' => 'names' ) ) : array();
				$add = array_values( array_diff( $names, is_array( $has ) ? $has : array() ) );
				$final = array_merge( (array) $has, $add );
				if ( 'category' === $tax->name && count( $final ) > 1 ) {
					$default = get_term_by( 'slug', 'uncategorized', 'category' );
					$final   = $default ? array_values( array_diff( $final, array( $default->name ) ) ) : $final;
				}
				if ( $add ) {
					$changes[] = array( 'field' => $tax->name, 'label' => $tax->labels->name, 'old' => implode( ', ', (array) $has ), 'new' => implode( ', ', $final ) );
				}
			}

			if ( $existing && ! $changes ) {
				$action = 'same';
			}
			$counts[ 'same' === $action ? 'skip' : $action ]++;
			$counts['warnings'] += $warnings ? 1 : 0;
			$out[]               = array(
				'row'      => $i,
				'title'    => $title,
				'id'       => $existing,
				'action'   => $action,
				'changes'  => $changes,
				'warnings' => $warnings,
			);
		}
		$_POST = $saved_post; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return array( 'rows' => $out, 'counts' => $counts );
	}
}

new ContentExcelImporterPreview();
