<?php
/**
 * Excel Importer for WooCommerce - imports that keep running after the merchant leaves the page.
 *
 * The import screen starts a job with the same request it used to send for every row. The job runs the import action
 * row by row on the server: in short slices while the screen is open (each progress check runs one), and through
 * WooCommerce's Action Scheduler, or WP-Cron when it is missing, when nobody watches. A lock keeps two runners apart.
 * The rows are imported exactly as before; only who sends them changes.
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Background imports.
 */
class ContentExcelImporterBackground {

	/**
	 * True while the runner imports a row (the import action then returns instead of ending the request).
	 *
	 * @var bool
	 */
	public static $active = false;

	/**
	 * Seconds of rows per progress check, and per scheduled run.
	 */
	const SLICE_POLL = 8;
	const SLICE_RUN  = 20;

	/**
	 * Scheduled action.
	 */
	const HOOK = 'cexl_bg_tick';

	/**
	 * Capability that may import.
	 */
	const CAP = 'manage_options';

	/**
	 * Prefix of the options that hold jobs.
	 */
	const PREFIX = 'cexl_job_';

	/**
	 * Hooks.
	 */
	public function __construct() {
		add_action( self::HOOK, array( __CLASS__, 'tick' ) );
		add_action( 'wp_ajax_cexl_bg_start', array( __CLASS__, 'ajax_start' ) );
		add_action( 'wp_ajax_cexl_bg_status', array( __CLASS__, 'ajax_status' ) );
		add_action( 'wp_ajax_cexl_bg_control', array( __CLASS__, 'ajax_control' ) );
	}

	/**
	 * Option that holds a job.
	 *
	 * @param string $token Import token.
	 * @return string
	 */
	private static function key( $token ) {
		return self::PREFIX . $token;
	}

	/**
	 * A job, read fresh from the database (another request may have paused it).
	 *
	 * @param string $token Import token.
	 * @return array|null
	 */
	public static function get_job( $token ) {
		if ( ! preg_match( '/^[A-Za-z0-9]{32}$/', (string) $token ) ) {
			return null;
		}
		wp_cache_delete( self::key( $token ), 'options' );
		$job = get_option( self::key( $token ), null );
		return is_array( $job ) ? $job : null;
	}

	/**
	 * Saves a job.
	 *
	 * @param array $job Job.
	 */
	private static function put_job( $job ) {
		$job['updated'] = time();
		update_option( self::key( $job['token'] ), $job, false );
	}

	/**
	 * What the screen needs to show a job.
	 *
	 * @param array $job Job.
	 * @return array
	 */
	public static function view( $job ) {
		return array(
			'token' => $job['token'],
			'file'  => $job['file'],
			'type'  => $job['type'],
			'total' => (int) $job['total'],
			'done'  => (int) $job['done'],
			'state' => $job['state'],
			'last'  => $job['last'],
			'start' => (int) $job['started'],
		);
	}

