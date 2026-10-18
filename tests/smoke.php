<?php
/**
 * End-to-end smoke test against a real WordPress, run by tests/smoke.sh with `wp eval-file`.
 *
 * Drives the settings page over HTTP as an administrator (nonce and all), then reads
 * the front-end head as a visitor and as the administrator.
 *
 * @package SequentialAnalytics
 */

// phpcs:disable -- test script, not shipped.

// wp eval-file runs this file inside a function: make the shared state global.
global $base, $failures;
$base     = 'http://web';
$failures = 0;

function seq_check( $ok, $label ) {
	global $failures;
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		$failures++;
	}
}

function seq_login( $user, $pass ) {
	global $base;
	$jar = array( new WP_Http_Cookie( array( 'name' => 'wordpress_test_cookie', 'value' => 'WP Cookie check' ) ) );
	$res = wp_remote_post(
		$base . '/wp-login.php',
		array(
			'body'        => array( 'log' => $user, 'pwd' => $pass, 'testcookie' => '1', 'wp-submit' => 'Log In' ),
			'cookies'     => $jar,
			'redirection' => 0, 'timeout' => 60,
		)
	);
	return wp_remote_retrieve_cookies( $res );
}

function seq_get( $path, $cookies = array() ) {
	global $base;
	$res = wp_remote_get( $base . $path, array( 'cookies' => $cookies, 'redirection' => 0, 'timeout' => 60 ) );
	if ( is_wp_error( $res ) ) {
		echo 'HTTP error on ' . $path . ': ' . $res->get_error_message() . "\n";
	}
	return array( wp_remote_retrieve_response_code( $res ), wp_remote_retrieve_body( $res ), $res );
}

function seq_settings_form( $cookies ) {
	list( $code, $body ) = seq_get( '/wp-admin/options-general.php?page=sequential-analytics', $cookies );
	preg_match( '/name="_wpnonce" value="([^"]+)"/', $body, $m );
	return array( $code, $body, isset( $m[1] ) ? $m[1] : '' );
}

function seq_save( $cookies, $fields, $nonce ) {
	global $base;
	$body = array(
		'option_page'      => 'sequential-analytics',
		'action'           => 'update',
		'_wp_http_referer' => '/wp-admin/options-general.php?page=sequential-analytics',
	);
	if ( null !== $nonce ) {
		$body['_wpnonce'] = $nonce;
	}
	foreach ( $fields as $k => $v ) {
		$body[ 'sequential_analytics_settings[' . $k . ']' ] = $v;
	}
	$res = wp_remote_post( $base . '/wp-admin/options.php', array( 'body' => $body, 'cookies' => $cookies, 'redirection' => 0, 'timeout' => 60 ) );
	if ( is_wp_error( $res ) ) {
		echo 'HTTP error on save: ' . $res->get_error_message() . "\n";
	}
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'sequential_analytics_settings', 'options' );
	return $res;
}

function seq_head( $html ) {
	$end = strpos( $html, '</head>' );
	return false === $end ? $html : substr( $html, 0, $end );
}

function seq_opt() {
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'notoptions', 'options' );
	wp_cache_delete( 'sequential_analytics_settings', 'options' );
	// No row yet means nothing was saved: WordPress skips the write when the value equals the default.
	return get_option( 'sequential_analytics_settings', array( 'key' => '', 'api' => 'https://api.usesequential.com', 'release' => '', 'skip_admins' => true ) );
}

function seq_after_save( $res, $cookies ) {
	$location = wp_remote_retrieve_header( $res, 'location' );
	$path     = preg_replace( '#^https?://[^/]+#', '', $location );
	list( , $body ) = seq_get( $path, $cookies );
	return array( $path, $body );
}

delete_option( 'sequential_analytics_settings' );

// 1. No key: nothing printed.
list( $code, $html ) = seq_get( '/' );
seq_check( 200 === $code, 'front page answers 200 (got ' . $code . ')' );
seq_check( false === strpos( $html, 'usesequential' ), 'no key saved: front page has no Sequential tag' );

// 2. Settings page as administrator.
$admin = seq_login( 'admin', 'admin-pass' );
list( $code, $page, $nonce ) = seq_settings_form( $admin );
seq_check( 200 === $code && false !== strpos( $page, 'Project key' ), 'admin sees Settings > Sequential (' . $code . ')' );
seq_check( '' !== $nonce, 'settings form carries a nonce' );
seq_check( false !== strpos( $page, 'is not on your pages' ), 'status says the tag is off' );

