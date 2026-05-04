<?php
/**
 * Staff Model
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/models
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Staff Model class.
 */
class Bookit_Staff_Model {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Get all active staff who offer a specific service
	 * Sorted alphabetically by first_name
	 *
	 * @param int $service_id Service ID.
	 * @return array Array of staff members with pricing.
	 */
	public function get_staff_for_service( $service_id ) {
		$service_id = absint( $service_id );
		if ( $service_id <= 0 ) {
			return array();
		}

		$table_prefix = $this->wpdb->prefix;

		$sql = $this->wpdb->prepare(
			"
			SELECT 
				s.id,
				s.first_name,
				s.last_name,
				CONCAT(s.first_name, ' ', s.last_name) as full_name,
				s.email,
				s.phone,
				s.photo_url,
				s.bio,
				s.title,
				COALESCE(ss.custom_price, srv.price) as price
			FROM {$table_prefix}bookings_staff s
			INNER JOIN {$table_prefix}bookings_staff_services ss ON s.id = ss.staff_id
			INNER JOIN {$table_prefix}bookings_services srv ON ss.service_id = srv.id
			WHERE s.is_active = 1
			  AND srv.id = %d
			  AND srv.is_active = 1
			  AND s.deleted_at IS NULL
			  AND srv.deleted_at IS NULL
			ORDER BY s.first_name ASC, s.last_name ASC
			",
			$service_id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		return $this->wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Get lowest price among staff for a service
	 * Used for "No Preference" card
	 *
	 * @param int $service_id Service ID.
	 * @return float Lowest price.
	 */
	public function get_lowest_staff_price_for_service( $service_id ) {
		$service_id = absint( $service_id );
		if ( $service_id <= 0 ) {
			return 0.00;
		}

		$table_prefix = $this->wpdb->prefix;

		$sql = $this->wpdb->prepare(
			"
			SELECT MIN(COALESCE(ss.custom_price, srv.price)) as min_price
			FROM {$table_prefix}bookings_staff s
			INNER JOIN {$table_prefix}bookings_staff_services ss ON s.id = ss.staff_id
			INNER JOIN {$table_prefix}bookings_services srv ON ss.service_id = srv.id
			WHERE s.is_active = 1
			  AND srv.id = %d
			  AND srv.is_active = 1
			  AND s.deleted_at IS NULL
			  AND srv.deleted_at IS NULL
			",
			$service_id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $this->wpdb->get_var( $sql );
		return $result ? floatval( $result ) : 0.00;
	}

	/**
	 * Get single staff member by ID
	 *
	 * @param int $staff_id Staff ID.
	 * @return array|null Staff data or null.
	 */
	public function get_staff_by_id( $staff_id ) {
		$staff_id = absint( $staff_id );
		if ( $staff_id <= 0 ) {
			return null;
		}

		$table_prefix = $this->wpdb->prefix;

		$sql = $this->wpdb->prepare(
			"
			SELECT 
				id,
				email,
				first_name,
				last_name,
				CONCAT(first_name, ' ', last_name) as full_name,
				phone,
				photo_url,
				bio,
				title,
				is_active
			FROM {$table_prefix}bookings_staff
			WHERE id = %d
			  AND deleted_at IS NULL
			",
			$staff_id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$staff = $this->wpdb->get_row( $sql, ARRAY_A );

		// Return null if not found or inactive.
		if ( ! $staff || ! $staff['is_active'] ) {
			return null;
		}

		return $staff;
	}
}
