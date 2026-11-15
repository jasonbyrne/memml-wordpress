<?php
/**
 * Regenerates tests/e2e/month-phone.html from the renderer.
 *
 * Usage: composer fixtures
 *
 * @package Memml
 */

use Brain\Monkey;

require_once dirname( __DIR__ ) . '/bootstrap.php';
require_once __DIR__ . '/class-memml-month-phone-fixture.php';

Monkey\setUp();
$memml_fixture_html = Memml_Month_Phone_Fixture::render();
Monkey\tearDown();

// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Developer script outside WordPress.
file_put_contents( Memml_Month_Phone_Fixture::PATH, $memml_fixture_html );
