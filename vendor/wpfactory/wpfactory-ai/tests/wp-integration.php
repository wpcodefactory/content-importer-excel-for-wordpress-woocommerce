<?php
/**
 * End-to-end test on a real WordPress site against a local WPFactory AI server, with Mailpit for the code.
 * Run with WP-CLI, in two phases (credits are added on the server between them):
 *   wp eval-file tests/wp-integration.php connect <email>
 *   php bin/admin.php credits:add <email> 100          (on the server)
 *   wp eval-file tests/wp-integration.php spend
 *
 * Needs the library loaded, wpfactory_ai_endpoint pointing at the local server, and Mailpit's API at
 * WPFAI_MAILPIT (default http://127.0.0.1:10006).
 *
 * @package WPFactory\AI
 */

defined( 'ABSPATH' ) || exit;

$phase  = isset( $args[0] ) ? (string) $args[0] : '';
$failed = 0;
$check  = function ( $cond, $what ) use ( &$failed ) {
	WP_CLI::log( ( $cond ? 'PASS ' : 'FAIL ' ) . $what );
	$failed += $cond ? 0 : 1;
};
$ai = wpfactory_ai();
$ai->register_product( 'ai-assistant-mcp', array( 'name' => 'AI Assistant & MCP Server' ) );
$check( version_compare( $ai->version(), '1.0.0', '>=' ), 'library ' . $ai->version() . ' loaded (' . WPFactory_AI_Versions::loaded() . ')' );

