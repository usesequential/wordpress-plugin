<?php
/**
 * Stored settings: names, defaults, validation.
 *
 * @package SequentialAnalytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and validates the plugin's single option.
 */
final class Sequential_Analytics_Options {

	/**
	 * Name of the option that holds all settings.
	 */
	const OPTION = 'sequential_analytics_settings';

	/**
	 * The hosted ingest host.
	 */
	const DEFAULT_API = 'https://api.usesequential.com';

	/**
	 * The SDK file on the Sequential CDN.
	 */
	const SDK_SRC = 'https://cdn.usesequential.com/sdk/sequential/v1/sequential.js';

	/**
	 * A project's "sequential" ingest key: the prefix, then 1 to 128 letters, digits, - or _.
	 *
	 * Sequential mints keys as the prefix plus 22 letters and digits. An operator can
	 * also set a key value by hand, so - and _ are accepted too. None of these
	 * characters can end an HTML attribute.
	 */
	const KEY_PATTERN = '/^sq_sequential_[A-Za-z0-9_-]{1,128}$/D';

	/**
	 * Longest release string the settings page accepts. The SDK accepts 1 to 64 printable ASCII characters
	 * (sequential.js reads data-release with /^[\x20-\x7E]{1,64}$/) and sends events without a release otherwise.
	 */
	const RELEASE_MAX_LENGTH = 64;

	/**
	 * A release the SDK accepts: 1 to 64 printable ASCII characters.
	 */
	const RELEASE_PATTERN = '/^[\x20-\x7E]{1,64}$/D';

	/**
	 * Default values for every setting.
	 *
	 * @return array{key: string, api: string, release: string, skip_admins: bool}
	 */
	public static function defaults() {
		return array(
			'key'         => '',
			'api'         => self::DEFAULT_API,
			'release'     => '',
			'skip_admins' => true,
		);
	}

	/**
	 * The stored settings, merged over the defaults.
	 *
	 * @return array{key: string, api: string, release: string, skip_admins: bool}
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$settings = array_merge( self::defaults(), array_intersect_key( $stored, self::defaults() ) );

		$settings['key']         = (string) $settings['key'];
		$settings['api']         = (string) $settings['api'];
		$settings['release']     = (string) $settings['release'];
		$settings['skip_admins'] = (bool) $settings['skip_admins'];

		return $settings;
	}

	/**
	 * Whether a value is a well-formed project key.
	 *
	 * @param mixed $key Value to check.
	 * @return bool
	 */
	public static function is_valid_key( $key ) {
		return is_string( $key ) && 1 === preg_match( self::KEY_PATTERN, $key );
	}

	/**
	 * Normalizes an ingest host to "https://host[:port][/path]", without a trailing slash.
	 *
	 * Returns an empty string when the value is not an https URL with a host,
	 * or when it carries credentials, a query or a fragment.
	 *
	 * @param mixed $url Value to check.
	 * @return string
	 */
	public static function normalize_api( $url ) {
		if ( ! is_string( $url ) ) {
			return '';
		}
		$url = trim( $url );
		if ( '' === $url || preg_match( '/[\s"\'<>\\\\]/', $url ) ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return '';
		}
		if ( ! isset( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return '';
		}
		if ( empty( $parts['host'] ) ) {
			return '';
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return '';
		}
		if ( ! preg_match( '/^[A-Za-z0-9.-]+$/', $parts['host'] ) ) {
			return '';
		}

		$normalized = 'https://' . strtolower( $parts['host'] );
		if ( isset( $parts['port'] ) ) {
			$normalized .= ':' . (int) $parts['port'];
		}
		if ( isset( $parts['path'] ) ) {
			$normalized .= rtrim( $parts['path'], '/' );
		}

		return $normalized;
	}

	/**
	 * Whether a release is one that the SDK accepts. The tag escapes it when it prints it.
	 *
	 * @param mixed $release Value to check (already trimmed).
	 * @return bool
	 */
	public static function is_valid_release( $release ) {
		return is_string( $release ) && 1 === preg_match( self::RELEASE_PATTERN, $release );
	}
}
