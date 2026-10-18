<?php
/**
 * Prints the Sequential tag in the front-end head.
 *
 * @package SequentialAnalytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prints the Sequential install snippet with the saved settings.
 */
final class Sequential_Analytics_Tag {

	/**
	 * The loader stub from the Sequential install snippet, unchanged.
	 *
	 * It defines window.sequential before the SDK file loads. Each method
	 * (capture, identify, account, register, unregister, reset, optOut, optIn,
	 * captureException) pushes [name, arguments] onto a queue, which the SDK
	 * replays in order when it loads. flush() is queued too and returns a
	 * resolved Promise, so sequential.flush().then(...) does not throw before
	 * the SDK loads. When window.sequential exists already, the stub does nothing.
	 */
	const STUB = <<<'JS'
(function(w){if(w.sequential)return;var q=[],s=w.sequential={_q:q},
m=['capture','identify','account','register','unregister','reset','optOut','optIn','captureException'];
for(var i=0;i<m.length;i++)(function(n){s[n]=function(){q.push([n,arguments])}})(m[i]);
s.flush=function(){q.push(['flush',arguments]);return typeof Promise=='function'?Promise.resolve():{then:function(f){if(f)f()}}}})(window);
JS;

	/**
	 * Hooks the tag into the front-end head.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_tag' ), 1 );
	}

	/**
	 * The attributes for the SDK script tag, or null when the tag must not print.
	 *
	 * @param array{key: string, api: string, release: string, skip_admins: bool} $settings Saved settings.
	 * @return array<string, string|bool>|null
	 */
	public static function sdk_attributes( $settings ) {
		if ( ! Sequential_Analytics_Options::is_valid_key( $settings['key'] ) ) {
			return null;
		}
		$api = Sequential_Analytics_Options::normalize_api( $settings['api'] );
		if ( '' === $api ) {
			return null;
		}

		$attributes = array(
			'async'    => true,
			'src'      => esc_url( Sequential_Analytics_Options::SDK_SRC ),
			'data-key' => $settings['key'],
			'data-api' => esc_url( $api ),
		);
		if ( '' !== $settings['release'] ) {
			$attributes['data-release'] = $settings['release'];
		}

		return $attributes;
	}

	/**
	 * Prints the snippet, or nothing when there is no valid key or the viewer is skipped.
	 */
	public static function print_tag() {
		$settings = Sequential_Analytics_Options::get();

		if ( $settings['skip_admins'] && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return;
		}

		$attributes = self::sdk_attributes( $settings );
		if ( null === $attributes ) {
			return;
		}

		// wp_print_script_tag() escapes each attribute value with esc_attr().
		echo "<!-- Sequential · analytics and session replay -->\n";
		wp_print_inline_script_tag( self::STUB );
		wp_print_script_tag( $attributes );
	}
}
