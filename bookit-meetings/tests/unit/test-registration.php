<?php

class Test_Bookit_Meetings_Registration extends WP_UnitTestCase {
	private function login_as_dashboard_user(): void {
		if ( class_exists( 'Bookit_Session' ) ) {
			Bookit_Session::set( 'is_logged_in', true );
			Bookit_Session::set( 'staff_role', 'admin' );
		} else {
			$_SESSION['is_logged_in'] = true;
			$_SESSION['staff_role']   = 'admin';
		}
	}

	public function test_plugin_constants_are_defined(): void {
		$this->assertTrue( defined( 'BOOKIT_MEETINGS_VERSION' ) );
		$this->assertTrue( defined( 'BOOKIT_MEETINGS_PLUGIN_DIR' ) );
		$this->assertTrue( defined( 'BOOKIT_MEETINGS_PLUGIN_URL' ) );
		$this->assertTrue( defined( 'BOOKIT_MEETINGS_REQUIRES_CORE' ) );
	}

	public function test_extension_is_registered_with_core(): void {
		$this->assertTrue( class_exists( 'Bookit_Extension_Registry' ) );
		$this->assertTrue( Bookit_Extension_Registry::is_registered( 'bookit-meetings' ) );
	}

	public function test_loader_class_exists(): void {
		$this->assertTrue( class_exists( 'Bookit_Meetings_Loader' ) );
	}

	public function test_nav_item_registered(): void {
		$this->login_as_dashboard_user();

		$request  = new WP_REST_Request( 'GET', '/bookit/v1/extensions' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data     = $response->get_data();
		$navitems = $data['nav_items'] ?? array();

		$this->assertIsArray( $navitems );

		$found = false;
		foreach ( $navitems as $item ) {
			if ( is_array( $item ) && ( $item['slug'] ?? '' ) === 'bookit-meetings' ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found );
	}
}

