<?php
/**
 * Wizard V2 payment amount helpers (deposit / total from service row).
 *
 * @package    Bookit_Booking_System
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Compute deposit and totals from a service row (same rules as the V2 payment step template).
 *
 * @param array $service Service row (associative).
 * @return array{ has_deposit: bool, deposit_due: float, balance_due: float, total_price: float }
 */
function bookit_v2_compute_payment_amounts_from_service( $service ) {
	$service_deposit_type   = $service['deposit_type'] ?? 'none';
	$service_deposit_amount = (float) ( $service['deposit_amount'] ?? 0 );
	$total_price            = (float) ( $service['price'] ?? 0 );
	$has_deposit            = false;
	$deposit_due            = 0.00;
	$balance_due            = $total_price;

	if ( 'percentage' === $service_deposit_type && $service_deposit_amount > 0 ) {
		$has_deposit = true;
		$deposit_due = round( $total_price * ( $service_deposit_amount / 100 ), 2 );
		$balance_due = round( $total_price - $deposit_due, 2 );
	} elseif ( 'fixed' === $service_deposit_type && $service_deposit_amount > 0 ) {
		$has_deposit = true;
		$deposit_due = min( $service_deposit_amount, $total_price );
		$balance_due = round( $total_price - $deposit_due, 2 );
	}

	return array(
		'has_deposit' => $has_deposit,
		'deposit_due' => $deposit_due,
		'balance_due' => $balance_due,
		'total_price' => $total_price,
	);
}

/**
 * Amount that would be charged online right now (deposit when configured; 0 when pay-in-full on arrival).
 *
 * @param array $amounts Result of bookit_v2_compute_payment_amounts_from_service().
 * @return float
 */
function bookit_v2_stripe_charge_amount( array $amounts ) {
	if ( ! empty( $amounts['has_deposit'] ) ) {
		return (float) ( $amounts['deposit_due'] ?? 0 );
	}
	return 0.0;
}
