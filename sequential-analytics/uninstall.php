<?php
/**
 * Removes the plugin's settings when the plugin is deleted.
 *
 * @package SequentialAnalytics
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	$sequential_analytics_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $sequential_analytics_site_ids as $sequential_analytics_site_id ) {
		switch_to_blog( $sequential_analytics_site_id );
		delete_option( 'sequential_analytics_settings' );
		restore_current_blog();
	}
} else {
	delete_option( 'sequential_analytics_settings' );
}
