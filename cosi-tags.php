<?php
/*
Plugin Name: Così Tags
Plugin URI: 
Description: A simple Google Tag Manager code inserter
Version: 1.0
Author: John Pennypacker
Author URI: https://pennypacker.net
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: cosi-tags
*/

// Block direct requests
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

define( 'COSITAGS_PATH', plugin_dir_path( __FILE__ ) );
define( 'COSITAGS_URL', plugin_dir_url( __FILE__ ) );
define( 'COSITAGS_VERSION', get_file_data( __FILE__, ['Version'], false )[0] );

// the settings screen
if ( is_admin() ) {
	include( 'inc/settings.php' );
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'cosi_tags_plugin_action_links' );
	// the network-wide settings screen only exists on multisite
	if ( is_multisite() ) {
		include( 'inc/network-settings.php' );
		add_filter( 'network_admin_plugin_action_links_' . plugin_basename( __FILE__ ), 'cosi_tags_network_plugin_action_links' );
	}
}

/**
 * Resolve a stored setting, honoring multisite network defaults and per-site overrides.
 *
 * On a single-site install this is simply get_option(). On multisite a site
 * inherits every network value unless it has opted to override them — and the
 * network admin can forbid overrides entirely.
 */
function cosi_tags_get_option( $key, $default = FALSE ) {
	if ( ! is_multisite() ) {
		return get_option( $key, $default );
	}
	// The network can force its values on every site.
	if ( get_site_option( 'cosi_tags_prevent_overrides', FALSE ) ) {
		return get_site_option( $key, $default );
	}
	// Otherwise the site inherits the network value unless it overrides.
	if ( get_option( 'cosi_tags_override', FALSE ) ) {
		return get_option( $key, $default );
	}
	return get_site_option( $key, $default );
}

/**
 * Get the resolved Container IDs as an array of individual, trimmed IDs.
 * The value is stored as a comma-separated string but may hold several IDs.
 */
function cosi_tags_get_ids() {
	$raw = cosi_tags_get_option( 'cosi_tags_id', FALSE );
	if ( empty( $raw ) ) {
		return array();
	}
	return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
}

/**
 * A wrapper to get the resolved Defer loading flag.
 */
function cosi_tags_get_defer() {
	return (bool) cosi_tags_get_option( 'cosi_tags_defer', FALSE );
}

/**
 * Adds the js version of the GTM code to the <head>. 
 */
function cosi_tags_head() {
	$ids   = cosi_tags_get_ids();
	$defer = cosi_tags_get_defer();

	if ( empty( $ids ) ) {
		return;
	}

	// Build one init call per container so multiple IDs all load.
	$init_calls = '';
	foreach ( $ids as $id ) {
		$init_calls .= ' initGTM( window, document, "script", "dataLayer", "' . esc_js( $id ) . '" );' . "\n";
	}

	echo '<!-- Così Tags -->
<script>
	// this is the guts of the default anonymous function from the Googz.
	function initGTM( w, d, s, l, i ) {
		w[l] = w[l] || [];
		w[l].push({ "gtm.start": new Date().getTime(), event: "gtm.js" });
		var f = d.getElementsByTagName(s)[0],
			j = d.createElement(s),
			dl = l != "dataLayer" ? "&l=" + l : "";
		j.defer = true;
		j.src = "https://www.googletagmanager.com/gtm.js?id=" + i + dl;
		f.parentNode.insertBefore( j, f );
	}
';

	$output = "\n%s"; // default value calls initGTM immediately.
	if ( $defer ) {
		$output = '
// based on code from Monmouth, it works great.
window.addEventListener("loadTracking", function(event) {%s}, false);

const loadTracking = new Event( "loadTracking" );
const triggerEvents = [
	"keydown", "mousedown", "mousemove", "touchmove", "touchstart", "touchend", "wheel", "visibilitychange"
];

function triggerTrackingScriptLoad() {
	// remove listeners for future loadTracking events
	triggerEvents.forEach( event => document.removeEventListener( event, triggerTrackingScriptLoad, false ) );
	// fire loadTracking event
	window.dispatchEvent( loadTracking );
}

// Parse the query string from the URL
const trackingURLParams = new URLSearchParams( window.location.search );

// Check if "sgtm=nodefer" is in the query string to ensure GTM loading
if ( "nodefer" === trackingURLParams.get( "sgtm" ) || "nodefer" === trackingURLParams.get( "cosi-tags" ) ) {
	window.addEventListener( "load", function( event ) {
		window.dispatchEvent( loadTracking );
	});
} else {
	triggerEvents.forEach( event => document.addEventListener( event, triggerTrackingScriptLoad, {
		passive: true
	}));
}
		';
	}
	echo sprintf( $output, $init_calls ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each interpolated ID was already run through esc_js() when $init_calls was built above.

	echo '</script>
<!-- End Così Tags -->
';

}
add_action( 'wp_head', 'cosi_tags_head', 0 );

/**
 * Adds the non-js version of the GTM code to the <body>. 
 */
function cosi_tags_body() {
	$ids = cosi_tags_get_ids();
	if ( empty( $ids ) ) {
		return;
	}
	echo '<!-- Così Tags (noscript) -->';
	foreach ( $ids as $id ) {
		echo '
		<noscript><iframe src="' . esc_url( 'https://www.googletagmanager.com/ns.html?id=' . $id ) . '"
		height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
	}
	echo '
		<!-- End Così Tags (noscript) -->';
}
add_action( 'wp_body_open', 'cosi_tags_body' );