if ( 'connect' === $phase ) {
	$email = isset( $args[1] ) ? (string) $args[1] : 'library-test@wpfactory.com';
	$ai->disconnect();
	$check( ! $ai->is_connected(), 'starts disconnected' );
	$check( is_wp_error( $ai->call( 'ai-assistant-mcp', array() ) ) && 'wpfactory_ai_not_connected' === $ai->call( 'ai-assistant-mcp', array() )->get_error_code(), 'call() refuses before connecting, nothing sent' );
	$check( 'wpfactory_ai_invalid_email' === $ai->start_connect( 'not-an-email' )->get_error_code(), 'bad email refused here' );

	$check( true === $ai->start_connect( $email ), 'code emailed' );
	$mailpit = defined( 'WPFAI_MAILPIT' ) ? WPFAI_MAILPIT : 'http://127.0.0.1:10006';
	$list    = json_decode( wp_remote_retrieve_body( wp_remote_get( $mailpit . '/api/v1/messages?limit=1' ) ), true );
	$id      = $list['messages'][0]['ID'] ?? '';
	$mail    = json_decode( wp_remote_retrieve_body( wp_remote_get( $mailpit . '/api/v1/message/' . $id ) ), true );
	preg_match( '/\b(\d{6})\b/', (string) ( $mail['Text'] ?? '' ), $m );
	$check( strtolower( (string) ( $mail['To'][0]['Address'] ?? '' ) ) === strtolower( $email ) && ! empty( $m[1] ), 'code arrived by email' );

	$wrong = $ai->finish_connect( $email, '000000' === $m[1] ? '111111' : '000000' );
	$check( is_wp_error( $wrong ) && 'wpfactory_ai_invalid_code' === $wrong->get_error_code(), 'wrong code refused with a plain message: ' . ( is_wp_error( $wrong ) ? $wrong->get_error_message() : '' ) );

	$info = $ai->finish_connect( $email, $m[1] );
	$check( ! is_wp_error( $info ) && $info['connected'] && $info['email'] === $email, 'connected with the code' );
	$raw = get_option( 'wpfactory_ai_connection' );
	$check( 0 === strpos( (string) $raw['token'], 'enc1:' ) && false === strpos( (string) $raw['token'], 'wpfai_' ), 'token stored encrypted' );

	$moved = static function () {
		return 'https://moved.example';
	};
	add_filter( 'home_url', $moved );
	$check( ! $ai->is_connected() && $ai->connection()['address_changed'], 'a copy at another address must connect again' );
	remove_filter( 'home_url', $moved );
	$check( $ai->is_connected(), 'back at its address, connected again' );
} elseif ( 'spend' === $phase ) {
	$check( $ai->is_connected(), 'still connected' );
	$start = $ai->balance( 'ai-assistant-mcp', true );
	$check( ! is_wp_error( $start ) && $start['paid'] > 0, 'paid credits there: ' . ( is_wp_error( $start ) ? $start->get_error_message() : $start['balance'] ) );

	$r = $ai->call(
		'ai-assistant-mcp',
		array(
			'model'         => 'claude-haiku-5-5',
			'max_tokens'    => 60,
			'output_config' => array( 'effort' => 'low' ),
			'messages'      => array( array( 'role' => 'user', 'content' => 'In one short sentence, what is a WooCommerce product variation?' ) ),
		),
		array( 'task' => 'library-test' )
	);
	$check( ! is_wp_error( $r ) && '' !== (string) ( $r['message']['content'][0]['text'] ?? '' ), 'real AI answer: ' . ( is_wp_error( $r ) ? $r->get_error_message() : $r['message']['content'][0]['text'] ) );
	$check( ! is_wp_error( $r ) && $r['credits'] >= 1 && $r['balance'] === $start['balance'] - $r['credits'], 'charged ' . ( is_wp_error( $r ) ? '?' : $r['credits'] ) . ' credits, balance updated' );
	$check( ! isset( $r['message']['wpfactory'] ), 'the answer is the plain Anthropic message' );
	$check( ! is_wp_error( $r ) && $ai->balance( 'ai-assistant-mcp' )['balance'] === $r['balance'], 'cached balance follows the call' );

	$back = $ai->refund( $r['request_id'] );
	$check( ! is_wp_error( $back ) && $back['refunded'] === $r['credits'] && $back['balance'] === $start['balance'], 'refund by request id gives the credits back' );
	$again = $ai->refund( $r['request_id'] );
	$check( is_wp_error( $again ) && 'wpfactory_ai_already_refunded' === $again->get_error_code(), 'second refund refused' );

	$usage = $ai->usage();
	$check( ! is_wp_error( $usage ) && in_array( 'library-test', array_column( $usage['by_task'], 'task' ), true ), 'usage by task' );

	$big = $ai->call( 'ai-assistant-mcp', array( 'model' => 'claude-sonnet-5-5', 'max_tokens' => 32000, 'messages' => array( array( 'role' => 'user', 'content' => 'Hi' ) ) ) );
	$check( is_wp_error( $big ) && 'wpfactory_ai_insufficient_credits' === $big->get_error_code() && isset( $big->get_error_data()['estimate'] ), 'not enough credits: plain message with the estimate: ' . ( is_wp_error( $big ) ? $big->get_error_message() : '' ) );

	$check( false !== strpos( $ai->pill( 'ai-assistant-mcp' ), 'credits' ), 'credits pill' );

	delete_transient( 'wpfactory_ai_packs' );
	$packs = $ai->packs();
	$check( ! is_wp_error( $packs ) && array( 5, 10, 25, 50 ) === array_column( $packs['packs'], 'usd' ), 'credit packs listed' );
	$buy = $ai->checkout( 10 );
	if ( ! is_wp_error( $packs ) && $packs['open'] ) {
		$check( ! is_wp_error( $buy ) && 0 === strpos( $buy, 'https://checkout.stripe.com/' ), 'checkout opens a Stripe page' );
	} else {
		$check( is_wp_error( $buy ) && 'wpfactory_ai_checkout_unavailable' === $buy->get_error_code(), 'checkout says buying is not open yet (no Stripe keys on this server)' );
	}

	wp_set_current_user( 0 );
	$denied = rest_do_request( new WP_REST_Request( 'GET', '/wpfactory-ai/v1/status' ) );
	$check( in_array( $denied->get_status(), array( 401, 403 ), true ), 'status route closed to visitors' );
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	wp_set_current_user( (int) $admins[0] );
	$status = rest_do_request( new WP_REST_Request( 'GET', '/wpfactory-ai/v1/status' ) );
	$check( 200 === $status->get_status() && true === $status->get_data()['connected'], 'status route for administrators' );

	wp_set_current_user( 0 );
	$challenge = bin2hex( random_bytes( 16 ) );
	$req       = new WP_REST_Request( 'GET', '/wpfactory-ai/v1/site-check' );
	$req->set_param( 'challenge', $challenge );
	$proof = rest_do_request( $req );
	$token = \WPFactory\AI\Connection::token();
	$check( 200 === $proof->get_status() && hash_equals( hash_hmac( 'sha256', 'wpfactory-ai-site-check|' . $challenge, hash( 'sha256', $token ) ), (string) $proof->get_data()['proof'] ), 'site check answers the challenge' );
	$bad = new WP_REST_Request( 'GET', '/wpfactory-ai/v1/site-check' );
	$bad->set_param( 'challenge', '<script>' );
	$check( 400 === rest_do_request( $bad )->get_status(), 'site check refuses a malformed challenge' );

	wp_set_current_user( (int) $admins[0] );
	$ai->disconnect();
	$check( ! $ai->is_connected() && false === get_option( 'wpfactory_ai_connection' ), 'disconnect forgets the token' );
} else {
	WP_CLI::error( 'Use: connect <email> | spend' );
}

if ( $failed ) {
	WP_CLI::error( "$failed checks failed." );
}
WP_CLI::success( 'All checks passed.' );
