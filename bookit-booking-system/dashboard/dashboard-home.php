<?php
/**
 * Dashboard home page (after login).
 *
 * @package Booking_System
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-session.php';
require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-auth.php';

Bookit_Auth::require_auth();

$staff = Bookit_Auth::get_current_staff();

get_header();
?>

<div class="booking-dashboard-wrapper">
	<div class="booking-dashboard-header">
		<h1><?php echo esc_html__( 'Booking System Dashboard', 'bookit-booking-system' ); ?></h1>
		<div class="booking-dashboard-user">
			<span>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: staff display name */
						__( 'Welcome, %s', 'bookit-booking-system' ),
						isset( $staff['name'] ) ? $staff['name'] : ''
					)
				);
				?>
			</span>
			<span class="booking-user-role">
				<?php echo esc_html( isset( $staff['role'] ) ? '(' . ucfirst( $staff['role'] ) . ')' : '' ); ?>
			</span>
			<a href="<?php echo esc_url( home_url( '/bookit-dashboard/logout/' ) ); ?>" class="booking-logout-link">
				<?php echo esc_html__( 'Logout', 'bookit-booking-system' ); ?>
			</a>
		</div>
	</div>

	<div class="booking-dashboard-content">
		<h2><?php echo esc_html__( 'Dashboard Home', 'bookit-booking-system' ); ?></h2>
		<p><?php echo esc_html__( 'You are successfully logged into the dashboard!', 'bookit-booking-system' ); ?></p>

		<div class="booking-dashboard-stats">
			<div class="booking-stat-card">
				<h3><?php echo esc_html__( "Today's Bookings", 'bookit-booking-system' ); ?></h3>
				<p class="stat-number">0</p>
			</div>
			<div class="booking-stat-card">
				<h3><?php echo esc_html__( 'Pending Bookings', 'bookit-booking-system' ); ?></h3>
				<p class="stat-number">0</p>
			</div>
			<div class="booking-stat-card">
				<h3><?php echo esc_html__( 'Total Revenue (This Month)', 'bookit-booking-system' ); ?></h3>
				<p class="stat-number">£0.00</p>
			</div>
		</div>

		<p><em><?php echo esc_html__( 'Note: Dashboard features will be implemented in Sprint 4. This is the authentication foundation.', 'bookit-booking-system' ); ?></em></p>
	</div>
</div>

<link rel="stylesheet" href="<?php echo esc_url( BOOKIT_PLUGIN_URL . 'dashboard/css/dashboard-auth.css' ); ?>">

<?php
get_footer();

