<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The public-facing functionality of the plugin.
 */
class Bookit_Public {

	/**
	 * The ID of the plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of the plugin.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the public-facing side.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			BOOKIT_PLUGIN_URL . 'public/css/booking-public.css',
			array(),
			$this->version,
			'all'
		);

		// Enqueue confirmation page styles (when on confirmation page or redirect with session_id).
		if ( is_page( 'booking-confirmed' ) || isset( $_GET['session_id'] ) ) {
			wp_enqueue_style(
				'bookit-confirmation',
				BOOKIT_PLUGIN_URL . 'public/assets/css/confirmation-page.css',
				array(),
				'1.5.1'
			);
		}
	}

	/**
	 * Register the JavaScript for the public-facing side.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			BOOKIT_PLUGIN_URL . 'public/js/booking-public.js',
			array( 'jquery' ),
			$this->version,
			false
		);
	}
}
