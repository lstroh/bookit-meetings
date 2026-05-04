<?php
/**
 * Service model for booking system.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes/models
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Service model class.
 */
class Bookit_Service_Model {

	/**
	 * Get active services organized by category.
	 *
	 * Returns array of categories, each containing array of services.
	 * Only includes active services with at least one active staff member.
	 * Categories sorted alphabetically.
	 * Services within category sorted alphabetically by name.
	 *
	 * @return array Services organized by category.
	 */
	public function get_active_services_by_category() {
		global $wpdb;

		$table_prefix = $wpdb->prefix;

		// Check if custom_price column exists in staff_services table.
		$has_custom_price = $this->column_exists( $table_prefix . 'bookings_staff_services', 'custom_price' );

		// Build query based on whether custom_price exists.
		if ( $has_custom_price ) {
			// Query with custom_price support.
			$sql = "
				SELECT 
					s.id,
					s.name,
					s.description,
					s.duration,
					s.price as base_price,
					MIN(COALESCE(ss.custom_price, s.price)) as min_staff_price,
					MAX(COALESCE(ss.custom_price, s.price)) as max_staff_price,
					GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') as categories
				FROM {$table_prefix}bookings_services s
				INNER JOIN {$table_prefix}bookings_staff_services ss ON s.id = ss.service_id
				INNER JOIN {$table_prefix}bookings_staff st ON ss.staff_id = st.id
				INNER JOIN {$table_prefix}bookings_service_categories sc ON s.id = sc.service_id
				INNER JOIN {$table_prefix}bookings_categories c ON sc.category_id = c.id
				WHERE s.is_active = 1
				  AND s.deleted_at IS NULL
				  AND st.is_active = 1
				  AND st.deleted_at IS NULL
				  AND c.is_active = 1
				  AND c.deleted_at IS NULL
				GROUP BY s.id, s.name, s.description, s.duration, s.price
				ORDER BY c.name ASC, s.name ASC
			";
		} else {
			// Query without custom_price (use base price for all staff).
			$sql = "
				SELECT 
					s.id,
					s.name,
					s.description,
					s.duration,
					s.price as base_price,
					s.price as min_staff_price,
					s.price as max_staff_price,
					GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') as categories
				FROM {$table_prefix}bookings_services s
				INNER JOIN {$table_prefix}bookings_staff_services ss ON s.id = ss.service_id
				INNER JOIN {$table_prefix}bookings_staff st ON ss.staff_id = st.id
				INNER JOIN {$table_prefix}bookings_service_categories sc ON s.id = sc.service_id
				INNER JOIN {$table_prefix}bookings_categories c ON sc.category_id = c.id
				WHERE s.is_active = 1
				  AND s.deleted_at IS NULL
				  AND st.is_active = 1
				  AND st.deleted_at IS NULL
				  AND c.is_active = 1
				  AND c.deleted_at IS NULL
				GROUP BY s.id, s.name, s.description, s.duration, s.price
				ORDER BY c.name ASC, s.name ASC
			";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_results( $sql, ARRAY_A );

		if ( empty( $results ) ) {
			return array();
		}

		// Organize services by category.
		$services_by_category = array();

		foreach ( $results as $row ) {
			// Parse categories (comma-separated from GROUP_CONCAT).
			$category_names = array_map( 'trim', explode( ',', $row['categories'] ) );

			// Determine if pricing varies.
			$has_variable_pricing = (float) $row['min_staff_price'] !== (float) $row['max_staff_price'];

			// Build service data.
			$service_data = array(
				'id'                  => (int) $row['id'],
				'name'                => $row['name'],
				'description'         => $row['description'],
				'duration'            => (int) $row['duration'],
				'base_price'          => (float) $row['base_price'],
				'min_staff_price'     => (float) $row['min_staff_price'],
				'max_staff_price'     => (float) $row['max_staff_price'],
				'has_variable_pricing' => $has_variable_pricing,
				'categories'          => $category_names,
			);

			// Add service to each category it belongs to.
			foreach ( $category_names as $category_name ) {
				if ( ! isset( $services_by_category[ $category_name ] ) ) {
					$services_by_category[ $category_name ] = array();
				}
				$services_by_category[ $category_name ][] = $service_data;
			}
		}

		// Sort categories alphabetically.
		ksort( $services_by_category );

		// Sort services within each category alphabetically by name.
		foreach ( $services_by_category as $category_name => &$services ) {
			usort(
				$services,
				function( $a, $b ) {
					return strcmp( $a['name'], $b['name'] );
				}
			);
		}

		return $services_by_category;
	}

	/**
	 * Get service by ID.
	 *
	 * @param int $service_id Service ID.
	 * @return array|null Service data or null if not found.
	 */
	public function get_service_by_id( $service_id ) {
		global $wpdb;

		$service_id = absint( $service_id );
		if ( $service_id <= 0 ) {
			return null;
		}

		$table_prefix = $wpdb->prefix;

		$sql = $wpdb->prepare(
			"
			SELECT 
				s.id,
				s.name,
				s.description,
				s.duration,
				s.price as base_price,
				s.is_active,
				s.buffer_before,
				s.buffer_after
			FROM {$table_prefix}bookings_services s
			WHERE s.id = %d
			  AND s.deleted_at IS NULL
			",
			$service_id
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->get_row( $sql, ARRAY_A );

		if ( empty( $result ) ) {
			return null;
		}

		// Convert to expected format.
		return array(
			'id'           => (int) $result['id'],
			'name'         => $result['name'],
			'description'  => $result['description'],
			'duration'     => (int) $result['duration'],
			'base_price'   => (float) $result['base_price'],
			'status'       => $result['is_active'] ? 'active' : 'inactive',
			'buffer_before' => (int) $result['buffer_before'],
			'buffer_after'  => (int) $result['buffer_after'],
		);
	}

	/**
	 * Check if a column exists in a table.
	 *
	 * @param string $table_name Table name.
	 * @param string $column_name Column name.
	 * @return bool True if column exists.
	 */
	/**
	 * Check if a column exists in a table.
	 *
	 * @param string $table_name Table name.
	 * @param string $column_name Column name.
	 * @return bool True if column exists.
	 */
	private function column_exists( $table_name, $column_name ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %1s LIKE %s',
				$table_name,
				$column_name
			)
		);

		return ! empty( $result );
	}
}
