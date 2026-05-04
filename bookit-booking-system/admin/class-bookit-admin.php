<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/admin
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The admin-specific functionality of the plugin.
 */
class Bookit_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of this plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_filter( 'bookit_dashboard_js_data', array( $this, 'add_branding_dashboard_js_data' ) );
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			BOOKIT_PLUGIN_URL . 'admin/css/booking-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			BOOKIT_PLUGIN_URL . 'admin/js/booking-admin.js',
			array( 'jquery' ),
			$this->version,
			false
		);
	}

	/**
	 * Add white-label branding payload to dashboard bootstrap data.
	 *
	 * @param array $js_data Existing JS data.
	 * @return array
	 */
	public function add_branding_dashboard_js_data( $js_data ) {
		global $wpdb;

		$defaults = array(
			'logoUrl'          => '',
			'primaryColour'    => '#4F46E5',
			'businessName'     => '',
			'poweredByVisible' => true,
		);

		if ( ! isset( $wpdb ) ) {
			$js_data['branding'] = $defaults;
			return $js_data;
		}

		$table = $wpdb->prefix . 'bookings_settings';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT setting_key, setting_value
				FROM {$table}
				WHERE setting_key IN (%s, %s, %s, %s)",
				'branding_logo_url',
				'branding_primary_colour',
				'branding_business_name',
				'branding_powered_by_visible'
			),
			ARRAY_A
		);

		$branding = $defaults;

		foreach ( $rows as $row ) {
			if ( 'branding_logo_url' === $row['setting_key'] ) {
				$branding['logoUrl'] = (string) $row['setting_value'];
			} elseif ( 'branding_primary_colour' === $row['setting_key'] ) {
				$primary_colour = strtoupper( (string) $row['setting_value'] );
				if ( preg_match( '/^#[0-9A-F]{6}$/', $primary_colour ) ) {
					$branding['primaryColour'] = $primary_colour;
				}
			} elseif ( 'branding_business_name' === $row['setting_key'] ) {
				$branding['businessName'] = (string) $row['setting_value'];
			} elseif ( 'branding_powered_by_visible' === $row['setting_key'] ) {
				$branding['poweredByVisible'] = (bool) $row['setting_value'];
			}
		}

		$js_data['branding'] = $branding;

		return $js_data;
	}
}
