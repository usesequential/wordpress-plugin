<?php
// phpcs:disable -- test script, not shipped. Run by `wp eval-file`.
// First save of the option with a bad key: how many times does the sanitize callback run,
// and which settings errors does the page get?
delete_option( 'sequential_analytics_settings' );
Sequential_Analytics_Settings::register();
$GLOBALS['seq_calls'] = 0;
add_filter( 'sanitize_option_sequential_analytics_settings', function ( $v ) { $GLOBALS['seq_calls']++; return $v; }, 5 );
update_option( 'sequential_analytics_settings', array( 'key' => 'pk_live_1', 'api' => 'http://x.example', 'release' => 'first', 'skip_admins' => '1' ) );
echo 'sanitize calls: ' . $GLOBALS['seq_calls'] . "\n";
foreach ( get_settings_errors( 'sequential_analytics_settings' ) as $e ) {
	echo 'error: ' . $e['code'] . "\n";
}
echo 'stored: ' . wp_json_encode( get_option( 'sequential_analytics_settings' ) ) . "\n";
