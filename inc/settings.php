<?php
/**
 * Description: Create the per-site admin settings screen
 */

// Block direct requests
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * Add the settings page to the settings menu.
 * @see https://developer.wordpress.org/reference/functions/add_options_page/
 */
function cosi_tags_settings_menu_item() {
	add_options_page(
		__( 'Così Tags', 'cosi-tags' ), // page title
		__( 'Così Tags', 'cosi-tags' ), // menu title
		'manage_options', // capability
		'cosi-tags-settings', // menu slug
		'cosi_tags_settings_page' // callback
	);
}
add_action( 'admin_menu', 'cosi_tags_settings_menu_item' );


/**
 * Add a "Settings" link to this plugin's row on the (per-site) Plugins screen.
 *
 * @param string[] $links
 * @return string[]
 */
function cosi_tags_plugin_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=cosi-tags-settings' ) ) . '">' . __( 'Settings', 'cosi-tags' ) . '</a>';
	array_push( $links, $settings_link );
	return $links;
}


/**
 * Register the settings with WordPress.
 *
 * Standalone (non-multisite) installs save through the Settings API / options.php.
 * On multisite the per-site form is saved by hand (see cosi_tags_settings_page) so it
 * can delete the site options when a site stops overriding the network.
 */
function cosi_tags_settings_init() {
	register_setting(
		'cosi_tags_settings', // option group
		'cosi_tags_id', // option name
		array(
			'sanitize_callback' => 'cosi_tags_sanitize_ids',
			'show_in_rest' => TRUE,
			'type' => 'string'
		)
	);
	register_setting(
		'cosi_tags_settings', // option group
		'cosi_tags_defer', // option name
		array(
			'sanitize_callback' => 'cosi_tags_sanitize_checkbox',
			'show_in_rest' => TRUE,
			'type' => 'boolean',
			'default' => FALSE
		)
	);

	add_settings_section(
		'cosi_tags_settings_section', // section id
		'', // title (none — the page <h1> is enough)
		'__return_false', // no intro callback
		'cosi_tags_settings' // page slug (matches do_settings_sections)
	);

	add_settings_field(
		'cosi_tags_id', // field id
		'Container ID', // label (rendered in the <th>)
		'cosi_tags_field_id', // render callback
		'cosi_tags_settings', // page slug
		'cosi_tags_settings_section', // section id
		array( 'label_for' => 'cosi-tags-id' )
	);

	add_settings_field(
		'cosi_tags_defer', // field id
		'Defer loading', // label (rendered in the <th>)
		'cosi_tags_field_defer', // render callback
		'cosi_tags_settings', // page slug
		'cosi_tags_settings_section' // section id
	);
}
add_action( 'admin_init', 'cosi_tags_settings_init' );


/**
 * Sanitize a checkbox value into a boolean.
 *
 * @param string $value
 * @return bool
 */
function cosi_tags_sanitize_checkbox( $value ) {
	return ! empty( $value );
}


/**
 * Sanitize a comma-separated list of GTM Container IDs.
 *
 * Splits on commas, trims, upper-cases, validates each against the GTM-XXXX
 * shape, drops anything invalid or duplicated, and returns a normalized,
 * comma-separated string.
 *
 * @param string $value
 * @return string
 */
function cosi_tags_sanitize_ids( $value ) {
	$ids   = array_map( 'trim', explode( ',', (string) $value ) );
	$valid = array();
	foreach ( $ids as $id ) {
		$id = strtoupper( $id );
		if ( preg_match( '/^GTM-[A-Z0-9]+$/', $id ) && ! in_array( $id, $valid, TRUE ) ) {
			$valid[] = $id;
		}
	}
	return implode( ', ', $valid );
}


/**
 * Render the Container ID field (Settings API callback, standalone installs).
 */
function cosi_tags_field_id() {
	?>
	<input type="text" class="regular-text" aria-describedby="cosi-tags-id-desc" name="cosi_tags_id" id="cosi-tags-id" value="<?php echo esc_attr( get_option( 'cosi_tags_id', '' ) ); ?>">
	<p class="description" id="cosi-tags-id-desc">Enter your Google Tag Manager Container ID. It should look like GTM-Z3V1L. Separate multiple IDs with commas.</p>
	<?php
}


/**
 * Render the Defer loading field (Settings API callback, standalone installs).
 */
function cosi_tags_field_defer() {
	?>
	<label for="cosi-tags-defer">
		<input type="checkbox" aria-describedby="cosi-tags-defer-desc" name="cosi_tags_defer" id="cosi-tags-defer" value="1" <?php checked( get_option( 'cosi_tags_defer', FALSE ) ); ?>>
		Load Google Tag Manager after the user interacts with the page.
	</label>
	<p class="description" id="cosi-tags-defer-desc">Improves page speed by filtering out bots and bounce traffic — your pageview and session counts may drop as a result.</p>
	<?php
}


/**
 * Render the settings page.
 *
 * Three shapes:
 *   - standalone install      → plain Container ID + Defer fields
 *   - multisite, can override → one override toggle that activates the fields
 *   - multisite, locked       → read-only view of the enforced network values
 */
function cosi_tags_settings_page() {

	if ( ! current_user_can( 'manage_options' ) ) {
		echo '<div id="setting-message-denied" class="updated settings-error notice is-dismissible">
<p><strong>You do not have permission to use this form.</strong></p>
<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
		return;
	}

	if ( is_multisite() ) {
		cosi_tags_settings_page_network_member();
		return;
	}

	cosi_tags_settings_page_standalone();
}


