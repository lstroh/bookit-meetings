<?php
/**
 * Base class for numbered migrations.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/database/migrations
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Base migration contract for Bookit migrations.
 */
abstract class Bookit_Migration_Base {

	/**
	 * Unique migration ID, must match filename without .php.
	 *
	 * @return string
	 */
	abstract public function migration_id(): string;

	/**
	 * Plugin slug this migration belongs to.
	 *
	 * @return string
	 */
	public function plugin_slug(): string {
		return 'bookit-booking-system';
	}

	/**
	 * Run the migration (forward).
	 *
	 * @return void
	 */
	abstract public function up(): void;

	/**
	 * Roll back the migration (reverse).
	 *
	 * @return void
	 */
	abstract public function down(): void;
}
