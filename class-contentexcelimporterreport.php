<?php
/**
 * Excel Importer for WooCommerce - import report and history.
 *
 * When the import screen asks for it (cexl_report = 1), every row's result is kept in a small JSON file next to the
 * upload in the closed uploads folder: created, updated, failed, with the item ID and title. Reports are listed on the
 * History screen, downloadable as CSV by users who may import, and removed after 30 days.
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import reports.
 */
class ContentExcelImporterReport {

	/**
	 * Days a report is kept.
	 */
	const DAYS = 30;

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( 'admin_post_cexl_report_csv', array( $this, 'download_csv' ) );
	}

	/**
	 * Path of the report of one import.
	 *
	 * @param string $token Import token.
	 * @return string Empty for an invalid token.
	 */
	public static function file( $token ) {
		$sheet = ContentExcelImporterProducts::import_file( $token );
		return '' === $sheet ? '' : dirname( $sheet ) . '/report-' . $token . '.json';
	}

	/**
	 * Report data.
	 *
	 * @param string $token Import token.
	 * @return array|null
	 */
	public static function get( $token ) {
		$file = self::file( $token );
		if ( '' === $file || ! file_exists( $file ) ) {
			return null;
		}
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Records the result of one row.
	 *
	 * @param string $token  Import token.
	 * @param int    $row    Sheet row number.
	 * @param string $result created, updated, failed or skipped.
	 * @param int    $id     Item ID (0 when nothing was saved).
	 * @param string $title  Title from the row.
	 * @param array  $info   Import details, used when the report starts (type, file name, total rows).
	 */
	public static function add_row( $token, $row, $result, $id, $title, $info = array() ) {
		$file = self::file( $token );
		if ( '' === $file ) {
			return;
		}
		$data = self::get( $token );
		if ( null === $data ) {
			$data = array(
				'token'   => $token,
				'type'    => isset( $info['type'] ) ? sanitize_key( $info['type'] ) : '',
				'file'    => isset( $info['file'] ) ? sanitize_file_name( $info['file'] ) : '',
				'total'   => isset( $info['total'] ) ? (int) $info['total'] : 0,
				'user'    => get_current_user_id(),
				'started' => time(),
				'counts'  => array( 'created' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0 ),
				'rows'    => array(),
			);
		}
		$result                      = in_array( $result, array( 'created', 'updated', 'failed', 'skipped' ), true ) ? $result : 'failed';
		$data['rows'][ (int) $row ]  = array( 'result' => $result, 'id' => (int) $id, 'title' => sanitize_text_field( $title ) );
		$data['counts']              = array_merge( array( 'created' => 0, 'updated' => 0, 'failed' => 0, 'skipped' => 0 ), array_count_values( wp_list_pluck( $data['rows'], 'result' ) ) );
		$data['updated']             = time();
		file_put_contents( $file, (string) wp_json_encode( $data ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * All kept reports, newest first. Removes the ones older than 30 days.
	 *
	 * @return array
	 */
	public static function all() {
		$out = array();
		foreach ( (array) glob( ContentExcelImporterProducts::import_dir() . '/report-*.json' ) as $file ) {
			if ( filemtime( $file ) < time() - self::DAYS * DAY_IN_SECONDS ) {
				wp_delete_file( $file );
				continue;
			}
			$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( is_array( $data ) ) {
				unset( $data['rows'] );
				$out[] = $data;
			}
		}
		usort(
			$out,
			function ( $a, $b ) {
				return (int) $b['started'] - (int) $a['started'];
			}
		);
		return $out;
	}

	/**
	 * Link to download a report as CSV.
	 *
	 * @param string $token Import token.
	 * @return string
	 */
	public static function csv_url( $token ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=cexl_report_csv&report=' . rawurlencode( $token ) ), 'cexl_report_csv' );
	}

	/**
	 * Sends a report as CSV.
	 */
	public function download_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'content-excel-importer' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'cexl_report_csv' );
		$token = isset( $_GET['report'] ) ? sanitize_text_field( wp_unslash( $_GET['report'] ) ) : '';
		$data  = self::get( $token );
		if ( null === $data ) {
			wp_die( esc_html__( 'This report was not found. Reports are kept for 30 days.', 'content-excel-importer' ), '', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="import-report-' . gmdate( 'Y-m-d', (int) $data['started'] ) . '.csv"' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( __( 'Row', 'content-excel-importer' ), __( 'Result', 'content-excel-importer' ), __( 'Title', 'content-excel-importer' ), __( 'ID', 'content-excel-importer' ), __( 'Link', 'content-excel-importer' ) ) );
		ksort( $data['rows'] );
		foreach ( $data['rows'] as $row => $line ) {
			// A leading = + - @ would make a spreadsheet run the cell as a formula.
			$title = preg_match( '/^[=+\-@]/', (string) $line['title'] ) ? "'" . $line['title'] : $line['title'];
			fputcsv( $out, array( $row, $line['result'], $title, $line['id'] ? $line['id'] : '', $line['id'] ? get_edit_post_link( $line['id'], 'raw' ) : '' ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}
}

new ContentExcelImporterReport();
