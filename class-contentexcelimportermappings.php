<?php
/**
 * Import Content in WordPress & WooCommerce with Excel - column matching: saved mappings and automatic matching.
 *
 * The import screen asks for a suggestion when a sheet is uploaded: the saved mapping of the same sheet layout (same
 * header row and post type) if there is one, else a match of each header against field names, labels and common
 * synonyms, ignoring case, spaces, dashes and underscores. Saved when an import starts with cexl_save_mapping = 1.
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Saved mappings and automatic column matching.
 */
class ContentExcelImporterMappings {

	/**
	 * Option that holds the saved mappings (not autoloaded).
	 */
	const OPTION = 'cexl_saved_mappings';

	/**
	 * Most saved layouts kept (oldest dropped first).
	 */
	const KEEP = 50;

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_cexl_suggest_mapping', array( $this, 'ajax_suggest' ) );
	}

	/**
	 * Words merchants use for each field (lower case, without spaces, dashes or underscores).
	 *
	 * @return array field => list of synonyms
	 */
	public static function synonyms() {
		$list = array(
			'post_title'     => array( 'title', 'name', 'productname', 'producttitle', 'posttitle', 'artikel', 'bezeichnung', 'nom', 'nombre', 'titel' ),
			'post_content'   => array( 'content', 'description', 'longdescription', 'body', 'text', 'beschreibung', 'descripcion' ),
			'post_excerpt'   => array( 'excerpt', 'shortdescription', 'summary', 'kurzbeschreibung', 'short' ),
			'post_status'    => array( 'status', 'poststatus', 'published' ),
			'post_date'      => array( 'date', 'postdate', 'publishdate', 'datum' ),
			'post_name'      => array( 'slug', 'url', 'urlslug', 'permalink', 'postname' ),
			'post_author'    => array( 'author', 'authorid', 'postauthor' ),
			'_sku'           => array( 'sku', 'artnr', 'articlenumber', 'itemnumber', 'productcode', 'code', 'ref', 'reference', 'artikelnummer', 'partnumber' ),
			'_regular_price' => array( 'regularprice', 'price', 'listprice', 'retailprice', 'rrp', 'msrp', 'vk', 'vkbrutto', 'preis', 'precio', 'prix' ),
			'_sale_price'    => array( 'saleprice', 'specialprice', 'offerprice', 'discountprice', 'angebotspreis', 'promo' ),
			'_stock'         => array( 'stock', 'qty', 'quantity', 'stockquantity', 'inventory', 'lager', 'bestand', 'onhand', 'available' ),
			'_weight'        => array( 'weight', 'gewicht', 'peso', 'poids' ),
			'_virtual'       => array( 'virtual' ),
			'product_cat'    => array( 'category', 'categories', 'productcategory', 'productcat', 'kategorie', 'categoria' ),
			'product_tag'    => array( 'tag', 'tags', 'producttag', 'keywords' ),
			'category'       => array( 'category', 'categories' ),
			'post_tag'       => array( 'tag', 'tags' ),
		);
		/**
		 * Synonyms used to match sheet headers to fields.
		 *
		 * @param array $list field => list of lower-case synonyms without spaces, dashes or underscores.
		 */
		return apply_filters( 'cexl_mapping_synonyms', $list );
	}

	/**
	 * Header text reduced for comparison.
	 *
	 * @param string $text Header or label.
	 * @return string
	 */
	public static function normalize( $text ) {
		$text = strtolower( remove_accents( wp_strip_all_tags( (string) $text ) ) );
		return preg_replace( '/[^a-z0-9]/', '', $text );
	}

	/**
	 * Fingerprint of a sheet layout: post type plus the normalized header row.
	 *
	 * @param array  $headers Column letter => header text.
	 * @param string $type    Post type.
	 * @return string
	 */
	public static function fingerprint( $headers, $type ) {
		return md5( $type . '|' . implode( '|', array_map( array( __CLASS__, 'normalize' ), $headers ) ) );
	}

	/**
	 * Suggested mapping for a header row.
	 *
	 * @param array  $headers Column letter => header text.
	 * @param string $type    Post type.
	 * @param array  $fields  Field keys offered on the mapping screen (and their labels: key => label).
	 * @return array { source: saved|auto, map: field => column letter, unmatched: list of column letters }
	 */
	public static function suggest( $headers, $type, $fields ) {
		$saved = get_option( self::OPTION, array() );
		$print = self::fingerprint( $headers, $type );
		if ( isset( $saved[ $print ]['map'] ) ) {
			$map = array_intersect_key( $saved[ $print ]['map'], $fields );
			return array( 'source' => 'saved', 'map' => $map, 'unmatched' => array_values( array_diff( array_keys( $headers ), $map ) ) );
		}
		$syn = self::synonyms();
		$map = array();
		foreach ( $headers as $letter => $header ) {
			$h = self::normalize( $header );
			if ( '' === $h ) {
				continue;
			}
			foreach ( $fields as $key => $label ) {
				if ( isset( $map[ $key ] ) ) {
					continue;
				}
				$names = array_merge( array( self::normalize( $key ), self::normalize( $label ) ), isset( $syn[ $key ] ) ? $syn[ $key ] : array() );
				if ( in_array( $h, $names, true ) ) {
					$map[ $key ] = $letter;
					break;
				}
			}
		}
		return array( 'source' => 'auto', 'map' => $map, 'unmatched' => array_values( array_diff( array_keys( $headers ), $map ) ) );
	}

	/**
	 * Saves the mapping used for a sheet layout.
	 *
	 * @param array  $headers Column letter => header text.
	 * @param string $type    Post type.
	 * @param array  $map     Field => column letter.
	 */
	public static function save( $headers, $type, $map ) {
		$map   = array_filter( array_map( 'sanitize_text_field', (array) $map ), 'strlen' );
		$saved = get_option( self::OPTION, array() );
		$print = self::fingerprint( $headers, $type );
		unset( $saved[ $print ] );
		$saved[ $print ] = array(
			'type'    => $type,
			'headers' => array_values( array_map( 'sanitize_text_field', $headers ) ),
			'map'     => $map,
			'saved'   => time(),
		);
		$saved = array_slice( $saved, -self::KEEP, null, true );
		update_option( self::OPTION, $saved, false );
	}

	/**
	 * AJAX: suggestion for the uploaded sheet (headers from the JSON copy of the upload).
	 */
	public function ajax_suggest() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to import.', 'content-excel-importer' ) ), 403 );
		}
		check_ajax_referer( 'excel_process', 'secNonce' );
		$token  = isset( $_POST['cexl_file'] ) ? sanitize_text_field( wp_unslash( $_POST['cexl_file'] ) ) : '';
		$type   = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$fields = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['fields'] ) ) : array();
		$rows   = ContentExcelImporterPreview::rows( $token );
		if ( null === $rows || ! isset( $rows[1] ) ) {
			wp_send_json_error( array( 'message' => __( 'The uploaded file was not found. Please upload it again.', 'content-excel-importer' ) ), 404 );
		}
		wp_send_json_success( self::suggest( array_map( 'strval', $rows[1] ), $type, $fields ) );
	}
}

new ContentExcelImporterMappings();
