<?php
/**
 * Tests for extension registry.
 *
 * @package    Bookit_Booking_System
 * @subpackage Tests
 */

/**
 * Test Bookit_Extension_Registry.
 */
class Test_Bookit_Extension_Registry extends WP_UnitTestCase {

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->reset_registry();
	}

	/**
	 * Tear down each test.
	 */
	public function tearDown(): void {
		$this->reset_registry();
		parent::tearDown();
	}

	/**
	 * @covers Bookit_Extension_Registry::register_extension
	 * @covers Bookit_Extension_Registry::is_registered
	 */
	public function test_register_extension_succeeds_with_valid_args() {
		$slug   = 'test-extension-' . strtolower( wp_generate_password( 6, false, false ) );
		$result = Bookit_Extension_Registry::register_extension(
			array(
				'name'          => 'Test Extension',
				'slug'          => $slug,
				'version'       => '1.0.0',
				'requires_core' => BOOKIT_VERSION,
				'description'   => 'Description',
				'author'        => 'PHPUnit',
			)
		);

		$this->assertTrue( $result );
		$this->assertTrue( Bookit_Extension_Registry::is_registered( $slug ) );
	}

	/**
	 * @covers Bookit_Extension_Registry::register_extension
	 */
	public function test_register_extension_fails_without_required_fields() {
		$result = Bookit_Extension_Registry::register_extension(
			array(
				'slug'          => 'missing-name',
				'version'       => '1.0.0',
				'requires_core' => BOOKIT_VERSION,
			)
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'bookit_missing_field', $result->get_error_code() );
	}

	/**
	 * @covers Bookit_Extension_Registry::register_extension
	 */
	public function test_register_extension_fails_with_incompatible_version() {
		$result = Bookit_Extension_Registry::register_extension(
			array(
				'name'          => 'Incompatible Extension',
				'slug'          => 'incompatible-extension',
				'version'       => '1.0.0',
				'requires_core' => '99.0.0',
			)
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'bookit_version_incompatible', $result->get_error_code() );
	}

	/**
	 * @covers Bookit_Extension_Registry::register_extension
	 */
	public function test_register_extension_rejects_duplicate_slug() {
		Bookit_Extension_Registry::register_extension(
			array(
				'name'          => 'One',
				'slug'          => 'duplicate-slug',
				'version'       => '1.0.0',
				'requires_core' => BOOKIT_VERSION,
			)
		);

		$result = Bookit_Extension_Registry::register_extension(
			array(
				'name'          => 'Two',
				'slug'          => 'duplicate-slug',
				'version'       => '1.1.0',
				'requires_core' => BOOKIT_VERSION,
			)
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'bookit_duplicate_slug', $result->get_error_code() );
	}

	/**
	 * @covers Bookit_Extension_Registry::register_nav_item
	 * @covers Bookit_Extension_Registry::get_nav_items
	 */
	public function test_register_nav_item_succeeds() {
		$slug = 'nav-extension';

		Bookit_Extension_Registry::register_extension(
			array(
				'name'          => 'Nav Extension',
				'slug'          => $slug,
				'version'       => '1.0.0',
				'requires_core' => BOOKIT_VERSION,
			)
		);

		Bookit_Extension_Registry::register_nav_item(
			array(
				'label'    => 'Second',
				'route'    => '/second',
				'icon'     => 'admin-generic',
				'position' => 20,
				'slug'     => $slug,
			)
		);

		$result = Bookit_Extension_Registry::register_nav_item(
			array(
				'label'    => 'First',
				'route'    => '/first',
				'icon'     => 'admin-home',
				'position' => 10,
				'slug'     => $slug,
			)
		);

		$items = Bookit_Extension_Registry::get_nav_items();

		$this->assertTrue( $result );
		$this->assertNotEmpty( $items );
		$this->assertSame( 'First', $items[0]['label'] );
		$this->assertSame( 10, (int) $items[0]['position'] );
	}

	/**
	 * @covers Bookit_Extension_Registry::register_nav_item
	 */
	public function test_register_nav_item_fails_for_unregistered_extension() {
		$result = Bookit_Extension_Registry::register_nav_item(
			array(
				'label' => 'Unknown',
				'route' => '/unknown',
				'icon'  => 'admin-links',
				'slug'  => 'not-registered',
			)
		);

		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * @covers Bookit_Extension_Registry::register_extension
	 * @covers Bookit_Extension_Registry::get_extensions
	 */
	public function test_get_extensions_returns_all_registered() {
		Bookit_Extension_Registry::register_extension(
			array(
				'name'          => 'Extension A',
				'slug'          => 'extension-a',
				'version'       => '1.0.0',
				'requires_core' => BOOKIT_VERSION,
			)
		);

		Bookit_Extension_Registry::register_extension(
			array(
				'name'          => 'Extension B',
				'slug'          => 'extension-b',
				'version'       => '1.0.0',
				'requires_core' => BOOKIT_VERSION,
			)
		);

		$extensions = Bookit_Extension_Registry::get_extensions();
		$this->assertGreaterThanOrEqual( 2, count( $extensions ) );
	}

	/**
	 * Reset static registry state between tests.
	 */
	private function reset_registry(): void {
		$reflection = new ReflectionClass( 'Bookit_Extension_Registry' );

		$extensions_property = $reflection->getProperty( 'extensions' );
		$extensions_property->setAccessible( true );
		$extensions_property->setValue( null, array() );

		$nav_items_property = $reflection->getProperty( 'nav_items' );
		$nav_items_property->setAccessible( true );
		$nav_items_property->setValue( null, array() );
	}
}
