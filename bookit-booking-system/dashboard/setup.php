<?php
/**
 * First Admin Setup Page.
 *
 * Only accessible when no admin users exist in the system.
 * Creates the first admin account for initial dashboard access.
 *
 * @package Bookit_Booking_System
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-session.php';
require_once BOOKIT_PLUGIN_DIR . 'includes/class-bookit-auth.php';

// If admins already exist, redirect to login — setup is no longer needed.
if ( Bookit_Auth::has_admin_users() ) {
	wp_redirect( home_url( '/bookit-dashboard/' ) );
	exit;
}

$error = '';

// Handle form submission.
if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['bookit_setup_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bookit_setup_nonce'] ) ), 'bookit_setup' ) ) {
		$error = 'Security check failed. Please try again.';
	} else {
		// Double-check no admin was created in the meantime (race condition guard).
		if ( Bookit_Auth::has_admin_users() ) {
			wp_redirect( home_url( '/bookit-dashboard/' ) );
			exit;
		}

		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password   = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		$errors = array();

		if ( empty( $first_name ) ) {
			$errors[] = 'First name is required.';
		}
		if ( empty( $last_name ) ) {
			$errors[] = 'Last name is required.';
		}
		if ( ! is_email( $email ) ) {
			$errors[] = 'A valid email address is required.';
		}
		if ( strlen( $password ) < 8 ) {
			$errors[] = 'Password must be at least 8 characters.';
		}

		if ( empty( $errors ) ) {
			global $wpdb;

			$password_hash = Bookit_Auth::hash_password( $password );

			$result = $wpdb->insert(
				$wpdb->prefix . 'bookings_staff',
				array(
					'email'         => $email,
					'password_hash' => $password_hash,
					'first_name'    => $first_name,
					'last_name'     => $last_name,
					'role'          => 'admin',
					'is_active'     => 1,
					'display_order' => 0,
					'created_at'    => current_time( 'mysql' ),
					'updated_at'    => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
			);

			if ( $result ) {
				// Fetch the newly created staff record and log them in.
				$staff_id = $wpdb->insert_id;
				$staff    = array(
					'id'         => $staff_id,
					'email'      => $email,
					'first_name' => $first_name,
					'last_name'  => $last_name,
					'role'       => 'admin',
				);

				Bookit_Auth::login( $staff );

				wp_redirect( home_url( '/bookit-dashboard/app/' ) );
				exit;
			} else {
				$error = 'Failed to create admin user. Please try again.';
			}
		} else {
			$error = implode( ' ', $errors );
		}
	}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Setup - Bookit Dashboard</title>
	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}

		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 20px;
		}

		.setup-container {
			background: white;
			border-radius: 12px;
			box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
			max-width: 480px;
			width: 100%;
			padding: 40px;
		}

		.logo {
			text-align: center;
			margin-bottom: 32px;
		}

		.logo h1 {
			font-size: 32px;
			font-weight: 700;
			color: #1a202c;
			margin-bottom: 8px;
		}

		.logo p {
			font-size: 14px;
			color: #718096;
		}

		.welcome {
			text-align: center;
			margin-bottom: 32px;
		}

		.welcome h2 {
			font-size: 24px;
			font-weight: 600;
			color: #1a202c;
			margin-bottom: 8px;
		}

		.welcome p {
			font-size: 14px;
			color: #718096;
			line-height: 1.5;
		}

		.form-group {
			margin-bottom: 20px;
		}

		.form-row {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 16px;
		}

		label {
			display: block;
			font-size: 14px;
			font-weight: 500;
			color: #2d3748;
			margin-bottom: 6px;
		}

		input[type="text"],
		input[type="email"],
		input[type="password"] {
			width: 100%;
			padding: 12px 16px;
			font-size: 14px;
			border: 1px solid #e2e8f0;
			border-radius: 6px;
			transition: all 0.2s;
		}

		input:focus {
			outline: none;
			border-color: #667eea;
			box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
		}

		.password-hint {
			font-size: 12px;
			color: #718096;
			margin-top: 4px;
		}

		.error {
			background: #fed7d7;
			color: #c53030;
			padding: 12px 16px;
			border-radius: 6px;
			font-size: 14px;
			margin-bottom: 20px;
			border-left: 4px solid #fc8181;
		}

		.btn-primary {
			width: 100%;
			padding: 14px 24px;
			font-size: 16px;
			font-weight: 600;
			color: white;
			background: #667eea;
			border: none;
			border-radius: 6px;
			cursor: pointer;
			transition: all 0.2s;
		}

		.btn-primary:hover {
			background: #5a67d8;
			transform: translateY(-1px);
			box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
		}

		.btn-primary:active {
			transform: translateY(0);
		}

		.info-box {
			background: #e6fffa;
			border: 1px solid #81e6d9;
			border-radius: 6px;
			padding: 12px 16px;
			margin-top: 24px;
		}

		.info-box p {
			font-size: 12px;
			color: #234e52;
			line-height: 1.5;
		}
	</style>
</head>
<body>
	<div class="setup-container">
		<div class="logo">
			<h1>Bookit</h1>
			<p>Booking System Dashboard</p>
		</div>

		<div class="welcome">
			<h2>Welcome!</h2>
			<p>Let's create your admin account to get started.</p>
		</div>

		<?php if ( ! empty( $error ) ) : ?>
			<div class="error">
				<?php echo esc_html( $error ); ?>
			</div>
		<?php endif; ?>

		<form method="POST" action="">
			<?php wp_nonce_field( 'bookit_setup', 'bookit_setup_nonce' ); ?>

			<div class="form-row">
				<div class="form-group">
					<label for="first_name">First Name *</label>
					<input
						type="text"
						id="first_name"
						name="first_name"
						required
						autofocus
						value="<?php echo isset( $_POST['first_name'] ) ? esc_attr( wp_unslash( $_POST['first_name'] ) ) : ''; ?>"
					/>
				</div>

				<div class="form-group">
					<label for="last_name">Last Name *</label>
					<input
						type="text"
						id="last_name"
						name="last_name"
						required
						value="<?php echo isset( $_POST['last_name'] ) ? esc_attr( wp_unslash( $_POST['last_name'] ) ) : ''; ?>"
					/>
				</div>
			</div>

			<div class="form-group">
				<label for="email">Email Address *</label>
				<input
					type="email"
					id="email"
					name="email"
					required
					value="<?php echo isset( $_POST['email'] ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>"
				/>
			</div>

			<div class="form-group">
				<label for="password">Password *</label>
				<input
					type="password"
					id="password"
					name="password"
					required
					minlength="8"
				/>
				<p class="password-hint">Minimum 8 characters</p>
			</div>

			<button type="submit" class="btn-primary">
				Create Admin Account
			</button>
		</form>

		<div class="info-box">
			<p>
				<strong>This is a one-time setup.</strong> After creating your admin account,
				you can add additional staff members from the dashboard.
			</p>
			<p>
				This page is only accessible when no active admin accounts exist.
			</p>
		</div>
	</div>
</body>
</html>
