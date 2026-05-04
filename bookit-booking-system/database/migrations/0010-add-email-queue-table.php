<?php
/**
 * Migration: Add email queue table.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Adds queue table for deferred notification delivery.
 */
class Bookit_Migration_0010_Add_Email_Queue_Table extends Bookit_Migration_Base {

	/**
	 * Return migration ID.
	 *
	 * @return string
	 */
	public function migration_id(): string {
		return '0010-add-email-queue-table';
	}

	/**
	 * Return plugin slug.
	 *
	 * @return string
	 */
	public function plugin_slug(): string {
		return 'bookit-booking-system';
	}

	/**
	 * Run migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookit_email_queue';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$table_name} (
				id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				booking_id      BIGINT UNSIGNED NULL,
				email_type      VARCHAR(50) NOT NULL,
				recipient_email VARCHAR(255) NOT NULL,
				recipient_name  VARCHAR(255) NOT NULL DEFAULT '',
				subject         VARCHAR(500) NOT NULL DEFAULT '',
				html_body       LONGTEXT NOT NULL,
				params          LONGTEXT NULL COMMENT 'JSON -- provider-specific params',
				status          ENUM('pending','processing','sent','failed','cancelled')
								NOT NULL DEFAULT 'pending',
				attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
				max_attempts    TINYINT UNSIGNED NOT NULL DEFAULT 3,
				scheduled_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				sent_at         DATETIME NULL,
				last_error      TEXT NULL,
				created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
								ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY idx_status_scheduled (status, scheduled_at),
				KEY idx_booking_id (booking_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
		);
	}

	/**
	 * Roll back migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookit_email_queue';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}
