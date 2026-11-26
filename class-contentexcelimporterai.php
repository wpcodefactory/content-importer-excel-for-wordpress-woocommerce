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
		$this->rows_for    = $rows_for ? $rows_for : array( 'ContentExcelImporterPreview', 'rows' );
		$this->preview_for = array( 'ContentExcelImporterPreview', 'preview' );
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'wp_ajax_cexl_ai_map' . $suffix, array( $this, 'ajax_map' ) );
		add_action( 'wp_ajax_cexl_ai_describe' . $suffix, array( $this, 'ajax_describe' ) );
		add_action( 'wp_ajax_cexl_ai_apply' . $suffix, array( $this, 'ajax_apply' ) );
		add_action( 'wp_ajax_cexl_ai_check' . $suffix, array( $this, 'ajax_check' ) );
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
	 * Preview of an import (rows, request).
	 *
	 * @var callable
	 */
	public $preview_for;

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

	/**
	 * Items an import created that have no description yet (at most 50), for "Write with AI".
	 *
	 * @param array $report Report data (rows with result and ID).
	 * @return array Post IDs.
	 */
	public static function describe_candidates( $report ) {
		$ids = array();
		foreach ( isset( $report['rows'] ) ? (array) $report['rows'] : array() as $row ) {
			$id = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( 'created' === ( isset( $row['result'] ) ? $row['result'] : '' ) && $id && ! in_array( $id, $ids, true ) ) {
				$post = get_post( $id );
				if ( $post && '' === trim( (string) $post->post_content ) && in_array( $post->post_type, array( 'product', 'post', 'page' ), true ) && current_user_can( 'edit_post', $id ) ) {
					$ids[] = $id;
				}
			}
			if ( count( $ids ) >= 50 ) {
				break;
			}
		}
		return $ids;
	}

	/**
	 * Request body for writing one item's description from its own data.
	 *
	 * @param WP_Post $post Item.
	 * @return array
	 */
	public static function describe_body( $post ) {
		$facts = array(
			'title'             => $post->post_title,
			'type'              => $post->post_type,
			'short_description' => wp_strip_all_tags( $post->post_excerpt ),
		);
		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $tax ) {
			if ( ! $tax->public || in_array( $tax->name, array( 'product_type', 'product_visibility', 'post_format', 'language', 'post_translations' ), true ) ) {
				continue;
			}
			$names = wp_get_object_terms( $post->ID, $tax->name, array( 'fields' => 'names' ) );
			if ( is_array( $names ) && $names ) {
				$facts[ $tax->labels->name ] = implode( ', ', $names );
			}
		}
		if ( 'product' === $post->post_type ) {
			$facts['sku'] = (string) get_post_meta( $post->ID, '_sku', true );
		}
		return array(
			'model'       => 'claude-haiku-5-5',
			'max_tokens'  => 700,
			'system'      => 'You write descriptions for items of an online store from the facts given, in the store language. Use only those facts: never invent materials, sizes, ingredients, prices, stock, shipping, guarantees or claims. When the facts are thin, write less. The description: one to three short HTML paragraphs (<p>), at most 120 words. The short description: one plain sentence, at most 25 words.',
			'tools'       => array(
				array(
					'name'         => 'set_text',
					'description'  => 'The texts for this item.',
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'description'       => array( 'type' => 'string' ),
							'short_description' => array( 'type' => 'string' ),
						),
						'required'   => array( 'description', 'short_description' ),
					),
				),
			),
			'tool_choice' => array(
				'type' => 'tool',
				'name' => 'set_text',
			),
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => (string) wp_json_encode(
						array(
							'store'          => get_bloginfo( 'name' ),
							'store_language' => get_locale(),
							'facts'          => array_filter( $facts, 'strlen' ),
						)
					),
				),
			),
		);
	}

	/**
	 * AJAX: draft a description for one item. Nothing is saved on the item until the merchant applies it.
	 */
	public function ajax_describe() {
		check_ajax_referer( 'cexl_ai', 'nonce' );
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$post = get_post( $id );
		if ( ! current_user_can( $this->capability ) || ! $post || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'content-excel-importer' ) ), 403 );
		}
		$result = self::complete( self::describe_body( $post ), 'write-descriptions' );
		if ( is_wp_error( $result ) ) {
			$data = array( 'message' => $result->get_error_message() );
			if ( 'wpfactory_ai_not_connected' === $result->get_error_code() && self::available() ) {
				$data['connect'] = wpfactory_ai()->admin_url();
			}
			wp_send_json_error( $data );
		}
		$text = array();
		foreach ( isset( $result['message']['content'] ) ? (array) $result['message']['content'] : array() as $block ) {
			if ( is_array( $block ) && 'tool_use' === ( isset( $block['type'] ) ? $block['type'] : '' ) && isset( $block['input'] ) ) {
				$text = (array) $block['input'];
			}
		}
		$description = wp_kses_post( isset( $text['description'] ) ? (string) $text['description'] : '' );
		$short       = sanitize_text_field( isset( $text['short_description'] ) ? (string) $text['short_description'] : '' );
		if ( '' === trim( wp_strip_all_tags( $description ) ) ) {
			wp_send_json_error( array( 'message' => __( 'The AI did not return a description. Nothing was saved.', 'content-excel-importer' ) ) );
		}
		wp_send_json_success(
			array(
				'id'          => $id,
				'title'       => get_the_title( $id ),
				'description' => $description,
				'short'       => '' === trim( $post->post_excerpt ) ? $short : '',
				'credits'     => isset( $result['credits'] ) ? $result['credits'] : null,
				'balance'     => isset( $result['balance'] ) ? $result['balance'] : null,
			)
		);
	}

	/**
	 * AJAX: save a reviewed description on the item (the short description only when the item has none).
	 */
	public function ajax_apply() {
		check_ajax_referer( 'cexl_ai', 'nonce' );
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$post = get_post( $id );
		if ( ! current_user_can( $this->capability ) || ! $post || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'content-excel-importer' ) ), 403 );
		}
		$update = array(
			'ID'           => $id,
			'post_content' => wp_kses_post( wp_unslash( isset( $_POST['description'] ) ? $_POST['description'] : '' ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_kses_post.
		);
		$short = sanitize_text_field( wp_unslash( isset( $_POST['short'] ) ? $_POST['short'] : '' ) );
		if ( '' !== $short && '' === trim( $post->post_excerpt ) ) {
			$update['post_excerpt'] = $short;
		}
		wp_update_post( $update );
		wp_send_json_success();
	}

	/**
	 * Request body for checking the planned changes of an import (at most 200 rows with changes).
	 *
	 * @param array $preview Preview (rows with action, changes and warnings).
	 * @return array|null Null when there is nothing to check.
	 */
	public static function check_body( $preview ) {
		$rows = array();
		foreach ( isset( $preview['rows'] ) ? (array) $preview['rows'] : array() as $row ) {
			if ( empty( $row['changes'] ) || count( $rows ) >= 200 ) {
				continue;
			}
			$changes = array();
			foreach ( $row['changes'] as $change ) {
				$changes[] = array(
					'field' => $change['label'],
					'old'   => mb_substr( wp_strip_all_tags( (string) $change['old'] ), 0, 80 ),
					'new'   => mb_substr( wp_strip_all_tags( (string) $change['new'] ), 0, 80 ),
				);
			}
			$rows[] = array(
				'row'     => (int) $row['row'],
				'item'    => mb_substr( (string) $row['title'], 0, 80 ),
				'action'  => $row['action'],
				'changes' => $changes,
			);
		}
		if ( ! $rows ) {
			return null;
		}
		$categories = get_terms(
			array(
				'taxonomy'   => taxonomy_exists( 'product_cat' ) ? 'product_cat' : 'category',
				'hide_empty' => false,
				'fields'     => 'names',
				'number'     => 300,
			)
		);
		return array(
			'model'       => 'claude-haiku-5-5',
			'max_tokens'  => 1500,
			'system'      => 'You check the planned changes of a store import before they are saved. Flag only real problems a merchant would want to see: a price that changes by more than half, a price or stock value that looks like a typo or uses the wrong unit or decimal sign, a weight or size that looks like the wrong unit, a category name that almost matches an existing one (for example a different spelling or plural), an empty or placeholder title, text that looks cut off or in the wrong column. Do not flag normal changes. Each flag: the row number and one short plain sentence, in the store language.',
			'tools'       => array(
				array(
					'name'         => 'set_flags',
					'description'  => 'The problems found, an empty list when there are none.',
					'input_schema' => array(
						'type'       => 'object',
						'properties' => array(
							'flags' => array(
								'type'  => 'array',
								'items' => array(
									'type'       => 'object',
									'properties' => array(
										'row'     => array( 'type' => 'integer' ),
										'message' => array( 'type' => 'string' ),
									),
									'required'   => array( 'row', 'message' ),
								),
							),
						),
						'required'   => array( 'flags' ),
					),
				),
			),
			'tool_choice' => array(
				'type' => 'tool',
				'name' => 'set_flags',
			),
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => (string) wp_json_encode(
						array(
							'store_language'      => get_locale(),
							'currency'            => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
							'weight_unit'         => get_option( 'woocommerce_weight_unit', '' ),
							'existing_categories' => is_array( $categories ) ? array_values( $categories ) : array(),
							'rows'                => $rows,
						)
					),
				),
			),
		);
	}

	/**
	 * AJAX: check the planned changes of the uploaded sheet with AI. Only flags come back; nothing is changed.
	 */
	public function ajax_check() {
		if ( ! current_user_can( $this->capability ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to import.', 'content-excel-importer' ) ), 403 );
		}
		check_ajax_referer( 'cexl_ai', 'nonce' );
		$token = isset( $_POST['cexl_file'] ) ? sanitize_text_field( wp_unslash( $_POST['cexl_file'] ) ) : '';
		$rows  = call_user_func( $this->rows_for, $token );
		if ( null === $rows ) {
			wp_send_json_error( array( 'message' => __( 'The uploaded file was not found. Please upload it again.', 'content-excel-importer' ) ) );
		}
		$preview = call_user_func( $this->preview_for, $rows, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized where used, as in the preview action.
		$body    = self::check_body( $preview );
		if ( null === $body ) {
			wp_send_json_success( array( 'flags' => array() ) );
		}
		$result = self::complete( $body, 'check-sheet' );
		if ( is_wp_error( $result ) ) {
			$data = array( 'message' => $result->get_error_message() );
			if ( 'wpfactory_ai_not_connected' === $result->get_error_code() && self::available() ) {
				$data['connect'] = wpfactory_ai()->admin_url();
			}
			wp_send_json_error( $data );
		}
		$known = wp_list_pluck( $preview['rows'], 'row' );
		$flags = array();
		foreach ( isset( $result['message']['content'] ) ? (array) $result['message']['content'] : array() as $block ) {
			if ( ! is_array( $block ) || 'tool_use' !== ( isset( $block['type'] ) ? $block['type'] : '' ) || empty( $block['input']['flags'] ) ) {
				continue;
			}
			foreach ( (array) $block['input']['flags'] as $flag ) {
				$row = isset( $flag['row'] ) ? (int) $flag['row'] : 0;
				$msg = isset( $flag['message'] ) ? sanitize_text_field( (string) $flag['message'] ) : '';
				if ( in_array( $row, $known, true ) && '' !== $msg && count( $flags ) < 300 ) {
					$flags[] = array(
						'row'     => $row,
						'message' => mb_substr( $msg, 0, 200 ),
					);
				}
			}
		}
		wp_send_json_success(
			array(
				'flags'   => $flags,
				'credits' => isset( $result['credits'] ) ? $result['credits'] : null,
			)
		);
	}
}

new ContentExcelImporterAI();
