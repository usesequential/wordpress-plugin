<?php
/**
 * Plugin Name:       Sequential – Session Replay & Product Analytics
 * Plugin URI:        https://www.usesequential.com/docs/install
 * Description:       Adds the Sequential tag to your site: session replay and product analytics for your WordPress site.
 * Version:           1.0.0
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * Author:            Sequential
 * Author URI:        https://www.usesequential.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sequential-analytics
 *
 * @package SequentialAnalytics
 */

/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEQUENTIAL_ANALYTICS_VERSION', '1.0.0' );
define( 'SEQUENTIAL_ANALYTICS_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-sequential-analytics-options.php';
require_once __DIR__ . '/includes/class-sequential-analytics-settings.php';
require_once __DIR__ . '/includes/class-sequential-analytics-tag.php';

Sequential_Analytics_Settings::init();
Sequential_Analytics_Tag::init();
