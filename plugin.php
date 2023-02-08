<?php

/**
 * `WP_Query` Search Operators plugin for WordPress
 *
 * @wordpress-plugin
 *
 * Plugin Name:          <code>WP_Query</code> Search Operators
 * Description:          Adds support for implementing search operators to refine results in WordPress.
 * Version:              1.0.0-alpha.1
 * Plugin URI:           https://github.com/wp-jazz/wp-query-search-operators
 * Update URI:           https://github.com/wp-jazz/wp-query-search-operators
 * Bitbucket Plugin URI: https://github.com/wp-jazz/wp-query-search-operators
 * Primary Branch:       main
 * Author:               WP Jazz
 * Author URI:           https://github.com/wp-jazz/
 * License:              MIT
 * Text Domain:          wp-jazz-query-search-operators
 * Requires PHP:         8.0.0
 * Requires at least:    5.9.0
 * Tested up to:         6.1.1
 */

declare( strict_types=1 );

namespace Jazz\WPQuerySearchOperators;

use function add_action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/namespace.php';

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );
