<?php
/**
 * Dashboard Vue App Entry Point.
 *
 * This file checks authentication and serves the Vue 3 SPA.
 *
 * @package Bookit_Booking_System
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-session.php';
require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-auth.php';

// Require authentication.
Bookit_Auth::require_auth();

// Get current staff.
$current_staff = Bookit_Auth::get_current_staff();

if ( ! $current_staff ) {
	wp_redirect( home_url( '/bookit-dashboard/' ) );
	exit;
}

// Notify extensions that the authenticated dashboard app has loaded.
do_action( 'bookit_dashboard_loaded', $current_staff );

// Get WordPress REST API nonce.
$rest_nonce = wp_create_nonce( 'wp_rest' );

$dashboard_js_data = array(
	'staff'     => $current_staff,
	'apiBase'   => rest_url( 'bookit/v1/dashboard' ),
	'restBase'  => rest_url( 'bookit/v1/' ),
	'nonce'     => $rest_nonce,
	'pluginUrl' => BOOKIT_PLUGIN_URL,
	'logoutUrl' => home_url( '/bookit-dashboard/logout/' ),
);

// Allow extensions to enrich dashboard bootstrap payload passed to Vue.
$dashboard_js_data = apply_filters( 'bookit_dashboard_js_data', $dashboard_js_data );

// Read Vite manifest to get hashed asset filenames.
$manifest_path = BOOKIT_PLUGIN_DIR . 'dashboard/dist/.vite/manifest.json';
$manifest      = array();
if ( file_exists( $manifest_path ) ) {
	$raw      = file_get_contents( $manifest_path ); // phpcs:ignore
	$manifest = json_decode( $raw, true ) ?? array();
}
$js_file  = $manifest['src/main.js']['file'] ?? 'index.js';
$css_file = isset( $manifest['src/main.js']['css'][0] )
	? $manifest['src/main.js']['css'][0]
	: 'style.css';

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Bookit Dashboard</title>

	<?php if ( file_exists( BOOKIT_PLUGIN_DIR . 'dashboard/dist/' . $css_file ) ) : ?>
		<link rel="stylesheet" href="<?php echo esc_url( BOOKIT_PLUGIN_URL . 'dashboard/dist/' . $css_file ); ?>">
	<?php endif; ?>

	<?php wp_print_styles(); ?>
</head>
<body>
	<div id="app"></div>

	<?php
	/**
	 * Allow extensions to inject mount points inside the dashboard layout.
	 * Extension Vue apps should add their <div id="bookit-{slug}-app"> here
	 * rather than via wp_footer, which places content outside the layout.
	 *
	 * @since 1.5.1
	 */
	do_action( 'bookit_dashboard_extension_content' );
	?>

	<!-- Inject session data for Vue -->
	<script>
		window.BOOKIT_DASHBOARD = {
			staff: <?php echo wp_json_encode( $dashboard_js_data['staff'] ?? array() ); ?>,
			apiBase: '<?php echo esc_js( $dashboard_js_data['apiBase'] ?? '' ); ?>',
			restBase: '<?php echo esc_js( $dashboard_js_data['restBase'] ?? '' ); ?>',
			nonce: '<?php echo esc_js( $dashboard_js_data['nonce'] ?? '' ); ?>',
			pluginUrl: '<?php echo esc_url( $dashboard_js_data['pluginUrl'] ?? '' ); ?>',
			logoutUrl: '<?php echo esc_url( $dashboard_js_data['logoutUrl'] ?? '' ); ?>',
			branding: <?php echo wp_json_encode(
				array(
					'logoUrl'          => esc_url( $dashboard_js_data['branding']['logoUrl'] ?? '' ),
					'primaryColour'    => sanitize_text_field( $dashboard_js_data['branding']['primaryColour'] ?? '#4F46E5' ),
					'businessName'     => sanitize_text_field( $dashboard_js_data['branding']['businessName'] ?? '' ),
					'poweredByVisible' => isset( $dashboard_js_data['branding']['poweredByVisible'] ) ? (bool) $dashboard_js_data['branding']['poweredByVisible'] : true,
				)
			); ?>
		};
	</script>

	<?php if ( file_exists( BOOKIT_PLUGIN_DIR . 'dashboard/dist/' . $js_file ) ) : ?>
		<script type="module" src="<?php echo esc_url( BOOKIT_PLUGIN_URL . 'dashboard/dist/' . $js_file ); ?>"></script>
	<?php else : ?>
		<script type="module" src="http://localhost:5173/@vite/client"></script>
		<script type="module" src="http://localhost:5173/src/main.js"></script>
	<?php endif; ?>
</body>
</html>
