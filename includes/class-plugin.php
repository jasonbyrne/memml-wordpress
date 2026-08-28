<?php
/**
 * Main plugin integration.
 *
 * @package Memml
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires plugin services into WordPress.
 */
final class Memml_Plugin {

	/**
	 * Settings controller.
	 *
	 * @var Memml_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = new Memml_Settings();
	}

	/**
	 * Registers plugin hooks.
	 *
	 * @return void
	 */
	public function register() {
		$this->settings->register();
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Registers the editor bundle and dynamic blocks.
	 *
	 * @return void
	 */
	public function register_blocks() {
		$asset_path = MEMML_PLUGIN_DIR . 'build/index.asset.php';
		$asset      = file_exists( $asset_path )
			? require $asset_path
			: array(
				'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-i18n' ),
				'version'      => MEMML_VERSION,
			);

		wp_register_script(
			'memml-block-editor',
			MEMML_PLUGIN_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'memml-block-editor', 'memml', MEMML_PLUGIN_DIR . 'languages' );

		register_block_type(
			MEMML_PLUGIN_DIR . 'blocks/events',
			array( 'render_callback' => array( $this, 'render_events_block' ) )
		);
		register_block_type(
			MEMML_PLUGIN_DIR . 'blocks/volunteers',
			array( 'render_callback' => array( $this, 'render_volunteers_block' ) )
		);
	}

	/**
	 * M1 dynamic render callback for the events block.
	 *
	 * The shared public renderer is introduced with the M2 display slice.
	 *
	 * @return string
	 */
	public function render_events_block() {
		return '';
	}

	/**
	 * M1 dynamic render callback for the volunteers block.
	 *
	 * The shared public renderer is introduced with the volunteer display slice.
	 *
	 * @return string
	 */
	public function render_volunteers_block() {
		return '';
	}
}
