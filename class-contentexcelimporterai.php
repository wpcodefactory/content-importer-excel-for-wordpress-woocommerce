<?php
/**
 * Excel Importer for WooCommerce - AI on WPFactory AI credits: match columns with AI.
 *
 * Nothing is sent until an administrator connects the site on WPFactory > AI Credits. A match sends the column names,
 * at most three sample rows (cells cut to 80 characters, never the cells of columns that look personal) and the list of
 * fields. The answer only fills the matching screen; the merchant checks it and nothing is saved until the import.
 * Tests and own providers can answer through the cexl_ai_complete filter.
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

/**
 * AI features.
 */
class ContentExcelImporterAI {

	/**
	 * Product id on the WPFactory AI server.
	 */
	const PRODUCT = 'content-importer';

	/**
	 * Hooks.
	 *
	 * @param string        $capability Capability that may use the importer.
	 * @param string        $suffix     AJAX action suffix ('' in free, '_pro' in Pro).
	 * @param callable|null $fields_for Fields of a post type, in groups (the edition's screen class).
	 * @param callable|null $rows_for   Rows of an uploaded sheet by token.
	 */
	public function __construct( $capability = 'manage_options', $suffix = '', $fields_for = null, $rows_for = null ) {
		$this->capability = $capability;
		$this->fields_for = $fields_for ? $fields_for : array( 'ContentExcelImporterApp', 'fields' );
		$this->rows_for   = $rows_for ? $rows_for : array( 'ContentExcelImporterPreview', 'rows' );
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'wp_ajax_cexl_ai_map' . $suffix, array( $this, 'ajax_map' ) );
	}

	/**
	 * Capability that may use the importer.
	 *
	 * @var string
	 */
	public $capability;

	/**
	 * Fields of a post type, in groups.
	 *
	 * @var callable
	 */
	public $fields_for;

	/**
	 * Rows of an uploaded sheet by token.
	 *
	 * @var callable
	 */
	public $rows_for;

	/**
	 * Registers the product with the shared WPFactory AI library.
	 */
	public static function register() {
		if ( function_exists( 'wpfactory_ai' ) ) {
			wpfactory_ai()->register_product( self::PRODUCT, array( 'name' => __( 'Excel Importer for WooCommerce', 'content-excel-importer' ) ) );
		}
	}

	/**
	 * Whether the library is there.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'wpfactory_ai' );
	}

	/**
	 * Settings for the screen script.
	 *
	 * @return array
	 */
	public static function script_settings() {
		if ( ! self::available() ) {
			return array( 'available' => false );
		}
		$ai = wpfactory_ai();
		return array(
			'available' => true,
			'connected' => (bool) $ai->is_connected(),
			'adminUrl'  => $ai->admin_url(),
			'nonce'     => wp_create_nonce( 'cexl_ai' ),
		);
	}

	/**
	 * Credits link for the top bar.
	 *
	 * @return string HTML.
	 */
	public static function pill() {
		if ( ! self::available() ) {
			return '';
		}
		return (string) wpfactory_ai()->pill( self::PRODUCT );
	}

	/**
	 * Sends a request: to the cexl_ai_complete filter when something answers it (tests, own provider), else to WPFactory AI.
	 *
	 * @param array  $body Anthropic Messages API body.
	 * @param string $task Task name for the usage report.
	 * @return array|WP_Error
	 */
	public static function complete( $body, $task ) {
		/**
		 * Answers an AI request instead of WPFactory AI (tests, own provider). Return an array shaped like the library's
		 * call() result (message, credits, balance, request_id) or a WP_Error; null sends the request to WPFactory AI.
		 *
		 * @param null|array|WP_Error $result Result.
		 * @param array               $body   Request body.
		 * @param string              $task   Task name.
		 */
		$result = apply_filters( 'cexl_ai_complete', null, $body, $task );
		if ( null !== $result ) {
			return $result;
		}
		if ( ! self::available() ) {
			return new WP_Error( 'cexl_ai_missing', __( 'The AI library is missing from this copy of the plugin.', 'content-excel-importer' ) );
		}
		return wpfactory_ai()->call( self::PRODUCT, $body, array( 'task' => $task ) );
	}

	/**
	 * Whether a column header looks like personal data (its sample cells are never sent).
	 *
	 * @param string $header Header.
	 * @return bool
	 */
	public static function looks_personal( $header ) {
		return (bool) preg_match( '/e-?mail|phone|mobile|tel\b|address|street|post ?code|zip|birth|iban|passport|customer/i', (string) $header );
	}

	/**
	 * Request body for matching columns.
	 *
	 * @param array $rows   Sheet rows (row 1 = headers).
	 * @param array $fields Field key => label.
	 * @return array
	 */
	public static function map_body( $rows, $fields ) {
		$headers = array_map( 'strval', $rows[1] );
		$columns = array();
		foreach ( $headers as $letter => $header ) {
			$samples = array();
			if ( ! self::looks_personal( $header ) ) {
				for ( $i = 2; $i <= 4; $i++ ) {
					if ( isset( $rows[ $i ][ $letter ] ) && '' !== trim( (string) $rows[ $i ][ $letter ] ) ) {
						$samples[] = mb_substr( wp_strip_all_tags( (string) $rows[ $i ][ $letter ] ), 0, 80 );
					}
				}
			}
			$columns[] = array(
				'column'  => $letter,
				'header'  => mb_substr( $header, 0, 80 ),
				'samples' => $samples,
			);
		}
		$field_list = array();
		foreach ( $fields as $key => $label ) {
			$field_list[] = array(
				'key'   => $key,
				'label' => $label,
			);
		}
		return array(
			'model'       => 'claude-haiku-5-5',
			'max_tokens'  => 600,
			'system'      => 'You match the columns of a spreadsheet to the fields of a WordPress or WooCommerce import. Use the header and the sample values. Match a column only when you are fairly sure; otherwise use "skip". Each field takes at most one column. "cexl_id" is the numeric ID of an existing item. Give a confidence from 0 to 1 for each column.',
			'tools'       => array(
				array(
					'name'         => 'set_matches',
					'description'  => 'The field for every column.',
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'matches' => array(
								'type'  => 'array',
								'items' => array(
									'type'       => 'object',
									'properties' => array(
										'column'     => array( 'type' => 'string' ),
										'field'      => array(
											'type' => 'string',
											'enum' => array_merge( array_keys( $fields ), array( 'skip' ) ),
										),
										'confidence' => array( 'type' => 'number' ),
									),
									'required'   => array( 'column', 'field', 'confidence' ),
								),
							),
						),
						'required'   => array( 'matches' ),
					),
				),
			),
			'tool_choice' => array(
				'type' => 'tool',
				'name' => 'set_matches',
			),
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => (string) wp_json_encode(
						array(
							'store_language' => get_locale(),
							'columns'        => $columns,
							'fields'         => $field_list,
						)
					),
				),
			),
		);
	}

	/**
	 * Matches from the AI answer: field => column letter, and the fields the answer is sure about.
	 *
	 * @param array $message Anthropic message.
	 * @param array $headers Column letter => header.
	 * @param array $fields  Field key => label.
	 * @return array { map, sure }
	 */
	public static function parse_matches( $message, $headers, $fields ) {
		$best = array();
		foreach ( isset( $message['content'] ) ? (array) $message['content'] : array() as $block ) {
			if ( ! is_array( $block ) || 'tool_use' !== ( isset( $block['type'] ) ? $block['type'] : '' ) || empty( $block['input']['matches'] ) ) {
				continue;
			}
			foreach ( (array) $block['input']['matches'] as $match ) {
				$column = isset( $match['column'] ) ? (string) $match['column'] : '';
				$field  = isset( $match['field'] ) ? (string) $match['field'] : '';
				$conf   = isset( $match['confidence'] ) ? (float) $match['confidence'] : 0;
				if ( ! isset( $headers[ $column ] ) || ! isset( $fields[ $field ] ) ) {
					continue;
				}
				if ( ! isset( $best[ $field ] ) || $conf > $best[ $field ]['confidence'] ) {
					$best[ $field ] = array(
						'column'     => $column,
						'confidence' => $conf,
					);
				}
			}
		}
		$map  = array();
		$sure = array();
		$used = array();
		uasort(
			$best,
			function ( $a, $b ) {
				return $b['confidence'] <=> $a['confidence'];
			}
		);
		foreach ( $best as $field => $match ) {
			if ( isset( $used[ $match['column'] ] ) ) {
				continue; // One field per column: the surer match wins.
			}
			$used[ $match['column'] ] = true;
			$map[ $field ]            = $match['column'];
			$sure[ $field ]           = $match['confidence'] >= 0.8;
		}
		return array(
			'map'  => $map,
			'sure' => $sure,
		);
	}

	/**
	 * AJAX: match the uploaded sheet's columns with AI.
	 */
	public function ajax_map() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to import.', 'content-excel-importer' ) ), 403 );
		}
		check_ajax_referer( 'cexl_ai', 'nonce' );
		$token = isset( $_POST['cexl_file'] ) ? sanitize_text_field( wp_unslash( $_POST['cexl_file'] ) ) : '';
		$type  = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';
		$rows  = call_user_func( $this->rows_for, $token );
		if ( null === $rows || empty( $rows[1] ) ) {
			wp_send_json_error( array( 'message' => __( 'The uploaded file was not found. Please upload it again.', 'content-excel-importer' ) ) );
		}
		$fields = array();
		foreach ( call_user_func( $this->fields_for, $type ) as $group ) {
			$fields += $group['fields'];
		}
		$result = self::complete( self::map_body( $rows, $fields ), 'map-columns' );
		if ( is_wp_error( $result ) ) {
			$data = array( 'message' => $result->get_error_message() );
			if ( 'wpfactory_ai_not_connected' === $result->get_error_code() && self::available() ) {
				$data['connect'] = wpfactory_ai()->admin_url();
			}
			wp_send_json_error( $data );
		}
		$matches = self::parse_matches( isset( $result['message'] ) ? $result['message'] : array(), array_map( 'strval', $rows[1] ), $fields );
		wp_send_json_success(
			array(
				'map'     => (object) $matches['map'],
				'sure'    => (object) $matches['sure'],
				'credits' => isset( $result['credits'] ) ? $result['credits'] : null,
				'balance' => isset( $result['balance'] ) ? $result['balance'] : null,
			)
		);
	}
}

new ContentExcelImporterAI();
