<?php
/**
 * Migration runner for Bookit and extensions.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'database/migrations/class-bookit-migration-base.php';

/**
 * Executes and tracks numbered migrations.
 */
class Bookit_Migration_Runner {

	/**
	 * Registered migration paths keyed by plugin slug.
	 *
	 * @var array<string, string>
	 */
	private static array $migration_paths = array();

	/**
	 * Register a migration path for a plugin.
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @param string $path        Absolute migrations path.
	 * @return void
	 */
	public static function register_migration_path( string $plugin_slug, string $path ): void {
		$normalized_path                         = rtrim( $path, '/\\' ) . DIRECTORY_SEPARATOR;
		self::$migration_paths[ $plugin_slug ] = $normalized_path;
	}

	/**
	 * Run all pending numbered migrations for a plugin slug.
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @return array<int, string> Migration IDs that were run.
	 */
	public static function run_pending( string $plugin_slug = 'bookit-booking-system' ): array {
		$ran_migrations = array();
		$path           = self::get_migration_path( $plugin_slug );

		if ( empty( $path ) || ! is_dir( $path ) ) {
			return $ran_migrations;
		}

		$files = scandir( $path );
		if ( false === $files ) {
			return $ran_migrations;
		}

		sort( $files, SORT_STRING );

		foreach ( $files as $file ) {
			if ( ! preg_match( '/^\d{4}-.+\.php$/', $file ) ) {
				continue;
			}

			$migration_id = pathinfo( $file, PATHINFO_FILENAME );
			if ( self::has_run( $migration_id, $plugin_slug ) ) {
				continue;
			}

			try {
				require_once $path . $file;

				$class_name = self::find_migration_class( $migration_id, $plugin_slug );
				if ( null === $class_name ) {
					throw new RuntimeException(
						sprintf(
							"No migration class found for migration_id '%s' in plugin '%s'",
							$migration_id,
							$plugin_slug
						)
					);
				}

				$migration = new $class_name();
				if ( ! $migration instanceof Bookit_Migration_Base ) {
					throw new RuntimeException( sprintf( 'Migration must extend Bookit_Migration_Base: %s', $class_name ) );
				}

				$migration->up();
				self::mark_as_run( $migration_id, $plugin_slug );
				$ran_migrations[] = $migration_id;
			} catch ( Throwable $exception ) {
				self::log_error(
					'Migration failed during run_pending()',
					array(
						'plugin_slug'  => $plugin_slug,
						'migration_id' => $migration_id,
						'error'        => $exception->getMessage(),
					)
				);
				break;
			}
		}

		return $ran_migrations;
	}

	/**
	 * Check whether a migration has already run.
	 *
	 * @param string $migration_id Migration identifier.
	 * @param string $plugin_slug  Plugin slug.
	 * @return bool
	 */
	public static function has_run( string $migration_id, string $plugin_slug = 'bookit-booking-system' ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_migrations';
		$sql        = $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE migration_id = %s AND plugin_slug = %s LIMIT 1",
			$migration_id,
			$plugin_slug
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_var( $sql );
		return ! empty( $result );
	}

	/**
	 * Mark a migration as run.
	 *
	 * @param string $migration_id Migration identifier.
	 * @param string $plugin_slug  Plugin slug.
	 * @return void
	 */
	public static function mark_as_run( string $migration_id, string $plugin_slug = 'bookit-booking-system' ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_migrations';
		$sql        = $wpdb->prepare(
			"INSERT IGNORE INTO {$table_name} (migration_id, plugin_slug) VALUES (%s, %s)",
			$migration_id,
			$plugin_slug
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $sql );
	}

