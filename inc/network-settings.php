<?php
/**
 * Description: Create the network-wide admin settings screen (multisite only).
 *
 * The Settings API's options.php flow only persists per-site options, so the
 * network settings are saved by hand against a network_admin_edit_* action.
 */

// Block direct requests
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

/**
 * Add the network settings page under the Network Admin → Settings menu.
 */
function cosi_tags_network_settings_menu_item() {
	add_submenu_page(
		'settings.php', // parent (Network Admin → Settings)
		__( 'Così Tags', 'cosi-tags' ), // page title
		__( 'Così Tags', 'cosi-tags' ), // menu title
		'manage_network_options', // capability
		'cosi-tags-network-settings', // menu slug
		'cosi_tags_network_settings_page' // callback
	);
}
add_action( 'network_admin_menu', 'cosi_tags_network_settings_menu_item' );


/**
 * Persist the network settings.
 *
 * Hooked to network_admin_edit_cosi_tags_network_settings, which fires when the
 * form below posts to edit.php?action=cosi_tags_network_settings.
 */
function cosi_tags_network_settings_save() {
	if ( ! current_user_can( 'manage_network_options' ) ) {
		wp_die( 'You do not have permission to change these settings.' );
	}
	check_admin_referer( 'cosi_tags_network_settings' );

	update_site_option( 'cosi_tags_id', cosi_tags_sanitize_ids( isset( $_POST['cosi_tags_id'] ) ? wp_unslash( $_POST['cosi_tags_id'] ) : '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cosi_tags_sanitize_ids() validates each ID against the GTM-XXXX pattern.
	update_site_option( 'cosi_tags_defer', cosi_tags_sanitize_checkbox( isset( $_POST['cosi_tags_defer'] ) ? wp_unslash( $_POST['cosi_tags_defer'] ) : FALSE ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cosi_tags_sanitize_checkbox() casts the value to a boolean.
	update_site_option( 'cosi_tags_prevent_overrides', cosi_tags_sanitize_checkbox( isset( $_POST['cosi_tags_prevent_overrides'] ) ? wp_unslash( $_POST['cosi_tags_prevent_overrides'] ) : FALSE ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cosi_tags_sanitize_checkbox() casts the value to a boolean.

	wp_safe_redirect( add_query_arg(
		array( 'page' => 'cosi-tags-network-settings', 'updated' => 'true' ),
		network_admin_url( 'settings.php' )
	) );
	exit;
}
add_action( 'network_admin_edit_cosi_tags_network_settings', 'cosi_tags_network_settings_save' );


/**
 * Render the network settings page.
 */
function cosi_tags_network_settings_page() {

	if ( ! current_user_can( 'manage_network_options' ) ) {
		return;
	}

?>
<div class="wrap">
<h1>Così Tags network settings</h1>

<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only UI flag set by our own nonce-verified redirect in cosi_tags_network_settings_save(); nothing is written based on it. ?>
	<div id="setting-message" class="updated notice is-dismissible"><p><strong>Settings saved.</strong></p></div>
<?php endif; ?>

<p>These values apply to every site in the network. Individual sites can override them unless you prevent overrides below.</p>

<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=cosi_tags_network_settings' ) ); ?>">
	<?php wp_nonce_field( 'cosi_tags_network_settings' ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="cosi-tags-id">Container ID</label></th>
			<td>
				<input type="text" class="regular-text" aria-describedby="cosi-tags-id-desc" name="cosi_tags_id" id="cosi-tags-id" value="<?php echo esc_attr( get_site_option( 'cosi_tags_id', '' ) ); ?>">
				<p class="description" id="cosi-tags-id-desc">Enter your Google Tag Manager Container ID. It should look like GTM-Z3V1L. Separate multiple IDs with commas.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Defer loading</th>
			<td>
				<label for="cosi-tags-defer">
					<input type="checkbox" aria-describedby="cosi-tags-defer-desc" name="cosi_tags_defer" id="cosi-tags-defer" value="1" <?php checked( get_site_option( 'cosi_tags_defer', FALSE ) ); ?>>
					Load Google Tag Manager after the user interacts with the page.
				</label>
				<p class="description" id="cosi-tags-defer-desc">Improves page speed by filtering out bots and bounce traffic — your pageview and session counts may drop as a result.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Site overrides</th>
			<td>
				<label for="cosi-tags-prevent-overrides">
					<input type="checkbox" aria-describedby="cosi-tags-prevent-overrides-desc" name="cosi_tags_prevent_overrides" id="cosi-tags-prevent-overrides" value="1" <?php checked( get_site_option( 'cosi_tags_prevent_overrides', FALSE ) ); ?>>
					Prevent individual site overrides
				</label>
				<p class="description" id="cosi-tags-prevent-overrides-desc">Force every site in the network to use these settings.</p>
			</td>
		</tr>
	</table>
	<?php submit_button( 'Save Settings' ); ?>
</form>

</div>
<?php }