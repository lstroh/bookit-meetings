<?php
/**
 * Core error code definitions.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/config
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Package error codes (E5xxx series).
if ( ! defined( 'BOOKIT_E5001' ) ) {
	define( 'BOOKIT_E5001', 'E5001' ); // PACKAGE_NOT_FOUND
}
if ( ! defined( 'BOOKIT_E5002' ) ) {
	define( 'BOOKIT_E5002', 'E5002' ); // PACKAGE_EXHAUSTED
}
if ( ! defined( 'BOOKIT_E5003' ) ) {
	define( 'BOOKIT_E5003', 'E5003' ); // PACKAGE_EXPIRED
}
if ( ! defined( 'BOOKIT_E5004' ) ) {
	define( 'BOOKIT_E5004', 'E5004' ); // PACKAGE_SERVICE_MISMATCH
}
if ( ! defined( 'BOOKIT_E5005' ) ) {
	define( 'BOOKIT_E5005', 'E5005' ); // PACKAGE_INSUFFICIENT_SESSIONS
}

if ( ! defined( 'BOOKIT_E2005' ) ) {
	define( 'BOOKIT_E2005', 'E2005' ); // INVALID_STATUS_TRANSITION
}

Bookit_Error_Registry::register(
	'E1001',
	array(
		'user_message' => __( 'Login failed. Please check your email and password.', 'bookit-booking-system' ),
		'log_message'  => 'Authentication failed for email: {email}',
		'http_status'  => 401,
		'category'     => 'auth',
	)
);

Bookit_Error_Registry::register(
	'E1002',
	array(
		'user_message' => __( 'Your session has expired. Please log in again.', 'bookit-booking-system' ),
		'log_message'  => 'Session expired or not found',
		'http_status'  => 401,
		'category'     => 'auth',
	)
);

Bookit_Error_Registry::register(
	'E1003',
	array(
		'user_message' => __( 'You do not have permission to perform this action.', 'bookit-booking-system' ),
		'log_message'  => 'Insufficient permissions. Required: {required_role}, actual: {actual_role}',
		'http_status'  => 403,
		'category'     => 'auth',
	)
);

Bookit_Error_Registry::register(
	'E2001',
	array(
		'user_message' => __( 'Sorry, that time slot is no longer available. Please choose another time.', 'bookit-booking-system' ),
		'log_message'  => 'Slot unavailable: staff {staff_id} on {date} at {time}',
		'http_status'  => 409,
		'category'     => 'booking',
	)
);

Bookit_Error_Registry::register(
	'E2002',
	array(
		'user_message' => __( 'Booking {booking_id} not found.', 'bookit-booking-system' ),
		'log_message'  => 'Booking ID {booking_id} not found',
		'http_status'  => 404,
		'category'     => 'booking',
	)
);

Bookit_Error_Registry::register(
	'E2003',
	array(
		'user_message' => __( 'This booking cannot be modified because it has already been completed.', 'bookit-booking-system' ),
		'log_message'  => 'Attempted to modify completed booking ID {booking_id}',
		'http_status'  => 422,
		'category'     => 'booking',
	)
);

Bookit_Error_Registry::register(
	'E2004',
	array(
		'user_message' => __( 'This booking was just updated by someone else. The latest version has been loaded — please review and save again.', 'bookit-booking-system' ),
		'log_message'  => 'Optimistic lock conflict on booking ID {booking_id}',
		'http_status'  => 409,
		'category'     => 'booking',
	)
);

Bookit_Error_Registry::register(
	'E2005',
	array(
		'user_message' => __( 'This status change is not allowed. Please refresh and try again.', 'bookit-booking-system' ),
		'log_message'  => 'Invalid status transition on booking ID {booking_id}: {old_status} → {new_status}',
		'http_status'  => 422,
		'category'     => 'booking',
	)
);

Bookit_Error_Registry::register(
	'E3001',
	array(
		'user_message' => __( 'Payment failed. Please try again or use a different payment method.', 'bookit-booking-system' ),
		'log_message'  => 'Payment failed: {gateway_message}',
		'http_status'  => 402,
		'category'     => 'payment',
	)
);

Bookit_Error_Registry::register(
	'E3002',
	array(
		'user_message' => __( 'A refund is not available for this booking.', 'bookit-booking-system' ),
		'log_message'  => 'Refund not available for booking ID {booking_id}: {reason}',
		'http_status'  => 422,
		'category'     => 'payment',
	)
);

Bookit_Error_Registry::register(
	'E3003',
	array(
		'user_message' => __( 'There was a problem connecting to the payment provider. Please try again shortly.', 'bookit-booking-system' ),
		'log_message'  => 'Payment gateway error: {gateway_message}',
		'http_status'  => 502,
		'category'     => 'payment',
	)
);

Bookit_Error_Registry::register(
	'E3010',
	array(
		'user_message' => __( 'Could not start the payment session. Please try again.', 'bookit-booking-system' ),
		'log_message'  => 'Stripe checkout session failed: {gateway_message}',
		'http_status'  => 500,
		'category'     => 'payment',
	)
);

Bookit_Error_Registry::register(
	'PAYMENT_METHOD_NOT_SUPPORTED',
	array(
		'user_message' => __( 'Payment method not supported', 'bookit-booking-system' ),
		'log_message'  => 'Requested payment method is not supported',
		'http_status'  => 501,
		'category'     => 'payment',
	)
);

Bookit_Error_Registry::register(
	'PACKAGE_PRICE_INVALID',
	array(
		'user_message' => __( 'Package price could not be calculated', 'bookit-booking-system' ),
		'log_message'  => 'Package price invalid for package_type_id {package_type_id}',
		'http_status'  => 422,
		'category'     => 'packages',
	)
);

Bookit_Error_Registry::register(
	'E4001',
	array(
		'user_message' => __( 'Please fill in all required fields.', 'bookit-booking-system' ),
		'log_message'  => 'Required field missing: {field}',
		'http_status'  => 422,
		'category'     => 'validation',
	)
);

Bookit_Error_Registry::register(
	'E4002',
	array(
		'user_message' => __( 'Please enter a valid email address.', 'bookit-booking-system' ),
		'log_message'  => 'Invalid email: {email}',
		'http_status'  => 422,
		'category'     => 'validation',
	)
);

Bookit_Error_Registry::register(
	'E4003',
	array(
		'user_message' => __( 'Please enter a valid date.', 'bookit-booking-system' ),
		'log_message'  => 'Invalid date: {date}',
		'http_status'  => 422,
		'category'     => 'validation',
	)
);

Bookit_Error_Registry::register(
	'E4004',
	array(
		'user_message' => __( 'Bookings cannot be made in the past.', 'bookit-booking-system' ),
		'log_message'  => 'Date in past: {date}',
		'http_status'  => 422,
		'category'     => 'validation',
	)
);

Bookit_Error_Registry::register(
	'E4005',
	array(
		'user_message' => __( 'The selected service could not be found.', 'bookit-booking-system' ),
		'log_message'  => 'Service ID {service_id} not found',
		'http_status'  => 404,
		'category'     => 'validation',
	)
);

Bookit_Error_Registry::register(
	'E4006',
	array(
		'user_message' => __( 'The selected staff member could not be found.', 'bookit-booking-system' ),
		'log_message'  => 'Staff ID {staff_id} not found',
		'http_status'  => 404,
		'category'     => 'validation',
	)
);

Bookit_Error_Registry::register(
	'E4010',
	array(
		'user_message' => __( 'Invalid setup guide action.', 'bookit-booking-system' ),
		'log_message'  => 'Invalid setup guide action: {action}',
		'http_status'  => 400,
		'category'     => 'validation',
	)
);

Bookit_Error_Registry::register(
	'E4011',
	array(
		'user_message' => __( 'Please provide a valid setup guide step (1-4).', 'bookit-booking-system' ),
		'log_message'  => 'Invalid setup guide step field: {field}',
		'http_status'  => 400,
		'category'     => 'validation',
	)
);

Bookit_Error_Registry::register(
	'E4012',
	array(
		'user_message' => __( 'Please select a valid calendar view.', 'bookit-booking-system' ),
		'log_message'  => 'Invalid team calendar view type: {field}',
		'http_status'  => 400,
		'category'     => 'validation',
	)
);

Bookit_Error_Registry::register(
	'E4013',
	array(
		'user_message' => __( 'Customer {customer_id} not found.', 'bookit-booking-system' ),
		'log_message'  => 'Customer ID {customer_id} not found for data export',
		'http_status'  => 404,
		'category'     => 'validation',
	)
);

Bookit_Error_Registry::register(
	'BULK_INVALID_ACTION',
	array(
		'user_message' => __( 'Invalid bulk action. Allowed actions: cancel, complete, no_show.', 'bookit-booking-system' ),
		'log_message'  => 'Invalid bulk action: {action}',
		'http_status'  => 400,
		'category'     => 'validation',
	)
);

Bookit_Error_Registry::register(
	'BULK_EMPTY_IDS',
	array(
		'user_message' => __( 'Please select at least one booking.', 'bookit-booking-system' ),
		'log_message'  => 'Bulk action requested with empty booking_ids payload',
		'http_status'  => 400,
		'category'     => 'validation',
	)
);

Bookit_Error_Registry::register(
	'BULK_TOO_MANY_IDS',
	array(
		'user_message' => __( 'You can update up to 100 bookings at once.', 'bookit-booking-system' ),
		'log_message'  => 'Bulk action exceeded booking limit: {count}',
		'http_status'  => 400,
		'category'     => 'validation',
	)
);

Bookit_Error_Registry::register_package_errors();

Bookit_Error_Registry::register(
	'E6001',
	array(
		'user_message' => __( 'Too many requests. Please wait before trying again.', 'bookit-booking-system' ),
		'log_message'  => 'Rate limit exceeded for action: {action}',
		'http_status'  => 429,
		'category'     => 'system',
	)
);

Bookit_Error_Registry::register(
	'E9001',
	array(
		'user_message' => __( 'A database error occurred. Please try again.', 'bookit-booking-system' ),
		'log_message'  => 'Database error: {db_error}',
		'http_status'  => 500,
		'category'     => 'system',
	)
);

Bookit_Error_Registry::register(
	'E9002',
	array(
		'user_message' => __( 'An unexpected error occurred. Please try again.', 'bookit-booking-system' ),
		'log_message'  => 'Unexpected error: {error}',
		'http_status'  => 500,
		'category'     => 'system',
	)
);

Bookit_Error_Registry::register(
	'E9999',
	array(
		'user_message' => __( 'An unexpected error occurred. Please try again.', 'bookit-booking-system' ),
		'log_message'  => 'Unknown error code used',
		'http_status'  => 500,
		'category'     => 'system',
	)
);
