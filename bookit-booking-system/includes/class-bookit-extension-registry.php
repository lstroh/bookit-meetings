<?php
/**
 * Extension registry for Bookit extensions.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/includes
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Extension registry class.
 */
class Bookit_Extension_Registry {

	/**
	 * Registered extensions keyed by slug.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $extensions = array();

	/**
	 * Registered dashboard nav items.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private static array $nav_items = array();

	/**
	 * Register an extension plugin with Bookit core.
	 *
	 * @param array $args Extension registration args.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function register_extension( array $args ): true|WP_Error {
		$required_fields = array( 'name', 'slug', 'version', 'requires_core' );

		foreach ( $required_fields as $field ) {
			if ( empty( $args[ $field ] ) ) {
				return new WP_Error(
					'bookit_missing_field',
					sprintf( 'Missing required extension field: %s', $field )
				);
			}
		}

		$sanitized_slug = sanitize_key( $args['slug'] );

		if ( isset( self::$extensions[ $sanitized_slug ] ) ) {
			return new WP_Error(
				'bookit_duplicate_slug',
				sprintf( 'Extension slug already registered: %s', $sanitized_slug )
			);
		}

		if ( version_compare( BOOKIT_VERSION, (string) $args['requires_core'], '<' ) ) {
			return new WP_Error(
				'bookit_version_incompatible',
				sprintf(
					'Bookit %1$s requires Bookit core version %2$s or higher. Current version: %3$s',
					sanitize_text_field( $args['name'] ),
					sanitize_text_field( $args['requires_core'] ),
					BOOKIT_VERSION
				)
			);
		}

		self::$extensions[ $sanitized_slug ] = array(
			'name'          => sanitize_text_field( $args['name'] ),
			'slug'          => $sanitized_slug,
			'version'       => sanitize_text_field( $args['version'] ),
			'requires_core' => sanitize_text_field( $args['requires_core'] ),
			'description'   => sanitize_text_field( $args['description'] ?? '' ),
			'author'        => sanitize_text_field( $args['author'] ?? '' ),
			'registered_at' => current_time( 'mysql' ),
		);

		return true;
	}

	/**
	 * Register a nav item for the dashboard sidebar.
	 *
	 * @param array $args Nav item args.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function register_nav_item( array $args ): true|WP_Error {
		$required_fields = array( 'label', 'route', 'icon', 'slug' );

		foreach ( $required_fields as $field ) {
			if ( empty( $args[ $field ] ) ) {
				return new WP_Error(
					'bookit_missing_field',
					sprintf( 'Missing required nav item field: %s', $field )
				);
			}
		}

		$slug = sanitize_key( $args['slug'] );
		if ( ! isset( self::$extensions[ $slug ] ) ) {
			return new WP_Error(
				'bookit_unknown_extension',
				sprintf( 'Cannot register nav item for unknown extension slug: %s', $slug )
			);
		}

		self::$nav_items[] = array(
			'label'      => sanitize_text_field( $args['label'] ),
			'route'      => esc_url_raw( $args['route'] ),
			'icon'       => sanitize_key( $args['icon'] ),
			'position'   => absint( $args['position'] ?? 100 ),
			'capability' => sanitize_key( $args['capability'] ?? 'bookit_manage_all' ),
			'slug'       => $slug,
		);

		usort(
			self::$nav_items,
			function ( $a, $b ) {
				return $a['position'] <=> $b['position'];
			}
		);

		return true;
	}

	/**
	 * Get registered extensions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_extensions(): array {
		return array_values( self::$extensions );
	}

	/**
	 * Get registered nav items.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_nav_items(): array {
		return self::$nav_items;
	}

	/**
	 * Check if an extension slug is registered.
	 *
	 * @param string $slug Extension slug.
	 * @return bool
	 */
	public static function is_registered( string $slug ): bool {
		return isset( self::$extensions[ sanitize_key( $slug ) ] );
	}
}