	/**
	 * Roll back the most recently recorded migration.
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @return bool
	 */
	public static function rollback_last( string $plugin_slug = 'bookit-booking-system' ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_migrations';
		$sql        = $wpdb->prepare(
			"SELECT migration_id FROM {$table_name} WHERE plugin_slug = %s ORDER BY id DESC LIMIT 1",
			$plugin_slug
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$last_migration_id = $wpdb->get_var( $sql );
		if ( empty( $last_migration_id ) ) {
			return false;
		}

		return self::rollback_single( $last_migration_id, $plugin_slug );
	}

	/**
	 * Roll back all migrations newer than a target migration ID.
	 *
	 * @param string $target_migration_id Target migration identifier.
	 * @param string $plugin_slug         Plugin slug.
	 * @return void
	 */
	public static function rollback_to( string $target_migration_id, string $plugin_slug = 'bookit-booking-system' ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_migrations';
		$sql        = $wpdb->prepare(
			"SELECT migration_id FROM {$table_name} WHERE plugin_slug = %s AND migration_id > %s ORDER BY migration_id DESC",
			$plugin_slug,
			$target_migration_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$migration_ids = $wpdb->get_col( $sql );
		if ( empty( $migration_ids ) ) {
			return;
		}

		foreach ( $migration_ids as $migration_id ) {
			if ( ! self::rollback_single( $migration_id, $plugin_slug ) ) {
				break;
			}
		}
	}

	/**
	 * Create migration tracking table if missing.
	 *
	 * @return void
	 */
	public static function create_migrations_table(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bookings_migrations';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			migration_id VARCHAR(200) NOT NULL,
			plugin_slug VARCHAR(100) NOT NULL DEFAULT 'bookit-booking-system',
			ran_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uq_migration (migration_id, plugin_slug),
			KEY idx_plugin_slug (plugin_slug)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $sql );
	}

	/**
	 * Build a migration class name from a filename without extension.
	 *
	 * @param string $filename Migration filename without .php.
	 * @return string
	 */
	private static function class_name_from_filename( string $filename ): string {
		return 'Bookit_Migration_' . str_replace( '-', '_', ucwords( $filename, '-' ) );
	}

	/**
	 * Find a migration class that was just loaded from a file.
	 *
	 * After requiring a migration file, scan all declared classes for one
	 * that: (a) extends Bookit_Migration_Base, (b) returns the expected
	 * migration_id() and plugin_slug() values.
	 *
	 * @param string $expected_migration_id The migration ID to match.
	 * @param string $plugin_slug           The plugin slug to match.
	 * @return string|null Class name, or null if not found.
	 */
	private static function find_migration_class(
		string $expected_migration_id,
		string $plugin_slug
	): ?string {
		foreach ( get_declared_classes() as $class ) {
			if ( ! is_subclass_of( $class, Bookit_Migration_Base::class ) ) {
				continue;
			}
			$instance = new $class();
			if (
				$instance->migration_id() === $expected_migration_id &&
				$instance->plugin_slug() === $plugin_slug
			) {
				return $class;
			}
		}
		return null;
	}

	/**
	 * Resolve migration path for the requested plugin.
	 *
	 * @param string $plugin_slug Plugin slug.
	 * @return string
	 */
	private static function get_migration_path( string $plugin_slug ): string {
		self::register_core_path_if_needed();

		if ( isset( self::$migration_paths[ $plugin_slug ] ) ) {
			return self::$migration_paths[ $plugin_slug ];
		}

		return '';
	}

	/**
	 * Register core plugin migrations path automatically.
	 *
	 * @return void
	 */
	private static function register_core_path_if_needed(): void {
		if ( isset( self::$migration_paths['bookit-booking-system'] ) ) {
			return;
		}

		self::register_migration_path(
			'bookit-booking-system',
			BOOKIT_PLUGIN_DIR . 'database/migrations/'
		);
	}

	/**
	 * Roll back a specific migration ID and remove its tracking record.
	 *
	 * @param string $migration_id Migration identifier.
	 * @param string $plugin_slug  Plugin slug.
	 * @return bool
	 */
	private static function rollback_single( string $migration_id, string $plugin_slug ): bool {
		global $wpdb;

		$path = self::get_migration_path( $plugin_slug );
		if ( empty( $path ) || ! is_dir( $path ) ) {
			return false;
		}

		$file_path = $path . $migration_id . '.php';
		if ( ! file_exists( $file_path ) ) {
			self::log_error(
				'Migration file not found for rollback',
				array(
					'plugin_slug'  => $plugin_slug,
					'migration_id' => $migration_id,
					'file_path'    => $file_path,
				)
			);
			return false;
		}

		try {
			require_once $file_path;

			$class_name = self::find_migration_class( $migration_id, $plugin_slug );
			if ( null === $class_name ) {
				throw new RuntimeException(
					sprintf(
						"No migration class found for migration_id '%s' in plugin '%s'",
						$migration_id,
						$plugin_slug
					)
				);
			}

			$migration = new $class_name();
			if ( ! $migration instanceof Bookit_Migration_Base ) {
				throw new RuntimeException( sprintf( 'Migration must extend Bookit_Migration_Base: %s', $class_name ) );
			}

			$migration->down();

			$table_name = $wpdb->prefix . 'bookings_migrations';
			$delete_sql = $wpdb->prepare(
				"DELETE FROM {$table_name} WHERE migration_id = %s AND plugin_slug = %s",
				$migration_id,
				$plugin_slug
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $delete_sql );
			return true;
		} catch ( Throwable $exception ) {
			self::log_error(
				'Migration failed during rollback',
				array(
					'plugin_slug'  => $plugin_slug,
					'migration_id' => $migration_id,
					'error'        => $exception->getMessage(),
				)
			);
			return false;
		}
	}

	/**
	 * Log migration errors without breaking activation flows.
	 *
	 * @param string $message Log message.
	 * @param array  $context Log context.
	 * @return void
	 */
	private static function log_error( string $message, array $context = array() ): void {
		if ( ! class_exists( 'Bookit_Logger' ) && defined( 'BOOKIT_PLUGIN_DIR' ) ) {
			$logger_file = BOOKIT_PLUGIN_DIR . 'includes/class-bookit-logger.php';
			if ( file_exists( $logger_file ) ) {
				require_once $logger_file;
			}
		}

		if ( class_exists( 'Bookit_Logger' ) ) {
			Bookit_Logger::error( $message, $context );
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[Bookit Migration Runner] ' . $message . ' ' . wp_json_encode( $context ) );
	}
}
