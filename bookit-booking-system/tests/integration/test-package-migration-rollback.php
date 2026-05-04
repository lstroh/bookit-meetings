<?php
/**
 * Integration tests for package migration rollback behavior.
 *
 * Manual-only: not included in default phpunit.xml suite.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test package migration rollback behavior.
 */
class Test_Package_Migration_Rollback extends WP_UnitTestCase {

	/**
	 * Ordered migration instances.
	 *
	 * @var array<int, Bookit_Migration_Base>
	 */
	private array $migrations = array();

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->load_package_migration_classes();
		$this->migrations = array(
			new Bookit_Migration_0005_Create_Package_Types_Table(),
			new Bookit_Migration_0006_Create_Customer_Packages_Table(),
			new Bookit_Migration_0007_Create_Package_Redemptions_Table(),
			new Bookit_Migration_0008_Add_Customer_Package_Id_To_Bookings(),
		);

		$this->rollback_package_migrations();
		$this->force_remove_package_schema_artifacts();
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		$this->rollback_package_migrations();
		$this->force_remove_package_schema_artifacts();
		parent::tearDown();
	}

	/**
	 * @covers Bookit_Migration_0007_Create_Package_Redemptions_Table::down
	 * @covers Bookit_Migration_0006_Create_Customer_Packages_Table::down
	 * @covers Bookit_Migration_0005_Create_Package_Types_Table::down
	 * @covers Bookit_Migration_0008_Add_Customer_Package_Id_To_Bookings::down
	 */
	public function test_rollback_drops_package_tables() {
		global $wpdb;

		$this->run_package_migrations();

		$this->assertTrue( $this->table_exists( $wpdb->prefix . 'bookings_package_redemptions' ) );
		$this->assertTrue( $this->table_exists( $wpdb->prefix . 'bookings_customer_packages' ) );
		$this->assertTrue( $this->table_exists( $wpdb->prefix . 'bookings_package_types' ) );
		$this->assertTrue( $this->column_exists( $wpdb->prefix . 'bookings', 'customer_package_id' ) );

		$this->rollback_package_migrations();

		$this->assertFalse( $this->table_exists( $wpdb->prefix . 'bookings_package_redemptions' ) );
		$this->assertFalse( $this->table_exists( $wpdb->prefix . 'bookings_customer_packages' ) );
		$this->assertFalse( $this->table_exists( $wpdb->prefix . 'bookings_package_types' ) );
		$this->assertFalse( $this->column_exists( $wpdb->prefix . 'bookings', 'customer_package_id' ) );
	}

	/**
	 * @covers Bookit_Migration_0007_Create_Package_Redemptions_Table::down
	 * @covers Bookit_Migration_0006_Create_Customer_Packages_Table::down
	 * @covers Bookit_Migration_0005_Create_Package_Types_Table::down
	 * @covers Bookit_Migration_0008_Add_Customer_Package_Id_To_Bookings::down
	 */
	public function test_rollback_is_idempotent() {
		global $wpdb;

		$this->run_package_migrations();
		$this->rollback_package_migrations();
		$this->assertEmpty( $wpdb->last_error, 'First rollback should not error' );

		$wpdb->last_error = '';
		$this->rollback_package_migrations();
		$this->assertEmpty( $wpdb->last_error, 'Second rollback should not error' );
	}

	/**
	 * Load migration files under test.
	 *
	 * @return void
	 */
	private function load_package_migration_classes(): void {
		require_once BOOKIT_PLUGIN_DIR . 'database/migrations/0005-create-package-types-table.php';
		require_once BOOKIT_PLUGIN_DIR . 'database/migrations/0006-create-customer-packages-table.php';
		require_once BOOKIT_PLUGIN_DIR . 'database/migrations/0007-create-package-redemptions-table.php';
		require_once BOOKIT_PLUGIN_DIR . 'database/migrations/0008-add-customer-package-id-to-bookings.php';
	}

	/**
	 * Run all package migrations in forward order.
	 *
	 * @return void
	 */
	private function run_package_migrations(): void {
		foreach ( $this->migrations as $migration ) {
			$migration->up();
		}
	}

	/**
	 * Roll back package migrations in reverse FK order.
	 *
	 * @return void
	 */
	private function rollback_package_migrations(): void {
		$lookup = array();
		foreach ( $this->migrations as $migration ) {
			$lookup[ $migration->migration_id() ] = $migration;
		}

		$order = array(
			'0007-create-package-redemptions-table',
			'0006-create-customer-packages-table',
			'0005-create-package-types-table',
			'0008-add-customer-package-id-to-bookings',
		);

		foreach ( $order as $migration_id ) {
			if ( isset( $lookup[ $migration_id ] ) ) {
				$lookup[ $migration_id ]->down();
			}
		}
	}

	/**
	 * Defensive cleanup for package schema artifacts.
	 *
	 * @return void
	 */
	private function force_remove_package_schema_artifacts(): void {
		global $wpdb;

		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		try {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bookings_package_redemptions" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bookings_customer_packages" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bookings_package_types" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery

			if ( $this->column_exists( $wpdb->prefix . 'bookings', 'customer_package_id' ) ) {
				$wpdb->query( "ALTER TABLE {$wpdb->prefix}bookings DROP COLUMN customer_package_id" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
			}
		} finally {
			$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}

	/**
	 * Check whether a table exists.
	 *
	 * @param string $table_name Full table name.
	 * @return bool
	 */
	private function table_exists( string $table_name ): bool {
		global $wpdb;

		$table = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		return $table === $table_name;
	}

	/**
	 * Check whether a table column exists.
	 *
	 * @param string $table_name Full table name.
	 * @param string $column     Column name.
	 * @return bool
	 */
	private function column_exists( string $table_name, string $column ): bool {
		global $wpdb;

		$row = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW COLUMNS FROM {$table_name} LIKE %s",
				$column
			)
		);

		return ! empty( $row );
	}
}
