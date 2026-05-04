<?php
/**
 * Tests for staff photo upload endpoint.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test staff photo upload behavior.
 */
class Test_Staff_Photo_Upload_API extends WP_UnitTestCase {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private $namespace = 'bookit/v1';

	/**
	 * Track temp files created during tests.
	 *
	 * @var array
	 */
	private $tmp_files = array();

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );

		$_SESSION = array();

		do_action( 'rest_api_init' );

		// Ensure uploads work in wp-env by bypassing the actual move.
		add_filter(
			'pre_move_uploaded_file',
			function( $false, $file, $new_file ) {
				copy( $file['tmp_name'], $new_file );
				return $new_file;
			},
			10,
			3
		);
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}bookings_staff" );

		foreach ( $this->tmp_files as $path ) {
			if ( is_string( $path ) && file_exists( $path ) ) {
				@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
		$this->tmp_files = array();

		$_SESSION = array();

		parent::tearDown();
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::check_dashboard_permission
	 */
	public function test_photo_upload_requires_authentication() {
		$staff_id = $this->create_test_staff();
		$_SESSION = array();

		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/' . $staff_id . '/photo' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::upload_staff_photo
	 */
	public function test_photo_upload_rejects_non_image_file() {
		$target_id = $this->create_test_staff();
		$admin_id  = $this->create_test_staff();
		$this->login_as( $admin_id, 'admin' );

		$tmp = $this->create_temp_text_file( 'hello' );
		$file = array(
			'name'     => 'test.txt',
			'type'     => 'text/plain',
			'tmp_name' => $tmp,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $tmp ),
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/' . $target_id . '/photo' );
		$request->set_file_params( array( 'photo' => $file ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'invalid_type', $response->as_error()->get_error_code() );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::upload_staff_photo
	 */
	public function test_photo_upload_rejects_oversized_file() {
		$target_id = $this->create_test_staff();
		$admin_id  = $this->create_test_staff();
		$this->login_as( $admin_id, 'admin' );

		$tmp = $this->create_temp_jpeg();
		$file = array(
			'name'     => 'test.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => $tmp,
			'error'    => UPLOAD_ERR_OK,
			// Set size to 6MB without creating a 6MB file.
			'size'     => 6 * 1024 * 1024,
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/' . $target_id . '/photo' );
		$request->set_file_params( array( 'photo' => $file ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'file_too_large', $response->as_error()->get_error_code() );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::upload_staff_photo
	 */
	public function test_staff_cannot_upload_photo_for_other_staff() {
		$staff_1 = $this->create_test_staff();
		$staff_2 = $this->create_test_staff();

		$this->login_as( $staff_1, 'staff' );

		$request  = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/' . $staff_2 . '/photo' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertTrue( $response->is_error() );
		$this->assertEquals( 403, $response->get_status() );
		$this->assertEquals( 'forbidden', $response->as_error()->get_error_code() );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::upload_staff_photo
	 */
	public function test_admin_can_upload_photo_for_any_staff() {
		$target_id = $this->create_test_staff();
		$admin_id  = $this->create_test_staff();
		$this->login_as( $admin_id, 'admin' );

		$tmp = $this->create_temp_jpeg();
		$file = array(
			'name'     => 'test.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => $tmp,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $tmp ),
		);

		$upload_dir = wp_upload_dir();
		$fake_path  = trailingslashit( $upload_dir['path'] ) . 'bookit-test-photo.jpg';
		$this->tmp_files[] = $fake_path;
		imagejpeg( imagecreatetruecolor( 10, 10 ), $fake_path );

		add_filter(
			'wp_handle_upload',
			function( $upload, $context ) use ( $upload_dir, $fake_path ) {
				return array(
					'file' => $fake_path,
					'url'  => trailingslashit( $upload_dir['url'] ) . 'bookit-test-photo.jpg',
					'type' => 'image/jpeg',
				);
			},
			10,
			2
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/' . $target_id . '/photo' );
		$request->set_file_params( array( 'photo' => $file ) );
		$response = rest_get_server()->dispatch( $request );

		remove_all_filters( 'wp_handle_upload' );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'url', $data );
		$this->assertNotEmpty( $data['url'] );
	}

	/**
	 * @covers Bookit_Dashboard_Bookings_API::upload_staff_photo
	 */
	public function test_successful_upload_updates_photo_url_in_db() {
		$target_id = $this->create_test_staff();
		$admin_id  = $this->create_test_staff();
		$this->login_as( $admin_id, 'admin' );

		$tmp = $this->create_temp_jpeg();
		$file = array(
			'name'     => 'test.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => $tmp,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $tmp ),
		);

		$request = new WP_REST_Request( 'POST', '/' . $this->namespace . '/dashboard/staff/' . $target_id . '/photo' );
		$request->set_file_params( array( 'photo' => $file ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'url', $data );

		global $wpdb;
		$photo_url = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT photo_url FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$target_id
			)
		);
		$this->assertEquals( $data['url'], $photo_url );
	}

	/**
	 * Simulate Bookit dashboard login via session.
	 *
	 * @param int    $staff_id Staff row ID.
	 * @param string $role     'admin' or 'staff'.
	 */
	private function login_as( $staff_id, $role = 'staff' ) {
		global $wpdb;

		$staff = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, email, first_name, last_name FROM {$wpdb->prefix}bookings_staff WHERE id = %d",
				$staff_id
			),
			ARRAY_A
		);

		$_SESSION['staff_id']      = (int) $staff['id'];
		$_SESSION['staff_email']   = $staff['email'];
		$_SESSION['staff_role']    = $role;
		$_SESSION['staff_name']    = trim( $staff['first_name'] . ' ' . $staff['last_name'] );
		$_SESSION['is_logged_in']  = true;
		$_SESSION['last_activity'] = time();
	}

	/**
	 * Create test staff member.
	 *
	 * @param array $args Override defaults.
	 * @return int Staff ID.
	 */
	private function create_test_staff( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'email'              => 'staff-' . wp_generate_password( 6, false ) . '@test.com',
			'password_hash'      => password_hash( 'password123', PASSWORD_BCRYPT ),
			'first_name'         => 'Test',
			'last_name'          => 'Staff',
			'phone'              => '07700900000',
			'photo_url'          => null,
			'bio'                => 'Test bio',
			'title'              => 'Therapist',
			'role'               => 'staff',
			'google_calendar_id' => null,
			'is_active'          => 1,
			'display_order'      => 0,
			'notification_preferences' => null,
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
			'deleted_at'         => null,
		);

		$data = wp_parse_args( $args, $defaults );

		$wpdb->insert(
			$wpdb->prefix . 'bookings_staff',
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Create a temporary plain text file.
	 *
	 * @param string $contents Text contents.
	 * @return string
	 */
	private function create_temp_text_file( $contents ) {
		$tmp = tempnam( sys_get_temp_dir(), 'bookit_test_' );
		file_put_contents( $tmp, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		$this->tmp_files[] = $tmp;
		return $tmp;
	}

	/**
	 * Create a temporary valid JPEG image file.
	 *
	 * @return string
	 */
	private function create_temp_jpeg() {
		$tmp = tempnam( sys_get_temp_dir(), 'bookit_test_' );
		imagejpeg( imagecreatetruecolor( 10, 10 ), $tmp );
		$this->tmp_files[] = $tmp;
		return $tmp;
	}
}

