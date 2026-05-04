<?php
/**
 * Payment Settings page: wrapper with tabs (Stripe, etc.).
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/admin/pages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'bookit-booking-system' ) );
}

$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'stripe';
$stripe_tab_url = add_query_arg( array( 'page' => 'bookit-payment-settings', 'tab' => 'stripe' ), admin_url( 'admin.php' ) );
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Payment Settings', 'bookit-booking-system' ); ?></h1>

	<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Payment settings tabs', 'bookit-booking-system' ); ?>">
		<a href="<?php echo esc_url( $stripe_tab_url ); ?>"
			class="nav-tab <?php echo $current_tab === 'stripe' ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Stripe', 'bookit-booking-system' ); ?>
		</a>
	</nav>

	<div class="bookit-payment-settings-tab-content" style="margin-top:1em;">
		<?php
		switch ( $current_tab ) {
			case 'stripe':
			default:
				bookit_render_stripe_settings_form();
				break;
		}
		?>
	</div>
</div>
