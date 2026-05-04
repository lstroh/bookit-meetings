<?php
/**
 * Tests for [bookit_my_packages] shortcode and related asset enqueue.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test My Packages shortcode.
 */
class Test_My_Packages_Shortcode extends WP_UnitTestCase {

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		Bookit_Session_Manager::clear();
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		Bookit_Session_Manager::clear();
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_destroy();
		}
		if ( isset( $_SESSION ) ) {
			$_SESSION = array();
		}
		parent::tearDown();
	}

	/**
	 * @covers Bookit_Shortcodes::__construct
	 */
	public function test_my_packages_shortcode_is_registered() {
		$this->assertTrue( shortcode_exists( 'bookit_my_packages' ) );
	}

	/**
	 * @covers Bookit_Shortcodes::render_my_packages
	 */
	public function test_my_packages_shortcode_renders_email_form_when_no_email() {
		unset( $_GET['customer_email'], $_GET['_bookit_nonce'] );

		$output = do_shortcode( '[bookit_my_packages]' );

		$this->assertStringContainsString( 'bookit-my-packages', $output );
		$this->assertStringContainsString( '<form', $output );
		$this->assertStringContainsString( 'name="customer_email"', $output );
	}

	/**
	 * @covers Bookit_Shortcodes::render_my_packages
	 */
	public function test_my_packages_shortcode_renders_disabled_message_when_packages_off() {
		$this->set_packages_enabled( '0' );

		$_GET['customer_email'] = 'customer@example.com';
		$_GET['_bookit_nonce']  = wp_create_nonce( 'bookit_my_packages_lookup' );

		$output = do_shortcode( '[bookit_my_packages]' );

		$this->assertStringContainsString( 'not currently available', $output );

		unset( $_GET['customer_email'], $_GET['_bookit_nonce'] );
	}

	/**
	 * @covers Bookit_Shortcodes::enqueue_wizard_assets
	 */
	public function test_my_packages_css_enqueued_on_my_packages_page() {
		wp_dequeue_style( 'bookit-my-packages' );
		wp_deregister_style( 'bookit-my-packages' );

		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'My Packages Page',
				'post_status'  => 'publish',
				'post_content' => '[bookit_my_packages]',
			)
		);

		global $post;
		$post = get_post( $post_id );
		setup_postdata( $post );

		do_action( 'wp_enqueue_scripts' );

		$styles = wp_styles();
		$this->assertTrue( in_array( 'bookit-my-packages', $styles->queue, true ), 'bookit-my-packages CSS should be enqueued' );
		$this->assertTrue( in_array( 'bookit-wizard-v2', $styles->queue, true ), 'Shared token stylesheet should load for my-packages' );

		wp_reset_postdata();
	}

	/**
	 * @covers Bookit_Shortcodes::enqueue_wizard_assets
	 */
	public function test_my_packages_css_not_enqueued_on_unrelated_page() {
		wp_dequeue_style( 'bookit-my-packages' );
		wp_deregister_style( 'bookit-my-packages' );
		wp_dequeue_style( 'bookit-wizard-v2' );
		wp_deregister_style( 'bookit-wizard-v2' );

		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'No My Packages Page',
				'post_status'  => 'publish',
				'post_content' => 'No shortcode here.',
			)
		);

		global $post;
		$post = get_post( $post_id );
		setup_postdata( $post );

		do_action( 'wp_enqueue_scripts' );

		$styles = wp_styles();
		$this->assertFalse( in_array( 'bookit-my-packages', $styles->queue, true ), 'bookit-my-packages CSS should not be enqueued' );

		wp_reset_postdata();
	}

	/**
	 * Set packages_enabled setting value.
	 *
	 * @param string $value Setting value.
	 * @return void
	 */
	private function set_packages_enabled( $value ) {
		global $wpdb;

		$wpdb->replace(
			$wpdb->prefix . 'bookings_settings',
			array(
				'setting_key'   => 'packages_enabled',
				'setting_value' => (string) $value,
			),
			array( '%s', '%s' )
		);
	}
}
