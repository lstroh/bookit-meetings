<?php
/**
 * Migration: Add Bookit Meetings schema.
 *
 * @package Bookit_Meetings
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$bookit_base_file = '';
if ( defined( 'BOOKIT_PLUGIN_DIR' ) ) {
	$bookit_base_file = BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';
} else {
	$bookit_base_file = dirname( __DIR__, 3 ) . '/bookit-booking-system/database/migrations/class-bookit-migration-base.php';
}

require_once $bookit_base_file;

/**
 * Adds meeting link support schema for bookings.
 */
class Bookit_Migration_Meetings_0001_Add_Meetings_Schema extends Bookit_Migration_Base {
	/**
	 * Unique migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return 'meetings-0001-add-meetings-schema';
	}

	/**
	 * Plugin slug for this migration.
	 *
	 * @return string
	 */
	public function plugin_slug(): string {
		return 'bookit-meetings';
	}

	/**
	 * Run migration (forward).
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$bookings_table = $wpdb->prefix . 'bookings';

		// 1) Add meeting_link column (if missing).
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$column_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM information_schema.COLUMNS
				WHERE TABLE_SCHEMA = %s
					AND TABLE_NAME = %s
					AND COLUMN_NAME = %s",
				DB_NAME,
				$bookings_table,
				'meeting_link'
			)
		);

		if ( empty( $column_exists ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$bookings_table} ADD COLUMN meeting_link VARCHAR(500) NULL DEFAULT NULL" );
		}

		// 2) Create credentials table (if missing).
		$credentials_table = $wpdb->prefix . 'bookit_meetings_credentials';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$table_exists      = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM information_schema.TABLES
				WHERE TABLE_SCHEMA = %s
					AND TABLE_NAME = %s",
				DB_NAME,
				$credentials_table
			)
		);

		if ( empty( $table_exists ) ) {
			$charset = $wpdb->get_charset_collate();

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query(
				"CREATE TABLE {$credentials_table} (
					id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
					platform    VARCHAR(50)  NOT NULL,
					credential_key   VARCHAR(100) NOT NULL,
					credential_value TEXT         NOT NULL,
					created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
					updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					UNIQUE KEY platform_key (platform, credential_key)
				) ENGINE=InnoDB {$charset};"
			);
		}

		// 3) Insert settings rows (idempotent).
		$settings_table = $wpdb->prefix . 'bookings_settings';

		$settings = array(
			'meetings_enabled'    => '0',
			'meetings_platform'   => '',
			'meetings_manual_url' => '',
		);

		foreach ( $settings as $key => $default_value ) {
			$existing_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$settings_table} WHERE setting_key = %s LIMIT 1",
					$key
				)
			);

			if ( null === $existing_id ) {
				$wpdb->insert(
					$settings_table,
					array(
						'setting_key'   => $key,
						'setting_value' => $default_value,
					),
					array( '%s', '%s' )
				);

				$inserted_value = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT setting_value FROM {$settings_table} WHERE setting_key = %s LIMIT 1",
						$key
					)
				);

				if ( null === $inserted_value ) {
					if ( '' === $default_value ) {
						$update_sql = $wpdb->prepare(
							"UPDATE {$settings_table} SET setting_value = '' WHERE setting_key = %s AND setting_value IS NULL",
							$key
						);
						// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
						$wpdb->query( $update_sql );
					} else {
						$wpdb->update(
							$settings_table,
							array( 'setting_value' => $default_value ),
							array( 'setting_key' => $key ),
							array( '%s' ),
							array( '%s' )
						);
					}
				}

				continue;
			}

			$current_value = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT setting_value FROM {$settings_table} WHERE setting_key = %s LIMIT 1",
					$key
				)
			);

			if ( null === $current_value ) {
				if ( '' === $default_value ) {
					$update_sql = $wpdb->prepare(
						"UPDATE {$settings_table} SET setting_value = '' WHERE setting_key = %s AND setting_value IS NULL",
						$key
					);
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->query( $update_sql );
				} else {
					$wpdb->update(
						$settings_table,
						array( 'setting_value' => $default_value ),
						array( 'setting_key' => $key ),
						array( '%s' ),
						array( '%s' )
					);
				}
			}
		}
	}

	/**
	 * Roll back migration (reverse).
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$settings_table = $wpdb->prefix . 'bookings_settings';
		$setting_keys   = array(
			'meetings_enabled',
			'meetings_platform',
			'meetings_manual_url',
		);

		// 1) Delete settings rows (per-key).
		foreach ( $setting_keys as $key ) {
			$wpdb->delete(
				$settings_table,
				array( 'setting_key' => $key ),
				array( '%s' )
			);
		}

		// 2) Drop credentials table.
		$credentials_table = $wpdb->prefix . 'bookit_meetings_credentials';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DROP TABLE IF EXISTS `{$credentials_table}`" );

		// 3) Drop meeting_link column.
		$bookings_table = $wpdb->prefix . 'bookings';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$column_exists = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT COLUMN_NAME FROM information_schema.COLUMNS
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
				DB_NAME,
				$bookings_table,
				'meeting_link'
			)
		);

		if ( ! empty( $column_exists ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "ALTER TABLE {$bookings_table} DROP COLUMN meeting_link" );
		}
	}
}

// Keep migration runner compatibility (expects class name derived from filename).
if ( ! class_exists( 'Bookit_Migration_0001_Add_Meetings_Schema' ) ) {
	class_alias( 'Bookit_Migration_Meetings_0001_Add_Meetings_Schema', 'Bookit_Migration_0001_Add_Meetings_Schema' );
}