/**
 * Per-site settings on a standalone (non-multisite) install: just the fields.
 */
function cosi_tags_settings_page_standalone() {
?>
<div class="wrap">
<h1>Così Tags settings</h1>

<form method="post" action="options.php">
	<?php settings_fields( 'cosi_tags_settings' ); ?>
	<?php do_settings_sections( 'cosi_tags_settings' ); ?>
	<?php submit_button( 'Save Settings' ); ?>
</form>

</div>
<?php
}


/**
 * Per-site settings for a site that belongs to a network.
 */
function cosi_tags_settings_page_network_member() {

	// When the network forbids overrides, this page is read-only.
	if ( get_site_option( 'cosi_tags_prevent_overrides', FALSE ) ) {
		cosi_tags_settings_page_locked();
		return;
	}

	// Handle our own save so we can delete the site options when not overriding.
	if ( isset( $_POST['cosi_tags_settings_submit'] ) ) {
		check_admin_referer( 'cosi_tags_site_settings' );
		if ( ! empty( $_POST['cosi_tags_override'] ) ) {
			update_option( 'cosi_tags_override', TRUE );
			update_option( 'cosi_tags_id', cosi_tags_sanitize_ids( isset( $_POST['cosi_tags_id'] ) ? wp_unslash( $_POST['cosi_tags_id'] ) : '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cosi_tags_sanitize_ids() validates each ID against the GTM-XXXX pattern.
			update_option( 'cosi_tags_defer', cosi_tags_sanitize_checkbox( isset( $_POST['cosi_tags_defer'] ) ? wp_unslash( $_POST['cosi_tags_defer'] ) : FALSE ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cosi_tags_sanitize_checkbox() casts the value to a boolean.
		} else {
			// Stop overriding: fall back to the network by removing the site values.
			delete_option( 'cosi_tags_override' );
			delete_option( 'cosi_tags_id' );
			delete_option( 'cosi_tags_defer' );
		}
		echo '<div id="setting-message" class="updated notice is-dismissible"><p><strong>Settings saved.</strong></p></div>';
	}

	$overriding = get_option( 'cosi_tags_override', FALSE );
	$net_id     = get_site_option( 'cosi_tags_id', '' );
	$net_defer  = get_site_option( 'cosi_tags_defer', FALSE );

	wp_enqueue_script( 'cosi_tags_admin', COSITAGS_URL . 'assets/admin.js',  [], COSITAGS_VERSION, FALSE );
	wp_enqueue_style(  'cosi_tags_admin', COSITAGS_URL . 'assets/admin.css', [], COSITAGS_VERSION );

?>
<div class="wrap">
<h1>Così Tags settings</h1>

<form method="post" action="">
	<?php wp_nonce_field( 'cosi_tags_site_settings' ); ?>

	<p>
		<label for="cosi-tags-override">
			<input type="checkbox" id="cosi-tags-override" name="cosi_tags_override" value="1" <?php checked( $overriding ); ?>>
			Override the network Così Tags settings for this site.
		</label>
	</p>

	<fieldset id="cosi-tags-fields"<?php disabled( ! $overriding ); ?>>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cosi-tags-id">Container ID</label></th>
				<td>
					<input type="text" class="regular-text" aria-describedby="cosi-tags-id-desc" name="cosi_tags_id" id="cosi-tags-id" value="<?php echo esc_attr( get_option( 'cosi_tags_id', '' ) ); ?>">
					<p class="description" id="cosi-tags-id-desc">Enter your Google Tag Manager Container ID. It should look like GTM-Z3V1L. Separate multiple IDs with commas.<br>Network default: <code><?php echo esc_html( $net_id ?: '(not set)' ); ?></code></p>
				</td>
			</tr>
			<tr>
				<th scope="row">Defer loading</th>
				<td>
					<label for="cosi-tags-defer">
						<input type="checkbox" aria-describedby="cosi-tags-defer-desc" name="cosi_tags_defer" id="cosi-tags-defer" value="1" <?php checked( get_option( 'cosi_tags_defer', FALSE ) ); ?>>
						Load Google Tag Manager after the user interacts with the page.
					</label>
					<p class="description" id="cosi-tags-defer-desc">Improves page speed by filtering out bots and bounce traffic — your pageview and session counts may drop as a result.<br>Network default: <code><?php echo $net_defer ? 'On' : 'Off'; ?></code></p>
				</td>
			</tr>
		</table>
	</fieldset>

	<input type="hidden" name="cosi_tags_settings_submit" value="1">
	<?php submit_button( 'Save Settings' ); ?>
</form>

</div>
<?php
}


/**
 * Read-only per-site view when the network has locked the settings.
 */
function cosi_tags_settings_page_locked() {
?>
<div class="wrap">
<h1>Così Tags settings</h1>

<div class="notice notice-info inline">
	<p>These settings are managed by your network administrator and can't be changed here.</p>
</div>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row">Container ID</th>
		<td><code><?php echo esc_html( get_site_option( 'cosi_tags_id', '' ) ?: '(not set)' ); ?></code></td>
	</tr>
	<tr>
		<th scope="row">Defer loading</th>
		<td><?php echo get_site_option( 'cosi_tags_defer', FALSE ) ? 'On' : 'Off'; ?></td>
	</tr>
</table>

</div>
<?php
}