// 3. Invalid key and http host are rejected.
$res = seq_save( $admin, array( 'key' => 'pk_live_123', 'api' => 'http://api.usesequential.com', 'release' => 'first', 'skip_admins' => '1' ), $nonce );
$opt = seq_opt();
seq_check( '' === $opt['key'], 'invalid key not saved' );
seq_check( 'https://api.usesequential.com' === $opt['api'], 'http:// ingest host not saved; default kept' );
list( $after, $page ) = seq_after_save( $res, $admin );
echo 'redirected to: ' . $after . "\n";
seq_check( false !== strpos( $page, 'starts with sq_sequential_' ) && 1 === substr_count( $page, 'The project key was not saved' ), 'key error shown once' );
seq_check( 1 === substr_count( $page, 'The ingest host was not saved' ), 'host error shown once' );
seq_check( false === strpos( $page, 'Enter a project key' ), 'first save with a bad key: no spurious "Enter a project key" from the second sanitize pass' );

// 3b. Empty key: the required-key message.
$res = seq_save( $admin, array( 'key' => '', 'skip_admins' => '1' ), $nonce );
list( , $page ) = seq_after_save( $res, $admin );
seq_check( 1 === substr_count( $page, 'Enter a project key' ), 'empty key: required message shown once (seen ' . substr_count( $page, 'Enter a project key' ) . ')' );

// 4. Valid save, with a hostile release value.
$key = 'sq_sequential_AbC123xyz0AbC123xyz0Ab';
$res4 = seq_save( $admin, array( 'key' => $key, 'api' => 'https://API.usesequential.com/', 'release' => '2.4.1"><script>alert(1)</script>', 'skip_admins' => '1' ), $nonce );
$opt = seq_opt();
seq_check( $key === $opt['key'], 'valid key saved' );
seq_check( 'https://api.usesequential.com' === $opt['api'], 'host normalized to ' . $opt['api'] );
seq_check( true === $opt['skip_admins'], 'skip_admins saved on' );
list( , $page ) = seq_after_save( $res4, $admin );
seq_check( false !== strpos( $page, 'Settings saved' ) && false === strpos( $page, 'notice-error' ) && false !== strpos( $page, 'is on your site' ), 'valid save: "Settings saved", no error, status on' );
echo 'saved release: ' . var_export( $opt['release'], true ) . "\n";

// 5. Visitor gets the exact snippet.
list( , $html ) = seq_get( '/' );
$head = seq_head( $html );
$stub = "(function(w){if(w.sequential)return;var q=[],s=w.sequential={_q:q},\n"
	. "m=['capture','identify','account','register','unregister','reset','optOut','optIn','captureException'];\n"
	. "for(var i=0;i<m.length;i++)(function(n){s[n]=function(){q.push([n,arguments])}})(m[i]);\n"
	. "s.flush=function(){q.push(['flush',arguments]);return typeof Promise=='function'?Promise.resolve():{then:function(f){if(f)f()}}}})(window);";
// The comment and the stub are compared byte for byte. The SDK tag is compared as a set of
// attributes: WordPress 7.x prints them in a different order than 5.7 and 6.8, which HTML ignores.
$expected = "<!-- Sequential · analytics and session replay -->\n<script>\n" . $stub . "\n</script>\n";
$expected_attrs = array(
	'async'        => '',
	'src'          => 'https://cdn.usesequential.com/sdk/sequential/v1/sequential.js',
	'data-key'     => $key,
	'data-api'     => 'https://api.usesequential.com',
	'data-release' => esc_attr( $opt['release'] ),
);
ksort( $expected_attrs );
$pos_stub = strpos( $head, $expected );
seq_check( false !== $pos_stub, 'visitor: head contains the exact comment and stub' );
if ( false === $pos_stub ) {
	echo "--- expected ---\n" . $expected . "--- end ---\n";
}
$after_stub = false === $pos_stub ? '' : substr( $head, $pos_stub + strlen( $expected ) );
$attrs      = null;
if ( preg_match( '#^<script((?: [a-z-]+(?:="[^"]*")?)+)></script>\n#', $after_stub, $tm ) ) {
	preg_match_all( '# ([a-z-]+)(?:="([^"]*)")?#', $tm[1], $am, PREG_SET_ORDER );
	$attrs = array();
	foreach ( $am as $a ) {
		$attrs[ $a[1] ] = isset( $a[2] ) ? $a[2] : '';
	}
	ksort( $attrs );
}
seq_check( $expected_attrs === $attrs, 'visitor: SDK tag right after the stub, with exactly async, src, data-key, data-api, data-release' );
if ( $expected_attrs !== $attrs ) {
	echo "--- after stub ---\n" . substr( $after_stub, 0, 400 ) . "\n--- end ---\n";
}
seq_check( substr_count( $html, 'cdn.usesequential.com' ) === 1, 'visitor: SDK tag printed once' );
seq_check( false === strpos( $html, 'alert(1)</script>' ), 'release value cannot break out of the attribute' );
$pos_tag  = strpos( $head, '<!-- Sequential' );
$tag_end  = strpos( $head, '</script>', strpos( $head, 'cdn.usesequential.com' ) );
echo "--- printed tag (visitor) ---\n" . substr( $head, $pos_tag, $tag_end + 10 - $pos_tag ) . "--- end ---\n";
echo 'tag offset in head: ' . $pos_tag . ' of ' . strlen( $head ) . "\n";

