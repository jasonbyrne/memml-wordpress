<?php
/**
 * Keeps the phone Month browser fixture in step with the renderer.
 *
 * @package Memml
 */

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/e2e/class-memml-month-phone-fixture.php';

/**
 * Fails when tests/e2e/month-phone.html no longer matches renderer output.
 */
final class Memml_Month_Phone_Fixture_Test extends TestCase {

	/**
	 * Sets up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tears down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * The committed fixture matches what the renderer produces today.
	 *
	 * @return void
	 */
	public function test_fixture_matches_renderer_output() {
		$this->assertFileExists( Memml_Month_Phone_Fixture::PATH );
		$this->assertSame(
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture.
			file_get_contents( Memml_Month_Phone_Fixture::PATH ),
			Memml_Month_Phone_Fixture::render(),
			'tests/e2e/month-phone.html is out of date. Run `composer fixtures` and commit the result.'
		);
	}
}
