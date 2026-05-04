<?php
/**
 * Dashboard login page.
 *
 * @package Bookit_Booking_System
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-session.php';
require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-auth.php';

// Check if setup is needed (no admin users exist yet).
if ( ! Bookit_Auth::has_admin_users() ) {
	wp_redirect( home_url( '/bookit-dashboard/setup/' ) );
	exit;
}

// If already logged in, redirect to dashboard.
if ( Bookit_Auth::is_logged_in() ) {
	wp_redirect( home_url( '/bookit-dashboard/app/' ) );
	exit;
}

$error_message = '';

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['booking_login_submit'] ) ) {
	$ip = Bookit_Rate_Limiter::get_client_ip();
	if ( ! Bookit_Rate_Limiter::check( 'dashboard_login', $ip, 5, 15 * MINUTE_IN_SECONDS ) ) {
		Bookit_Rate_Limiter::handle_exceeded( 'dashboard_login', $ip );
		status_header( 429 );
		$error_message = Bookit_Error_Registry::to_wp_error( 'E6001' )->get_error_message();
	}

	if ( empty( $error_message ) ) {
		// Verify nonce.
		if (
			! isset( $_POST['booking_login_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['booking_login_nonce'] ) ), 'booking_login' )
		) {
			$error_message = 'Security check failed. Please try again.';
		} else {
			$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

			if ( empty( $email ) || empty( $password ) ) {
				$error_message = 'Please enter both email and password.';
			} else {
				$staff = Bookit_Auth::authenticate( $email, $password );

				if ( $staff ) {
					Bookit_Auth::login( $staff );

					$redirect_to = isset( $_GET['redirect_to'] ) ? (string) wp_unslash( $_GET['redirect_to'] ) : '';
					if ( empty( $redirect_to ) ) {
						$redirect_to = home_url( '/bookit-dashboard/app/' );
					}

					wp_redirect( $redirect_to );
					exit;
				} else {
					$error_message = 'Invalid email or password.';
				}
			}
		}
	}
}

get_header();
?>

<div class="booking-dashboard-login-wrapper">
	<div class="booking-login-container">
		<div class="booking-login-header">
			<h1><?php echo esc_html__( 'Booking System', 'bookit-booking-system' ); ?></h1>
			<p><?php echo esc_html__( 'Staff Dashboard Login', 'bookit-booking-system' ); ?></p>
		</div>

		<?php if ( ! empty( $error_message ) ) : ?>
			<div class="booking-login-error">
				<?php echo esc_html( $error_message ); ?>
			</div>
		<?php endif; ?>

		<form method="post" action="" class="booking-login-form">
			<?php wp_nonce_field( 'booking_login', 'booking_login_nonce' ); ?>

			<div class="booking-form-group">
				<label for="email"><?php echo esc_html__( 'Email Address', 'bookit-booking-system' ); ?></label>
				<input
					type="email"
					id="email"
					name="email"
					required
					autofocus
					value="<?php echo isset( $_POST['email'] ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>"
				/>
			</div>

			<div class="booking-form-group">
				<label for="password"><?php echo esc_html__( 'Password', 'bookit-booking-system' ); ?></label>
				<input
					type="password"
					id="password"
					name="password"
					required
				/>
			</div>

			<div class="booking-form-group">
				<button type="submit" name="booking_login_submit" class="booking-login-button">
					<?php echo esc_html__( 'Log In', 'bookit-booking-system' ); ?>
				</button>
			</div>
		</form>

		<div class="booking-login-footer">
			<p>
				<a href="<?php echo esc_url( home_url( '/bookit-dashboard/forgot-password/' ) ); ?>">
					<?php echo esc_html__( 'Forgot password?', 'bookit-booking-system' ); ?>
				</a>
			</p>
			<p class="booking-login-help">
				<?php echo esc_html__( 'Need help? Contact your administrator.', 'bookit-booking-system' ); ?>
			</p>
		</div>
	</div>
</div>

<link rel="stylesheet" href="<?php echo esc_url( BOOKIT_PLUGIN_URL . 'dashboard/css/dashboard-auth.css' ); ?>">

<?php
get_footer();

