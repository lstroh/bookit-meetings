<?php
/**
 * Tests for Bookit migration runner.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Migration_Runner behavior.
 */
class Test_Bookit_Migration_Runner extends WP_UnitTestCase {

	/**
	 * Temporary directories created during tests.
	 *
	 * @var string[]
	 */
	private array $temp_dirs = array();

	/**
	 * Temporary tables created during tests.
	 *
	 * @var string[]
	 */
	private array $temp_tables = array();

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		Bookit_Migration_Runner::create_migrations_table();
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_migrations" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;

		foreach ( array_unique( $this->temp_tables ) as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
		}

		foreach ( array_unique( $this->temp_dirs ) as $dir ) {
			$files = glob( $dir . DIRECTORY_SEPARATOR . '*.php' );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					if ( is_string( $file ) && file_exists( $file ) ) {
						unlink( $file );
					}
				}
			}
			if ( is_dir( $dir ) ) {
				rmdir( $dir );
			}
		}

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_migrations" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Migration_Runner::create_migrations_table
	 */
	public function test_create_migrations_table_creates_table() {
		global $wpdb;

		Bookit_Migration_Runner::create_migrations_table();

		$table = $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}bookings_migrations'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->assertSame( $wpdb->prefix . 'bookings_migrations', $table );
	}

	/**
	 * @covers Bookit_Migration_Runner::mark_as_run
	 * @covers Bookit_Migration_Runner::has_run
	 */
	public function test_mark_as_run_inserts_record() {
		Bookit_Migration_Runner::mark_as_run( 'test-migration-001', 'bookit-test' );

		$this->assertTrue(
			Bookit_Migration_Runner::has_run( 'test-migration-001', 'bookit-test' )
		);
	}

	/**
	 * @covers Bookit_Migration_Runner::has_run
	 */
	public function test_has_run_returns_false_for_unknown_migration() {
		$this->assertFalse(
			Bookit_Migration_Runner::has_run( 'nonexistent-migration', 'bookit-test' )
		);
	}

	/**
	 * @covers Bookit_Migration_Runner::run_pending
	 * @covers Bookit_Migration_Runner::has_run
	 */
	public function test_run_pending_executes_valid_migration() {
		global $wpdb;

		$migration = $this->create_temp_migration_artifacts();

		Bookit_Migration_Runner::register_migration_path( 'bookit-test', $migration['dir'] );
		Bookit_Migration_Runner::run_pending( 'bookit-test' );

		$this->assertTrue( Bookit_Migration_Runner::has_run( $migration['migration_id'], 'bookit-test' ) );

		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$migration['table_name']}'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->assertSame( $migration['table_name'], $table_exists );
	}

	/**
	 * @covers Bookit_Migration_Runner::rollback_last
	 * @covers Bookit_Migration_Runner::run_pending
	 * @covers Bookit_Migration_Runner::has_run
	 */
	public function test_rollback_last_calls_down() {
		global $wpdb;

		$migration = $this->create_temp_migration_artifacts();

		Bookit_Migration_Runner::register_migration_path( 'bookit-test', $migration['dir'] );
		Bookit_Migration_Runner::run_pending( 'bookit-test' );

		$this->assertTrue( Bookit_Migration_Runner::rollback_last( 'bookit-test' ) );
		$this->assertFalse( Bookit_Migration_Runner::has_run( $migration['migration_id'], 'bookit-test' ) );

		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$migration['table_name']}'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->assertNull( $table_exists );
	}

	/**
	 * @covers Bookit_Migration_Runner::register_migration_path
	 * @covers Bookit_Migration_Runner::run_pending
	 */
	public function test_duplicate_registration_is_silently_ignored() {
		global $wpdb;

		$migration = $this->create_temp_migration_artifacts();

		Bookit_Migration_Runner::register_migration_path( 'bookit-test', $migration['dir'] );
		Bookit_Migration_Runner::register_migration_path( 'bookit-test', $migration['dir'] );
		Bookit_Migration_Runner::run_pending( 'bookit-test' );
		Bookit_Migration_Runner::run_pending( 'bookit-test' );

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings_migrations WHERE migration_id = %s AND plugin_slug = %s",
				$migration['migration_id'],
				'bookit-test'
			)
		);

		$this->assertSame( 1, $count );
	}

	/**
	 * @covers Bookit_Migration_Runner::register_migration_path
	 * @covers Bookit_Migration_Runner::run_pending
	 * @covers Bookit_Migration_Runner::has_run
	 */
	public function test_get_pending_returns_unrun_migrations() {
		global $wpdb;

		$suffix               = strtolower( wp_generate_password( 8, false, false ) );
		$first_migration_id   = '0001-temp-first-' . $suffix;
		$second_migration_id  = '0002-temp-second-' . $suffix;
		$first_table_name     = $wpdb->prefix . 'bookings_temp_migration_first_' . $suffix;
		$second_table_name    = $wpdb->prefix . 'bookings_temp_migration_second_' . $suffix;
		$first_class_name     = 'Bookit_Migration_' . str_replace( '-', '_', ucwords( $first_migration_id, '-' ) );
		$second_class_name    = 'Bookit_Migration_' . str_replace( '-', '_', ucwords( $second_migration_id, '-' ) );
		$base_tmp             = trailingslashit( sys_get_temp_dir() ) . 'bookit-tests-migrations';
		$dir                  = trailingslashit( $base_tmp ) . $suffix;

		if ( ! is_dir( $base_tmp ) ) {
			wp_mkdir_p( $base_tmp );
		}
		wp_mkdir_p( $dir );

		$first_migration_php = <<<PHP
<?php
class {$first_class_name} extends Bookit_Migration_Base {
	public function migration_id(): string {
		return '{$first_migration_id}';
	}

	public function plugin_slug(): string {
		return 'bookit-test';
	}

	public function up(): void {
		global \$wpdb;
		\$wpdb->query( "CREATE TABLE IF NOT EXISTS {$first_table_name} (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" );
	}

	public function down(): void {
		global \$wpdb;
		\$wpdb->query( "DROP TABLE IF EXISTS {$first_table_name}" );
	}
}
PHP;

		$second_migration_php = <<<PHP
<?php
class {$second_class_name} extends Bookit_Migration_Base {
	public function migration_id(): string {
		return '{$second_migration_id}';
	}

	public function plugin_slug(): string {
		return 'bookit-test';
	}

	public function up(): void {
		global \$wpdb;
		\$wpdb->query( "CREATE TABLE IF NOT EXISTS {$second_table_name} (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" );
	}

	public function down(): void {
		global \$wpdb;
		\$wpdb->query( "DROP TABLE IF EXISTS {$second_table_name}" );
	}
}
PHP;

		file_put_contents( trailingslashit( $dir ) . $first_migration_id . '.php', $first_migration_php );
		$this->temp_dirs[]   = $dir;
		$this->temp_tables[] = $first_table_name;
		$this->temp_tables[] = $second_table_name;

		Bookit_Migration_Runner::register_migration_path( 'bookit-test', $dir );

		$first_run_ran = Bookit_Migration_Runner::run_pending( 'bookit-test' );
		$this->assertContains( $first_migration_id, $first_run_ran );
		$this->assertNotContains( $second_migration_id, $first_run_ran );
		$this->assertTrue( Bookit_Migration_Runner::has_run( $first_migration_id, 'bookit-test' ) );
		$this->assertFalse( Bookit_Migration_Runner::has_run( $second_migration_id, 'bookit-test' ) );

		file_put_contents( trailingslashit( $dir ) . $second_migration_id . '.php', $second_migration_php );

		$second_run_ran = Bookit_Migration_Runner::run_pending( 'bookit-test' );
		$this->assertSame( array( $second_migration_id ), $second_run_ran );
		$this->assertTrue( Bookit_Migration_Runner::has_run( $second_migration_id, 'bookit-test' ) );
	}

	/**
	 * Create temporary migration file and table metadata.
	 *
	 * @return array<string,string>
	 */
	private function create_temp_migration_artifacts(): array {
		global $wpdb;

		$suffix       = wp_generate_password( 8, false, false );
		$migration_id = '0001-temp-' . strtolower( $suffix );
		$table_name   = $wpdb->prefix . 'bookings_temp_migration_' . strtolower( $suffix );
		$class_name   = 'Bookit_Migration_' . str_replace( '-', '_', ucwords( $migration_id, '-' ) );

		$base_tmp = trailingslashit( sys_get_temp_dir() ) . 'bookit-tests-migrations';
		if ( ! is_dir( $base_tmp ) ) {
			wp_mkdir_p( $base_tmp );
		}

		$dir = trailingslashit( $base_tmp ) . strtolower( $suffix );
		wp_mkdir_p( $dir );

		$php = <<<PHP
<?php
class {$class_name} extends Bookit_Migration_Base {
	public function migration_id(): string {
		return '{$migration_id}';
	}

	public function plugin_slug(): string {
		return 'bookit-test';
	}

	public function up(): void {
		global \$wpdb;
		\$wpdb->query( "CREATE TABLE IF NOT EXISTS {$table_name} (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci" );
	}

	public function down(): void {
		global \$wpdb;
		\$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}
PHP;

		file_put_contents( trailingslashit( $dir ) . $migration_id . '.php', $php );

		$this->temp_dirs[]   = $dir;
		$this->temp_tables[] = $table_name;

		return array(
			'dir'          => $dir,
			'migration_id' => $migration_id,
			'table_name'   => $table_name,
		);
	}
}