	/**
	 * Jobs of the current user that are still running or paused (finished jobs older than two days are removed).
	 *
	 * @return array
	 */
	public static function open_jobs() {
		global $wpdb;
		$out = array();
		$like = $wpdb->esc_like( self::PREFIX ) . '%';
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ) as $name ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$job = self::get_job( substr( $name, strlen( self::PREFIX ) ) );
			if ( ! $job ) {
				continue;
			}
			if ( in_array( $job['state'], array( 'done', 'stopped', 'failed' ), true ) ) {
				if ( (int) $job['updated'] < time() - 2 * DAY_IN_SECONDS ) {
					delete_option( $name );
				}
				continue;
			}
			if ( (int) $job['user'] === get_current_user_id() ) {
				$out[] = self::view( $job );
			}
		}
		usort(
			$out,
			function ( $x, $y ) {
				return $y['start'] - $x['start']; // Newest first.
			}
		);
		return array_slice( $out, 0, 3 );
	}

	/**
	 * Asks for a run soon: Action Scheduler when WooCommerce has it, else WP-Cron.
	 *
	 * @param string $token Import token.
	 */
	public static function schedule( $token ) {
		if ( function_exists( 'as_enqueue_async_action' ) && function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( self::HOOK, array( $token ), 'cexl' ) ) {
				as_enqueue_async_action( self::HOOK, array( $token ), 'cexl' );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK, array( $token ) ) ) {
			wp_schedule_single_event( time(), self::HOOK, array( $token ) );
		}
		if ( ! wp_doing_cron() && function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * Scheduled run: a slice of rows, then the next run while the job is running.
	 *
	 * @param string $token Import token.
	 */
	public static function tick( $token ) {
		/**
		 * Seconds of rows per slice ('run' for scheduled runs, 'poll' for the screen's progress checks).
		 *
		 * @param int    $seconds Seconds.
		 * @param string $kind    run or poll.
		 */
		self::run_slice( $token, (int) apply_filters( 'cexl_bg_slice', self::SLICE_RUN, 'run' ) );
		$job = self::get_job( $token );
		if ( $job && 'running' === $job['state'] ) {
			self::schedule( $token );
		}
	}

	/**
	 * Imports rows of a job for a few seconds, one row at a time through the import action.
	 *
	 * @param string $token   Import token.
	 * @param int    $seconds Time for this slice.
	 */
	public static function run_slice( $token, $seconds ) {
		$lock = 'cexl_lock_' . self::PREFIX . $token;
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( (int) get_option( $lock ) > time() - 120 ) {
				return; // Another runner is on this job.
			}
			update_option( $lock, time(), false );
		}
		$job = self::get_job( $token );
		if ( ! $job || 'running' !== $job['state'] ) {
			delete_option( $lock );
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/post.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$user  = get_current_user_id();
		$post  = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$req   = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$start = microtime( true );
		wp_set_current_user( (int) $job['user'] );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( $seconds + 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		self::$active = true;
		try {
			while ( $job['next'] <= $job['total'] && microtime( true ) - $start < $seconds ) {
				$row      = (int) $job['next'];
				$_POST    = array_merge( $job['request'], array( 'start' => (string) $row ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				// Each row starts as it would in a request of its own (an earlier row can leave the global post set).
				$GLOBALS['post'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				ob_start();
				try {
					self::import_row();
				} catch ( \Throwable $e ) {
					echo "<p class='error'>" . esc_html( $e->getMessage() ) . '</p>';
				}
				$html = (string) ob_get_clean();
				if ( class_exists( 'WC_Post_Data' ) && method_exists( 'WC_Post_Data', 'do_deferred_product_sync' ) ) {
					WC_Post_Data::do_deferred_product_sync(); // What the end of a request does after each screen row (variable product prices).
				}
				self::after_row();
				$result = self::row_result( $token, $row, $html );
				if ( 'failed' === $result['result'] && ! empty( $job['request']['cexl_report'] ) ) {
					$report = ContentExcelImporterReport::get( $token );
					if ( ! $report || ! isset( $report['rows'][ $row ] ) ) {
						// A row that stopped with an error counts as failed in the result and the History.
						ContentExcelImporterReport::add_row( $token, $row, 'failed', 0, $result['title'], array( 'type' => $job['type'], 'file' => $job['file'], 'total' => (int) $job['total'] - 1 ) );
					}
				}
				$job['last'][] = $result;
				$job['last']   = array_slice( $job['last'], -8 );
				$job['next']   = $row + 1;
				$job['done']   = $row - 1;
				if ( false !== strpos( $html, "style='color:red'" ) && $row < $job['total'] && ! file_exists( substr( self::sheet( $token ), 0, -5 ) . '.json' ) ) {
					$job['state'] = 'failed'; // The uploaded sheet is gone: nothing more can be imported.
				}
				if ( $job['next'] > $job['total'] ) {
					$job['state'] = 'done';
				}
				$fresh = self::get_job( $token );
				if ( $fresh && 'running' !== $fresh['state'] && 'running' === $job['state'] ) {
					$job['state'] = $fresh['state']; // Paused or stopped from the screen meanwhile.
				}
				self::put_job( $job );
				if ( 'running' !== $job['state'] ) {
					break;
				}
			}
		} finally {
			self::$active = false;
			$_POST        = $post; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_REQUEST     = $req; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_set_current_user( $user );
			delete_option( $lock );
		}
	}

	/**
	 * Imports the row in $_POST with the import action.
	 */
	protected static function import_row() {
		( new ContentExcelImporterProducts() )->import_content();
	}

	/**
	 * After each row (Pro notes what Undo needs here).
	 */
	protected static function after_row() {
	}

	/**
	 * Path of a job's uploaded sheet.
	 *
	 * @param string $token Import token.
	 * @return string
	 */
	protected static function sheet( $token ) {
		return ContentExcelImporterProducts::import_file( $token );
	}

	/**
	 * Result of one row, from the import report when it has the row.
	 *
	 * @param string $token Import token.
	 * @param int    $row   Row number.
	 * @param string $html  What the import action printed.
	 * @return array { row, result, title }
	 */
	protected static function row_result( $token, $row, $html ) {
		$report = ContentExcelImporterReport::get( $token );
		if ( $report && isset( $report['rows'][ $row ] ) ) {
			return array(
				'row'    => $row,
				'result' => $report['rows'][ $row ]['result'],
				'title'  => $report['rows'][ $row ]['title'],
			);
		}
		return array(
			'row'    => $row,
			'result' => false !== strpos( $html, "class='skipped'" ) ? 'skipped' : 'failed',
			'title'  => mb_substr( trim( wp_strip_all_tags( $html ) ), 0, 120 ),
		);
	}

	/**
	 * AJAX: start a job with the import request of the screen.
	 */
	public static function ajax_start() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to import.', 'content-excel-importer' ) ), 403 );
		}
		check_ajax_referer( 'excel_process', 'secNonce' );
		// Kept as WordPress hands it to the import action (slashed): the rows run exactly as when the screen sent them.
		$request = $_POST; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is sanitized where the import action uses it.
		$token   = isset( $request['cexl_file'] ) ? sanitize_text_field( wp_unslash( $request['cexl_file'] ) ) : '';
		$sheet   = self::sheet( $token );
		if ( '' === $sheet || ! file_exists( substr( $sheet, 0, -5 ) . '.json' ) ) {
			wp_send_json_error( array( 'message' => __( 'The uploaded file was not found. Please upload it again.', 'content-excel-importer' ) ) );
		}
		unset( $request['action'], $request['secNonce'], $request['_wp_http_referer'], $request['start'] );
		$job = array(
			'token'   => $token,
			'user'    => get_current_user_id(),
			'file'    => isset( $request['cexl_file_name'] ) ? sanitize_file_name( wp_unslash( $request['cexl_file_name'] ) ) : '',
			'type'    => isset( $request['post_type'] ) ? sanitize_key( wp_unslash( $request['post_type'] ) ) : '',
			'total'   => isset( $request['finalupload'] ) ? (int) $request['finalupload'] : 0,
			'next'    => 2,
			'done'    => 0,
			'state'   => 'running',
			'started' => time(),
			'last'    => array(),
			'request' => $request,
		);
		self::put_job( $job );
		self::schedule( $token );
		wp_send_json_success( self::view( $job ) );
	}

	/**
	 * A job of the current user, or an error answer.
	 *
	 * @return array
	 */
	private static function own_job() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to import.', 'content-excel-importer' ) ), 403 );
		}
		check_ajax_referer( 'excel_process', 'secNonce' );
		$job = self::get_job( isset( $_POST['cexl_file'] ) ? sanitize_text_field( wp_unslash( $_POST['cexl_file'] ) ) : '' );
		if ( ! $job || ( (int) $job['user'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'This import was not found.', 'content-excel-importer' ) ), 404 );
		}
		return $job;
	}

	/**
	 * AJAX: progress of a job; while the screen watches, each check also imports a slice of rows.
	 */
	public static function ajax_status() {
		$job = self::own_job();
		if ( 'running' === $job['state'] ) {
			self::run_slice( $job['token'], (int) apply_filters( 'cexl_bg_slice', self::SLICE_POLL, 'poll' ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName -- documented in tick().
			$job = self::get_job( $job['token'] );
			if ( 'running' === $job['state'] ) {
				self::schedule( $job['token'] );
			}
		}
		wp_send_json_success( self::view( $job ) );
	}

	/**
	 * AJAX: pause, resume or stop a job.
	 */
	public static function ajax_control() {
		$job  = self::own_job();
		$want = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		if ( 'pause' === $want && 'running' === $job['state'] ) {
			$job['state'] = 'paused';
		} elseif ( 'resume' === $want && 'paused' === $job['state'] ) {
			$job['state'] = 'running';
		} elseif ( 'stop' === $want && in_array( $job['state'], array( 'running', 'paused' ), true ) ) {
			$job['state'] = 'stopped';
		}
		self::put_job( $job );
		if ( 'running' === $job['state'] ) {
			self::schedule( $job['token'] );
		}
		wp_send_json_success( self::view( $job ) );
	}
}

new ContentExcelImporterBackground();
