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
	 * Public feed renderer.
	 *
	 * @var Memml_Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = new Memml_Settings();
		$this->renderer = new Memml_Renderer();
	}

	/**
	 * Registers plugin hooks.
	 *
	 * @return void
	 */
	public function register() {
		$this->settings->register();
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_frontend_assets' ) );
		add_shortcode( 'memml_calendar', array( $this, 'render_calendar_shortcode' ) );
		add_shortcode( 'memml_events', array( $this->renderer, 'render_events' ) );
		add_shortcode( 'memml_volunteers', array( $this->renderer, 'render_volunteers' ) );
	}

	/**
	 * Enqueues front-end assets early when the current post uses Memml Calendar.
	 *
	 * Render callbacks also enqueue as a fallback for non-post contexts.
	 *
	 * @return void
	 */
	public function maybe_enqueue_frontend_assets() {
		global $post;

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$content        = $post->post_content;
		$uses_block     = has_block( 'memml/calendar', $post ) ||
			has_block( 'memml/events', $post ) ||
			has_block( 'memml/volunteers', $post );
		$uses_shortcode = has_shortcode( $content, 'memml_calendar' ) ||
			has_shortcode( $content, 'memml_events' ) ||
			has_shortcode( $content, 'memml_volunteers' );

		if ( $uses_block || $uses_shortcode ) {
			$this->renderer->enqueue_assets();
		}
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
			MEMML_PLUGIN_DIR . 'blocks/calendar',
			array( 'render_callback' => array( $this, 'render_calendar_block' ) )
		);
		register_block_type(
			MEMML_PLUGIN_DIR . 'blocks/events',
			array( 'render_callback' => array( $this->renderer, 'render_events' ) )
		);
		register_block_type(
			MEMML_PLUGIN_DIR . 'blocks/volunteers',
			array( 'render_callback' => array( $this->renderer, 'render_volunteers' ) )
		);
	}

	/**
	 * Renders the shared calendar block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_calendar_block( $attributes ) {
		$calendar = isset( $attributes['calendar'] ) ? $attributes['calendar'] : 'events';
		$layout   = isset( $attributes['view'] ) ? $attributes['view'] : 'list';
		$period   = isset( $attributes['period'] ) ? $attributes['period'] : 'upcoming';

		return $this->renderer->render_calendar( $calendar, $layout, $period );
	}

	/**
	 * Renders the shared calendar shortcode.
	 *
	 * @param array|string $attributes Shortcode attributes.
	 * @return string
	 */
	public function render_calendar_shortcode( $attributes ) {
		$attributes = shortcode_atts(
			array(
				'calendar' => 'events',
				'period'   => 'upcoming',
				'view'     => 'list',
			),
			is_array( $attributes ) ? $attributes : array(),
			'memml_calendar'
		);

		return $this->renderer->render_calendar( $attributes['calendar'], $attributes['view'], $attributes['period'] );
	}
}