// 6. Logged-in administrator is skipped.
list( , $html ) = seq_get( '/', $admin );
seq_check( false !== strpos( $html, 'wp-admin-bar' ) || false !== strpos( $html, 'admin-bar' ), 'admin request is logged in' );
seq_check( false === strpos( $html, 'cdn.usesequential.com' ), 'administrator skipped while the toggle is on' );

// 7. Toggle off: administrator gets the tag; empty release drops the attribute.
seq_save( $admin, array( 'key' => $key, 'api' => '', 'release' => '' ), $nonce );
$opt = seq_opt();
seq_check( false === $opt['skip_admins'] && 'https://api.usesequential.com' === $opt['api'], 'toggle off saved; empty host falls back to default' );
list( , $html ) = seq_get( '/', $admin );
seq_check( false !== strpos( $html, 'data-key="' . $key . '"' ) && false === strpos( $html, 'data-release' ), 'administrator gets the tag when the toggle is off; no data-release when empty' );

// 8. Missing nonce is refused.
$res = seq_save( $admin, array( 'key' => 'sq_sequential_Other000000000000000000' ), null );
$opt = seq_opt();
seq_check( $key === $opt['key'], 'save without nonce changes nothing (HTTP ' . wp_remote_retrieve_response_code( $res ) . ')' );

// 9. A subscriber cannot open or save the page.
$sub = seq_login( 'sub', 'sub-pass' );
list( $code, $page ) = seq_settings_form( $sub );
seq_check( false === strpos( $page, 'Project key' ), 'subscriber cannot open the page (HTTP ' . $code . ')' );
$res = seq_save( $sub, array( 'key' => 'sq_sequential_Other000000000000000000' ), $nonce );
$opt = seq_opt();
seq_check( $key === $opt['key'], 'subscriber save changes nothing (HTTP ' . wp_remote_retrieve_response_code( $res ) . ')' );

// 10. Settings link on the Plugins screen.
list( , $page ) = seq_get( '/wp-admin/plugins.php', $admin );
seq_check( false !== strpos( $page, 'options-general.php?page=sequential-analytics' ), 'Plugins screen has the Settings link' );

// 11. Key rule: minted keys and hand-set keys with - or _ pass; anything that is not one key fails.
seq_check( Sequential_Analytics_Options::is_valid_key( 'sq_sequential_demo-key_1' ), 'key rule: - and _ accepted' );
seq_check( ! Sequential_Analytics_Options::is_valid_key( $key . "\n" ), 'key rule: trailing newline rejected' );
seq_check( ! Sequential_Analytics_Options::is_valid_key( 'sq_sequential_' ), 'key rule: prefix alone rejected' );
seq_check( ! Sequential_Analytics_Options::is_valid_key( 'sq_sequential_' . str_repeat( 'a', 129 ) ), 'key rule: over 128 characters rejected' );
seq_check( ! Sequential_Analytics_Options::is_valid_key( 'sq_sequential_a"b' ), 'key rule: quote rejected' );

echo $failures ? "FAILURES: $failures\n" : "ALL PASS\n";
if ( $failures ) {
	exit( 1 );
}
