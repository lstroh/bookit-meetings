<?php
/**
 * Generate Test Bookings for Today
 * 
 * Usage: 
 * php generate-test-bookings.php
 * 
 * Or visit: http://plugin-test-1.local/wp-content/plugins/bookit-booking-system/dashboard/test-data/generate-test-bookings.php
 */

// Load WordPress
require_once __DIR__ . '/../../../../wp-load.php';

// Security check (only in local environment)
if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
    die( 'This script can only run in development mode (WP_DEBUG = true)' );
}

global $wpdb;

$today = current_time( 'Y-m-d' );
$now = current_time( 'mysql' );

// 1. Create test customers
$test_customers = [
    ['alice.smith@example.com', 'Alice', 'Smith', '07700900001'],
    ['bob.jones@example.com', 'Bob', 'Jones', '07700900002'],
    ['charlie.brown@example.com', 'Charlie', 'Brown', '07700900003'],
    ['diana.prince@example.com', 'Diana', 'Prince', '07700900004'],
    ['edward.king@example.com', 'Edward', 'King', '07700900005'],
];

foreach ( $test_customers as $customer ) {
    $wpdb->insert(
        $wpdb->prefix . 'bookings_customers',
        [
            'email' => $customer[0],
            'first_name' => $customer[1],
            'last_name' => $customer[2],
            'phone' => $customer[3],
            'marketing_consent' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        ['%s', '%s', '%s', '%s', '%d', '%s', '%s']
    );
}

// 2. Get IDs
$customer_ids = [];
foreach ( $test_customers as $customer ) {
    $customer_ids[] = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}bookings_customers WHERE email = %s LIMIT 1",
        $customer[0]
    ) );
}

$staff_id = $wpdb->get_var( "SELECT id FROM {$wpdb->prefix}bookings_staff WHERE is_active = 1 ORDER BY id LIMIT 1" );
$service = $wpdb->get_row( "SELECT id, price, duration FROM {$wpdb->prefix}bookings_services WHERE is_active = 1 ORDER BY id LIMIT 1" );

if ( ! $staff_id || ! $service ) {
    die( 'ERROR: No active staff or services found. Create at least one active staff member and service first.' );
}

// 3. Delete old test bookings for today
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$wpdb->prefix}bookings WHERE booking_date = %s AND customer_id IN (" . implode(',', array_map('intval', $customer_ids)) . ")",
    $today
) );

// 4. Create test bookings
$bookings = [
    [
        'customer_id' => $customer_ids[0],
        'start_time' => '09:00:00',
        'status' => 'confirmed',
        'payment_method' => 'stripe',
        'deposit_paid' => $service->price,
        'balance_due' => 0,
        'full_amount_paid' => 1,
        'special_requests' => 'Please use organic products',
    ],
    [
        'customer_id' => $customer_ids[1],
        'start_time' => '10:30:00',
        'status' => 'confirmed',
        'payment_method' => 'stripe',
        'deposit_paid' => $service->price * 0.5,
        'balance_due' => $service->price * 0.5,
        'full_amount_paid' => 0,
        'special_requests' => null,
    ],
    [
        'customer_id' => $customer_ids[2],
        'start_time' => '13:00:00',
        'status' => 'pending_payment',
        'payment_method' => 'pay_on_arrival',
        'deposit_paid' => 0,
        'balance_due' => $service->price,
        'full_amount_paid' => 0,
        'special_requests' => 'First time customer - please confirm',
    ],
    [
        'customer_id' => $customer_ids[3],
        'start_time' => '14:30:00',
        'status' => 'confirmed',
        'payment_method' => 'stripe',
        'deposit_paid' => $service->price,
        'balance_due' => 0,
        'full_amount_paid' => 1,
        'special_requests' => null,
    ],
    [
        'customer_id' => $customer_ids[4],
        'start_time' => '08:00:00',
        'status' => 'completed',
        'payment_method' => 'cash',
        'deposit_paid' => $service->price,
        'balance_due' => 0,
        'full_amount_paid' => 1,
        'special_requests' => null,
    ],
];

$created = 0;
foreach ( $bookings as $booking ) {
    $end_time = date( 'H:i:s', strtotime( $booking['start_time'] ) + ( $service->duration * 60 ) );
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'bookings',
        [
            'customer_id' => $booking['customer_id'],
            'service_id' => $service->id,
            'staff_id' => $staff_id,
            'booking_date' => $today,
            'start_time' => $booking['start_time'],
            'end_time' => $end_time,
            'duration' => $service->duration,
            'status' => $booking['status'],
            'total_price' => $service->price,
            'deposit_amount' => $service->price,
            'deposit_paid' => $booking['deposit_paid'],
            'balance_due' => $booking['balance_due'],
            'full_amount_paid' => $booking['full_amount_paid'],
            'payment_method' => $booking['payment_method'],
            'special_requests' => $booking['special_requests'],
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );
    
    if ( $result ) {
        $created++;
    }
}

echo "✅ SUCCESS: Created {$created} test bookings for {$today}\n\n";
echo "Test bookings:\n";
echo "- 08:00 - Completed (cash)\n";
echo "- 09:00 - Confirmed (full payment)\n";
echo "- 10:30 - Confirmed (deposit only)\n";
echo "- 13:00 - Pending payment (pay on arrival)\n";
echo "- 14:30 - Confirmed (full payment)\n\n";
echo "Visit: http://plugin-test-1.local/bookit-dashboard/app/ to see them!\n";