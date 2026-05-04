<?php
/**
 * Integration tests for booking wizard flow
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test complete wizard flow and session persistence.
 */
class Test_Wizard_Flow extends WP_UnitTestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Session route.
	 *
	 * @var string
	 */
	private $route = '/wizard/session';

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		Bookit_Session_Manager::clear();
		do_action( 'rest_api_init' );
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
	 * Test complete wizard flow: navigate all 4 steps via API and shortcode.
	 *
	 * @covers Bookit_Session_Manager
	 * @covers Bookit_Wizard_API
	 * @covers Bookit_Shortcodes
	 */
	public function test_complete_wizard_flow() {
		$nonce = wp_create_nonce( 'wp_rest' );

		// Step 1: only current_step.
		$params = array( 'current_step' => 1 );
		$this->post_step_and_assert( 1, $params, $nonce );

		// Step 2: requires service_id so step 2 template can show staff selection.
		$params = array(
			'current_step' => 2,
			'service_id'   => 1,
		);
		$this->post_step_and_assert( 2, $params, $nonce );

		// Step 3: requires service_id and staff_id so step 3 template shows date/time picker.
		$params = array(
			'current_step' => 3,
			'service_id'   => 1,
			'staff_id'     => 1,
		);
		$this->post_step_and_assert( 3, $params, $nonce );

		// Step 4: requires date and time so step 4 (checkout) can show summary.
		$params = array(
			'current_step' => 4,
			'service_id'   => 1,
			'staff_id'     => 1,
			'date'         => '2026-05-15',
			'time'         => '14:00:00',
		);
		$this->post_step_and_assert( 4, $params, $nonce );
	}

	/**
	 * POST session update and assert shortcode output contains step markup.
	 *
	 * @param int    $step   Step number (1–4).
	 * @param array  $params Body params for POST.
	 * @param string $nonce  REST nonce.
	 */
	private function post_step_and_assert( $step, array $params, $nonce ) {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_header( 'X-WP-Nonce', $nonce );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status(), "Step $step POST should succeed" );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( $step, (int) $data['data']['current_step'] );
		$this->assertEquals( $step, (int) Bookit_Session_Manager::get( 'current_step' ) );

		$output = do_shortcode( '[bookit_wizard_v2]' );
		$this->assertStringContainsString( 'bookit-v2-step--' . $step, $output );
	}

	/**
	 * Test that session data persists across multiple API calls (simulated requests).
	 *
	 * @covers Bookit_Session_Manager::get_data
	 * @covers Bookit_Session_Manager::set_data
	 * @covers Bookit_Wizard_API::get_session
	 * @covers Bookit_Wizard_API::update_session
	 */
	public function test_session_persists_across_requests() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			array(
				'current_step' => 2,
				'service_id'   => 10,
				'date'         => '2026-02-15',
			)
		);
		rest_get_server()->dispatch( $request );

		$get_request  = new WP_REST_Request( 'GET', '/' . $this->namespace . $this->route );
		$get_response = rest_get_server()->dispatch( $get_request );
		$data         = $get_response->get_data();

		$this->assertEquals( 200, $get_response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 2, (int) $data['data']['current_step'] );
		$this->assertEquals( 10, (int) $data['data']['service_id'] );
		$this->assertEquals( '2026-02-15', $data['data']['date'] );

		$this->assertEquals( 2, (int) Bookit_Session_Manager::get( 'current_step' ) );
		$this->assertEquals( 10, (int) Bookit_Session_Manager::get( 'service_id' ) );
		$this->assertEquals( '2026-02-15', Bookit_Session_Manager::get( 'date' ) );
	}

	/**
	 * Test wizard renders usable markup without JavaScript (graceful degradation).
	 *
	 * @covers Bookit_Shortcodes::render_booking_wizard_v2
	 */
	public function test_wizard_with_javascript_disabled() {
		$output = do_shortcode( '[bookit_wizard_v2]' );

		$this->assertNotEmpty( $output );
		$this->assertStringContainsString( 'bookit-v2-wizard-container', $output );
		$this->assertStringContainsString( 'bookit-v2-progress', $output );
		$this->assertStringContainsString( 'bookit-v2-step--1', $output );
		$this->assertTrue(
			strpos( $output, 'What would you like to book?' ) !== false || strpos( $output, 'No Services Available' ) !== false,
			'Step 1 should render the service selection or empty state.'
		);
	}

	/**
	 * Test that clearing wizard resets state (isolation between "sessions").
	 *
	 * @covers Bookit_Session_Manager::clear
	 * @covers Bookit_Session_Manager::get_data
	 * @covers Bookit_Wizard_API::update_session
	 */
	public function test_concurrent_sessions_isolated() {
		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . $this->route );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body_params(
			array(
				'current_step' => 3,
				'service_id'   => 99,
				'staff_id'     => 5,
			)
		);
		rest_get_server()->dispatch( $request );

		$this->assertEquals( 3, (int) Bookit_Session_Manager::get( 'current_step' ) );
		$this->assertEquals( 99, (int) Bookit_Session_Manager::get( 'service_id' ) );
		$this->assertEquals( 5, (int) Bookit_Session_Manager::get( 'staff_id' ) );

		Bookit_Session_Manager::clear();

		$data = Bookit_Session_Manager::get_data();
		$this->assertEquals( 1, (int) $data['current_step'] );
		$this->assertNull( $data['service_id'] );
		$this->assertNull( $data['staff_id'] );

		// "New" flow: step 1 again.
		$request->set_body_params( array( 'current_step' => 1 ) );
		rest_get_server()->dispatch( $request );
		$this->assertEquals( 1, (int) Bookit_Session_Manager::get( 'current_step' ) );
		$output = do_shortcode( '[bookit_wizard_v2]' );
		$this->assertStringContainsString( 'bookit-v2-step--1', $output );
	}
}
