<?php
/**
 * The newest bundled copy wins. Plain PHP, no WordPress: php tests/versions-test.php
 *
 * @package WPFactory\AI
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['hooks'] = array();
/**
 * Stub.
 *
 * @param string   $hook     Hook.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 */
function add_action( $hook, $callback, $priority = 10 ) {
	$GLOBALS['hooks'][] = array( $hook, $priority );
}

$dir   = sys_get_temp_dir() . '/wpfai-versions-' . getmypid();
$files = array();
mkdir( $dir );
foreach ( array( '1.0.0', '1.2.0', '1.10.0', '1.2.0-beta' ) as $v ) {
	$files[ $v ] = "$dir/bootstrap-$v.php";
	file_put_contents( $files[ $v ], "<?php \$GLOBALS['loaded'][] = '$v';" );
}

require dirname( __DIR__ ) . '/src/class-wpfactory-ai-versions.php';
require dirname( __DIR__ ) . '/src/functions.php';
foreach ( $files as $v => $file ) {
	WPFactory_AI_Versions::register( $v, $file );
}
WPFactory_AI_Versions::load();
WPFactory_AI_Versions::load();

$fail = 0;
$ok   = function ( $cond, $what ) use ( &$fail ) {
	echo ( $cond ? 'PASS ' : 'FAIL ' ) . $what . "\n";
	$fail += $cond ? 0 : 1;
};
$ok( '1.10.0' === WPFactory_AI_Versions::loaded(), 'newest version wins (1.10.0 over 1.2.0, compared as versions)' );
$ok( array( '1.10.0' ) === $GLOBALS['loaded'], 'only the newest copy loads, once' );
$ok( array( array( 'plugins_loaded', PHP_INT_MAX ) ) === $GLOBALS['hooks'], 'loads once, at the end of plugins_loaded' );
$ok( function_exists( 'wpfactory_ai' ), 'wpfactory_ai() is defined' );

array_map( 'unlink', $files );
rmdir( $dir );
exit( $fail ? 1 : 0 );
