<?php
/**
 * Server-side calendar renderers.
 *
 * @package Memml
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders escaped Memml feed data for blocks and shortcodes.
 */
final class Memml_Renderer {
	/**
	 * Maximum span in which empty intervening month panels are generated.
	 */
	const MAX_CONTIGUOUS_MONTHS = 60;

	/**
	 * Most month-grid days one multi-day item can occupy.
	 */
	const MAX_SPANNED_DAYS = 366;

	/**
	 * Shared handle for the front-end stylesheet and script.
	 */
	const ASSET_HANDLE = 'memml-calendar';

	/**
	 * Site options and attribute spellings for inherited content visibility.
	 */
	const VISIBILITY_PREFERENCES = array(
		'show_images'                 => array( 'showImages', 'show_images' ),
		'show_descriptions'           => array( 'showDescriptions', 'show_descriptions' ),
		'show_item_count'             => array( 'showItemCount', 'show_item_count' ),
		'show_details'                => array( 'showDetails', 'show_details' ),
		'show_venue_cost'             => array( 'showVenueCost', 'show_venue_cost' ),
		'show_volunteer_availability' => array( 'showVolunteerAvailability', 'show_volunteer_availability' ),
		'show_cancelled_events'       => array( 'showCancelledEvents', 'show_cancelled_events' ),
		'show_rsvp'                   => array( 'showRsvp', 'show_rsvp' ),
		'show_registration'           => array( 'showRegistration', 'show_registration' ),
		'show_online'                 => array( 'showOnline', 'show_online' ),
		'show_volunteer_signup'       => array( 'showVolunteerSignup', 'show_volunteer_signup' ),
		'show_add_to_calendar'        => array( 'showAddToCalendar', 'show_add_to_calendar' ),
		'show_event_page'             => array( 'showEventPage', 'show_event_page' ),
	);

	/**
	 * Number of toggle instances rendered during the request.
	 *
	 * @var int
	 */
	private static $calendar_instance = 0;

	/**
	 * Number of month controls rendered during the request.
	 *
	 * @var int
	 */
	private static $month_instance = 0;

	/**
	 * Share-link identifiers already used during the request.
	 *
	 * @var array
	 */
	private static $used_url_keys = array();

	/**
	 * Feed results already resolved during the request.
	 *
	 * @var array
	 */
	private static $feed_results = array();

	/**
	 * Renders the general events calendar.
	 *
	 * @param array|string $attributes Block or shortcode attributes.
	 * @return string
	 */
	public function render_events( $attributes = array() ) {
		$this->enqueue_assets();

		return $this->render_single_feed(
			'events',
			$this->get_layout_from_attributes( $attributes ),
			$this->get_period_from_attributes( $attributes ),
			$this->get_url_key_from_attributes( $attributes ),
			$this->get_limit_from_attributes( $attributes ),
			$this->get_list_style_from_attributes( $attributes ),
			$this->get_subscribe_from_attributes( $attributes ),
			$this->get_control_from_attributes( $attributes, 'layoutSwitcher', 'layout_switcher', 'layout_switcher' ),
			$this->get_control_from_attributes( $attributes, 'periodSwitcher', 'period_switcher', 'period_switcher' ),
			$attributes
		);
	}

	/**
	 * Renders the volunteer opportunities calendar.
	 *
	 * @param array|string $attributes Block or shortcode attributes.
	 * @return string
	 */
	public function render_volunteers( $attributes = array() ) {
		$this->enqueue_assets();

		return $this->render_single_feed(
			'volunteers',
			$this->get_layout_from_attributes( $attributes ),
			$this->get_period_from_attributes( $attributes ),
			$this->get_url_key_from_attributes( $attributes ),
			$this->get_limit_from_attributes( $attributes ),
			$this->get_list_style_from_attributes( $attributes ),
			$this->get_subscribe_from_attributes( $attributes ),
			$this->get_control_from_attributes( $attributes, 'layoutSwitcher', 'layout_switcher', 'layout_switcher' ),
			$this->get_control_from_attributes( $attributes, 'periodSwitcher', 'period_switcher', 'period_switcher' ),
			$attributes
		);
	}

	/**
	 * Renders a committee roster, such as a board of directors.
	 *
	 * @param array|string $attributes Block or shortcode attributes.
	 * @return string
	 */
	public function render_committees( $attributes = array() ) {
		$this->enqueue_assets();

		$result = $this->get_client_result( 'committees' );

		if ( is_wp_error( $result ) ) {
			return $this->render_error( $result );
		}

		$committees = isset( $result['data']['committees'] ) && is_array( $result['data']['committees'] )
			? $result['data']['committees']
			: array();

		if ( empty( $committees ) ) {
			return $this->render_notice( __( 'No committees are currently available.', 'memml' ) );
		}

		$show_charter      = $this->get_committee_toggle_from_attributes( $attributes, 'showCharter', 'show_charter', true );
		$show_vacant_seats = $this->get_committee_toggle_from_attributes( $attributes, 'showVacantSeats', 'show_vacant_seats', true );
		$rosters           = '';

		foreach ( $committees as $committee ) {
			if ( is_array( $committee ) ) {
				$rosters .= $this->render_committee( $committee, $show_charter, $show_vacant_seats );
			}
		}

		return '<div class="memml-calendar memml-calendar--committees' . esc_attr( self::get_appearance_classes( Memml_Settings::get_options() ) ) . '" data-memml-committees>' . $rosters . '</div>';
	}

	/**
	 * Gets a yes/no toggle from block or shortcode attributes.
	 *
	 * @param array|string $attributes    Block or shortcode attributes.
	 * @param string       $camel_case    Block attribute name.
	 * @param string       $snake_case    Shortcode attribute name.
	 * @param bool         $default_value Value used when unset.
	 * @return bool
	 */
	private function get_committee_toggle_from_attributes( $attributes, $camel_case, $snake_case, $default_value ) {
		if ( ! is_array( $attributes ) ) {
			return $default_value;
		}

		$value = isset( $attributes[ $camel_case ] ) ? $attributes[ $camel_case ] : ( isset( $attributes[ $snake_case ] ) ? $attributes[ $snake_case ] : '' );

		if ( '' === $value || null === $value ) {
			return $default_value;
		}

		if ( is_string( $value ) ) {
			return ! in_array( strtolower( $value ), array( '0', 'false', 'no' ), true );
		}

		return (bool) $value;
	}

	/**
	 * Renders one committee's roster.
	 *
	 * @param array $committee         Committee feed record.
	 * @param bool  $show_charter      Whether to show the committee's charter.
	 * @param bool  $show_vacant_seats Whether to show vacant seats.
	 * @return string
	 */
	private function render_committee( $committee, $show_charter, $show_vacant_seats ) {
		$name    = isset( $committee['name'] ) ? (string) $committee['name'] : '';
		$charter = $show_charter && ! empty( $committee['charter'] )
			? '<p class="memml-calendar__committee-charter">' . esc_html( (string) $committee['charter'] ) . '</p>'
			: '';

		$seats = isset( $committee['seats'] ) && is_array( $committee['seats'] ) ? $committee['seats'] : array();
		$rows  = '';

		foreach ( $seats as $seat ) {
			if ( ! is_array( $seat ) ) {
				continue;
			}

			$is_vacant = ! empty( $seat['vacant'] );

			if ( $is_vacant && ! $show_vacant_seats ) {
				continue;
			}

			$rows .= $this->render_committee_seat( $seat, $is_vacant );
		}

		if ( '' === $rows ) {
			$rows = '<li class="memml-calendar__seat">' . esc_html__( 'No seats to show.', 'memml' ) . '</li>';
		}

		return sprintf(
			'<article class="memml-calendar__committee" data-memml-item><h3 class="memml-calendar__title">%1$s</h3>%2$s<ul class="memml-calendar__seat-list">%3$s</ul></article>',
			esc_html( $name ),
			$charter,
			$rows
		);
	}

	/**
	 * Renders one committee seat.
	 *
	 * @param array $seat      Seat feed record.
	 * @param bool  $is_vacant Whether the seat is vacant.
	 * @return string
	 */
	private function render_committee_seat( $seat, $is_vacant ) {
		$title   = isset( $seat['title'] ) ? (string) $seat['title'] : '';
		$holders = isset( $seat['holders'] ) && is_array( $seat['holders'] ) ? $seat['holders'] : array();
		$names   = array();

		foreach ( $holders as $holder ) {
			if ( is_array( $holder ) && ! empty( $holder['name'] ) ) {
				$names[] = (string) $holder['name'];
			}
		}

		$holder_markup = $is_vacant || empty( $names )
			? '<span class="memml-calendar__seat-vacant">' . esc_html__( 'Vacant', 'memml' ) . '</span>'
			: esc_html( implode( ', ', $names ) );

		return sprintf(
			'<li class="memml-calendar__seat%1$s"><span class="memml-calendar__seat-title">%2$s</span><span class="memml-calendar__seat-holder">%3$s</span></li>',
			$is_vacant ? ' memml-calendar__seat--vacant' : '',
			esc_html( $title ),
			$holder_markup
		);
	}

	/**
	 * Renders a visitor-facing calendar switcher.
	 *
	 * @param string $calendar   Initial calendar: events or volunteers, or '' for the site default.
	 * @param string $layout     Initial display layout: list or month, or '' for the site default.
	 * @param string $period     Initial list period: upcoming or past, or '' for the site default.
	 * @param string $url_key    Optional stable share-link identifier.
	 * @param mixed  $limit      Maximum list items per period, 0 for every item, or ''/-1 for the site default.
	 * @param string $list_style List presentation: grid or rows, or '' for the site default.
	 * @param mixed  $subscribe  Whether to offer subscription links; null or '' for the site default.
	 * @param mixed  $layout_switcher Whether visitors can switch between List and Month; null or '' for the site default.
	 * @param mixed  $period_switcher Whether visitors can switch between Upcoming and Past; null or '' for the site default.
	 * @param mixed  $calendar_switcher Whether visitors can switch between feeds; null or '' for the site default.
	 * @param array|string $visibility Content and action visibility attributes.
	 * @return string
	 */
	public function render_calendar( $calendar = '', $layout = '', $period = '', $url_key = '', $limit = '', $list_style = '', $subscribe = null, $layout_switcher = null, $period_switcher = null, $calendar_switcher = null, $visibility = array() ) {
		$this->enqueue_assets();
		++self::$calendar_instance;

		$url_key                = $this->get_unique_url_key( $url_key );
		$query_prefix           = 'memml_' . $url_key . '_';
		$show_calendar_switcher = $this->resolve_control_visibility( $calendar_switcher, 'calendar_switcher' );
		$show_layout_switcher   = $this->resolve_control_visibility( $layout_switcher, 'layout_switcher' );
		$show_period_switcher   = $this->resolve_control_visibility( $period_switcher, 'period_switcher' );
		$calendar               = $this->get_initial_calendar( $calendar, $query_prefix, $show_calendar_switcher );
		$context                = array_merge(
			array(
				'feeds'                => $show_calendar_switcher ? array( 'events', 'volunteers' ) : array( $calendar ),
				'instance_id'          => 'memml-calendar-' . self::$calendar_instance,
				'layout'               => $this->get_initial_layout( $layout, $query_prefix, $show_layout_switcher ),
				'limit'                => $this->resolve_limit( $limit ),
				'list_style'           => $this->resolve_list_style( $list_style ),
				'period'               => $this->get_initial_period( $period, $query_prefix, $show_period_switcher ),
				'query_prefix'         => $query_prefix,
				'show_layout_switcher' => $show_layout_switcher,
				'show_period_switcher' => $show_period_switcher,
				'subscribe'            => $this->resolve_subscribe( $subscribe ),
			),
			$this->get_visibility_from_attributes( $visibility )
		);

		$events_id         = $context['instance_id'] . '-events';
		$volunteers_id     = $context['instance_id'] . '-volunteers';
		$events_layouts    = $show_calendar_switcher || 'events' === $calendar
			? $this->render_layout_panels( 'events', $context, $this->get_client_result( 'events' ) )
			: '';
		$volunteer_layouts = $show_calendar_switcher || 'volunteers' === $calendar
			? $this->render_layout_panels( 'volunteers', $context, $this->get_client_result( 'volunteers' ) )
			: '';
		$source_controls   = '';

		if ( $show_calendar_switcher ) {
			$source_controls = sprintf(
				'<div class="memml-calendar__filter" role="group" aria-label="%1$s">%2$s%3$s</div>',
				esc_attr__( 'Choose a calendar', 'memml' ),
				$this->render_control_link( $context, 'data-memml-view', 'events', __( 'Events', 'memml' ), $events_id, 'events' === $calendar, array( 'calendar' => 'events' ) ),
				$this->render_control_link( $context, 'data-memml-view', 'volunteers', __( 'Volunteer Opportunities', 'memml' ), $volunteers_id, 'volunteers' === $calendar, array( 'calendar' => 'volunteers' ) )
			);
		}

		$toolbar = $this->render_toolbar( $source_controls . $this->render_period_controls( $context ) . $this->render_layout_controls( $context ) );
		$panels  = '';

		if ( $show_calendar_switcher || 'events' === $calendar ) {
			$panels .= sprintf(
				'<div class="memml-calendar__panel" id="%1$s"%2$s>%3$s</div>',
				esc_attr( $events_id ),
				'events' === $calendar ? '' : ' hidden',
				$events_layouts
			);
		}

		if ( $show_calendar_switcher || 'volunteers' === $calendar ) {
			$panels .= sprintf(
				'<div class="memml-calendar__panel" id="%1$s"%2$s>%3$s</div>',
				esc_attr( $volunteers_id ),
				'volunteers' === $calendar ? '' : ' hidden',
				$volunteer_layouts
			);
		}

		return sprintf(
			'<div class="memml-calendar memml-calendar--switchable%8$s" data-memml-calendar data-memml-url-prefix="%1$s" data-calendar="%2$s" data-layout="%3$s" data-period="%4$s">%5$s%6$s%7$s</div>',
			esc_attr( $query_prefix ),
			esc_attr( $calendar ),
			esc_attr( $context['layout'] ),
			esc_attr( $context['period'] ),
			$toolbar,
			$panels,
			$this->render_live_region(),
			esc_attr( self::get_appearance_classes( Memml_Settings::get_options() ) )
		);
	}

	/**
	 * Renders a fixed feed with a visitor-facing layout switcher.
	 *
	 * @param string $feed       Feed identifier.
	 * @param string $layout     Initial display layout, or '' for the site default.
	 * @param string $period     Initial list period, or '' for the site default.
	 * @param string $url_key    Optional stable share-link identifier.
	 * @param mixed  $limit      Maximum list items per period, 0 for every item, or ''/-1 for the site default.
	 * @param string $list_style List presentation: grid or rows, or '' for the site default.
	 * @param mixed  $subscribe  Whether to offer subscription links; null or '' for the site default.
	 * @param mixed  $layout_switcher Whether visitors can switch between List and Month; null or '' for the site default.
	 * @param mixed  $period_switcher Whether visitors can switch between Upcoming and Past; null or '' for the site default.
	 * @param array|string $visibility Content and action visibility attributes.
	 * @return string
	 */
	private function render_single_feed( $feed, $layout, $period, $url_key = '', $limit = 0, $list_style = '', $subscribe = null, $layout_switcher = null, $period_switcher = null, $visibility = array() ) {
		++self::$calendar_instance;

		$url_key              = $this->get_unique_url_key( $url_key );
		$query_prefix         = 'memml_' . $url_key . '_';
		$show_layout_switcher = $this->resolve_control_visibility( $layout_switcher, 'layout_switcher' );
		$show_period_switcher = $this->resolve_control_visibility( $period_switcher, 'period_switcher' );
		$context              = array_merge(
			array(
				'feeds'                => array( $feed ),
				'instance_id'          => 'memml-calendar-' . self::$calendar_instance,
				'layout'               => $this->get_initial_layout( $layout, $query_prefix, $show_layout_switcher ),
				'limit'                => $this->resolve_limit( $limit ),
				'list_style'           => $this->resolve_list_style( $list_style ),
				'period'               => $this->get_initial_period( $period, $query_prefix, $show_period_switcher ),
				'query_prefix'         => $query_prefix,
				'show_layout_switcher' => $show_layout_switcher,
				'show_period_switcher' => $show_period_switcher,
				'subscribe'            => $this->resolve_subscribe( $subscribe ),
			),
			$this->get_visibility_from_attributes( $visibility )
		);
		$toolbar              = $this->render_toolbar( $this->render_period_controls( $context ) . $this->render_layout_controls( $context ) );

		return sprintf(
			'<div class="memml-calendar memml-calendar--%1$s%8$s" data-memml-calendar data-memml-url-prefix="%2$s" data-feed="%1$s" data-layout="%3$s" data-period="%4$s">%5$s%6$s%7$s</div>',
			esc_attr( $feed ),
			esc_attr( $query_prefix ),
			esc_attr( $context['layout'] ),
			esc_attr( $context['period'] ),
			$toolbar,
			$this->render_layout_panels( $feed, $context, $this->get_client_result( $feed ) ),
			$this->render_live_region(),
			esc_attr( self::get_appearance_classes( Memml_Settings::get_options() ) )
		);
	}

	/**
	 * Wraps non-empty visitor controls in the toolbar container.
	 *
	 * @param string $controls Rendered toolbar controls.
	 * @return string
	 */
	private function render_toolbar( $controls ) {
		return '' === $controls ? '' : '<div class="memml-calendar__toolbar">' . $controls . '</div>';
	}

	/**
	 * Builds a shareable URL for one visitor-facing state change.
	 *
	 * Every control is a real link, so the calendar keeps working without
	 * JavaScript and visitors can open a view in a new tab.
	 *
	 * @param array $context Instance render context.
	 * @param array $changes Unprefixed query parameters to set.
	 * @return string
	 */
	private function build_state_url( $context, $changes ) {
		$arguments = array();

		foreach ( $changes as $name => $value ) {
			$arguments[ $context['query_prefix'] . $name ] = '' === $value ? false : $value;
		}

		return add_query_arg( $arguments );
	}

	/**
	 * Renders one visitor control as a shareable link.
	 *
	 * @param array  $context    Instance render context.
	 * @param string $attribute  Behaviour data attribute name.
	 * @param string $value      Behaviour data attribute value.
	 * @param string $label      Visible link text.
	 * @param string $controls   Space-separated IDs the link switches.
	 * @param bool   $is_current Whether the link represents the shown state.
	 * @param array  $changes    Unprefixed query parameters the link sets.
	 * @return string
	 */
	private function render_control_link( $context, $attribute, $value, $label, $controls, $is_current, $changes ) {
		return sprintf(
			'<a aria-controls="%1$s"%2$s class="memml-calendar__filter-button" %3$s="%4$s" href="%5$s">%6$s</a>',
			esc_attr( $controls ),
			$is_current ? ' aria-current="true"' : '',
			esc_attr( $attribute ),
			esc_attr( $value ),
			esc_url( $this->build_state_url( $context, $changes ) ),
			esc_html( $label )
		);
	}

	/**
	 * Renders the List and Month visitor controls.
	 *
	 * @param array $context Instance render context.
	 * @return string
	 */
	private function render_layout_controls( $context ) {
		if ( empty( $context['show_layout_switcher'] ) ) {
			return '';
		}

		$list_ids  = array();
		$month_ids = array();

		foreach ( $context['feeds'] as $feed ) {
			$list_ids[]  = $context['instance_id'] . '-' . $feed . '-list';
			$month_ids[] = $context['instance_id'] . '-' . $feed . '-month';
		}

		return sprintf(
			'<div class="memml-calendar__filter memml-calendar__layout-filter" role="group" aria-label="%1$s">%2$s%3$s</div>',
			esc_attr__( 'Choose a display view', 'memml' ),
			$this->render_control_link( $context, 'data-memml-layout', 'list', __( 'List', 'memml' ), implode( ' ', $list_ids ), 'list' === $context['layout'], array( 'view' => 'list' ) ),
			$this->render_control_link( $context, 'data-memml-layout', 'month', __( 'Month', 'memml' ), implode( ' ', $month_ids ), 'month' === $context['layout'], array( 'view' => 'month' ) )
		);
	}

	/**
	 * Renders the Upcoming and Past list controls.
	 *
	 * @param array $context Instance render context.
	 * @return string
	 */
	private function render_period_controls( $context ) {
		if (
			empty( $context['show_period_switcher'] ) ||
			( empty( $context['show_layout_switcher'] ) && 'list' !== $context['layout'] )
		) {
			return '';
		}

		$upcoming_ids = array();
		$past_ids     = array();

		foreach ( $context['feeds'] as $feed ) {
			$upcoming_ids[] = $context['instance_id'] . '-' . $feed . '-upcoming';
			$past_ids[]     = $context['instance_id'] . '-' . $feed . '-past';
		}

		return sprintf(
			'<div class="memml-calendar__filter memml-calendar__period-filter" data-memml-period-controls role="group" aria-label="%1$s"%2$s>%3$s%4$s</div>',
			esc_attr__( 'Filter by date', 'memml' ),
			'list' === $context['layout'] ? '' : ' hidden',
			$this->render_control_link( $context, 'data-memml-period', 'upcoming', __( 'Upcoming', 'memml' ), implode( ' ', $upcoming_ids ), 'upcoming' === $context['period'], array( 'period' => 'upcoming' ) ),
			$this->render_control_link( $context, 'data-memml-period', 'past', __( 'Past', 'memml' ), implode( ' ', $past_ids ), 'past' === $context['period'], array( 'period' => 'past' ) )
		);
	}

	/**
	 * Renders both display layouts for one feed.
	 *
	 * @param string         $feed    Feed identifier.
	 * @param array          $context Instance render context.
	 * @param array|WP_Error $result  Feed client result.
	 * @return string
	 */
	private function render_layout_panels( $feed, $context, $result ) {
		$list_id   = $context['instance_id'] . '-' . $feed . '-list';
		$month_id  = $context['instance_id'] . '-' . $feed . '-month';
		$subscribe = empty( $context['subscribe'] ) ? '' : $this->render_subscribe_row( $feed, $result );

		if ( empty( $context['show_layout_switcher'] ) ) {
			if ( 'month' === $context['layout'] ) {
				return $subscribe . sprintf(
					'<div data-memml-layout-panel="month" id="%1$s">%2$s</div>',
					esc_attr( $month_id ),
					$this->render_feed_panel( $feed, 'month', $result, 'upcoming', $context )
				);
			}

			return $subscribe . sprintf(
				'<div data-memml-layout-panel="list" id="%1$s">%2$s</div>',
				esc_attr( $list_id ),
				$this->render_period_panels( $feed, $context, $result )
			);
		}

		$list_html  = $this->render_period_panels( $feed, $context, $result );
		$month_html = $this->render_feed_panel( $feed, 'month', $result, 'upcoming', $context );

		return $subscribe . sprintf(
			'<div data-memml-layout-panel="list" id="%1$s"%2$s>%3$s</div><div data-memml-layout-panel="month" id="%4$s"%5$s>%6$s</div>',
			esc_attr( $list_id ),
			'list' === $context['layout'] ? '' : ' hidden',
			$list_html,
			esc_attr( $month_id ),
			'month' === $context['layout'] ? '' : ' hidden',
			$month_html
		);
	}

	/**
	 * Renders calendar subscription links for one feed.
	 *
	 * The feed envelope advertises its own ICS and RSS URLs, so visitors can
	 * follow the calendar from their own calendar application instead of
	 * saving one event at a time.
	 *
	 * @param string         $feed   Feed identifier.
	 * @param array|WP_Error $result Feed client result.
	 * @return string
	 */
	private function render_subscribe_row( $feed, $result ) {
		if ( is_wp_error( $result ) || ! isset( $result['data']['links'] ) || ! is_array( $result['data']['links'] ) ) {
			return '';
		}

		$links = $result['data']['links'];
		$ics   = isset( $links['ics'] ) ? (string) $links['ics'] : '';

		if ( 0 !== strpos( $ics, 'http' ) ) {
			return '';
		}

		$webcal  = preg_replace( '#^https?://#', 'webcal://', $ics );
		$google  = 'https://calendar.google.com/calendar/render?cid=' . rawurlencode( $ics );
		$buttons = sprintf(
			'<a class="memml-calendar__button" href="%1$s" rel="noopener" target="_blank">%2$s</a><a class="memml-calendar__button" href="%3$s">%4$s</a>',
			esc_url( $google ),
			esc_html__( 'Google Calendar', 'memml' ),
			esc_url( $webcal, array( 'webcal', 'https', 'http' ) ),
			esc_html__( 'Apple / Outlook', 'memml' )
		);

		if ( ! empty( $links['rss'] ) ) {
			$buttons .= sprintf(
				'<a class="memml-calendar__calendar-link" href="%1$s">%2$s</a>',
				esc_url( $links['rss'] ),
				esc_html__( 'RSS', 'memml' )
			);
		}

		return sprintf(
			'<div class="memml-calendar__subscribe"><span class="memml-calendar__subscribe-label">%1$s</span><div class="memml-calendar__actions">%2$s</div></div>',
			'volunteers' === $feed
				? esc_html__( 'Subscribe to volunteer opportunities', 'memml' )
				: esc_html__( 'Subscribe to events', 'memml' ),
			$buttons
		);
	}

	/**
	 * Renders one feed's content in the requested layout.
	 *
	 * @param string         $feed    Feed identifier.
	 * @param string         $layout  Display layout.
	 * @param array|WP_Error $result  Feed client result.
	 * @param string         $period  List period.
	 * @param array          $context Instance render context.
	 * @return string
	 */
	private function render_feed_panel( $feed, $layout, $result, $period, $context ) {
		return 'events' === $feed
			? $this->render_events_panel( $layout, $result, $period, $context )
			: $this->render_volunteers_panel( $layout, $result, $period, $context );
	}

	/**
	 * Renders both list periods for one feed.
	 *
	 * @param string         $feed    Feed identifier.
	 * @param array          $context Instance render context.
	 * @param array|WP_Error $result  Feed client result.
	 * @return string
	 */
	private function render_period_panels( $feed, $context, $result ) {
		$upcoming_id = $context['instance_id'] . '-' . $feed . '-upcoming';
		$past_id     = $context['instance_id'] . '-' . $feed . '-past';

		if ( empty( $context['show_period_switcher'] ) ) {
			return sprintf(
				'<div data-memml-period-panel="%1$s" id="%2$s">%3$s</div>',
				esc_attr( $context['period'] ),
				esc_attr( 'past' === $context['period'] ? $past_id : $upcoming_id ),
				$this->render_feed_panel( $feed, 'list', $result, $context['period'], $context )
			);
		}

		return sprintf(
			'<div data-memml-period-panel="upcoming" id="%1$s"%2$s>%3$s</div><div data-memml-period-panel="past" id="%4$s"%5$s>%6$s</div>',
			esc_attr( $upcoming_id ),
			'upcoming' === $context['period'] ? '' : ' hidden',
			$this->render_feed_panel( $feed, 'list', $result, 'upcoming', $context ),
			esc_attr( $past_id ),
			'past' === $context['period'] ? '' : ' hidden',
			$this->render_feed_panel( $feed, 'list', $result, 'past', $context )
		);
	}

	/**
	 * Renders the events feed content.
	 *
	 * @param string         $layout  Display layout.
	 * @param array|WP_Error $result  Feed client result.
	 * @param string         $period  List period.
	 * @param array          $context Instance render context.
	 * @return string
	 */
	private function render_events_panel( $layout, $result, $period, $context ) {
		if ( is_wp_error( $result ) ) {
			return $this->render_error( $result );
		}

		$events = isset( $result['data']['events'] ) && is_array( $result['data']['events'] )
			? $result['data']['events']
			: array();

		if ( ! $this->is_visible( $context, 'show_cancelled_events' ) ) {
			$events = array_values(
				array_filter(
					$events,
					static function ( $event ) {
						return ! is_array( $event ) || 'cancelled' !== ( isset( $event['status'] ) ? $event['status'] : '' );
					}
				)
			);
		}

		if ( empty( $events ) ) {
			return $this->render_notice( __( 'No events are currently available.', 'memml' ) );
		}

		$timezone = $this->get_timezone( $result['data'] );

		if ( 'month' === $layout ) {
			return $this->render_month_calendar( $events, 'events', $timezone, $context );
		}

		$events = $this->filter_list_items( $events, $period, $timezone, $context['limit'] );

		if ( empty( $events ) ) {
			return $this->render_notice(
				'past' === $period
					? __( 'No past events are currently available.', 'memml' )
					: __( 'No upcoming events are currently available.', 'memml' )
			);
		}

		$cards   = '';
		$count   = 0;
		$is_rows = 'rows' === $context['list_style'];

		foreach ( $events as $event ) {
			if ( is_array( $event ) ) {
				$cards .= $is_rows
					? $this->render_event_row( $event, $timezone, 'past' === $period, $context )
					: $this->render_event_card( $event, $timezone, 'past' === $period, $context );
				++$count;
			}
		}

		$caption = sprintf(
			'past' === $period
				/* translators: %d: Number of events shown. */
				? _n( '%d past event', '%d past events', $count, 'memml' )
				/* translators: %d: Number of events shown. */
				: _n( '%d upcoming event', '%d upcoming events', $count, 'memml' ),
			$count
		);

		$count_caption = $this->is_visible( $context, 'show_item_count' ) ? $this->render_count_caption( $caption ) : '';

		return $count_caption . '<div class="' . esc_attr( $this->get_grid_class( $context ) ) . '">' . $cards . '</div>';
	}

	/**
	 * Renders the volunteer feed content.
	 *
	 * @param string         $layout  Display layout.
	 * @param array|WP_Error $result  Feed client result.
	 * @param string         $period  List period.
	 * @param array          $context Instance render context.
	 * @return string
	 */
	private function render_volunteers_panel( $layout, $result, $period, $context ) {
		if ( is_wp_error( $result ) ) {
			return $this->render_error( $result );
		}

		$data          = $result['data'];
		$opportunities = array();

		if ( isset( $data['volunteerOpportunities'] ) && is_array( $data['volunteerOpportunities'] ) ) {
			$opportunities = $data['volunteerOpportunities'];
		} elseif ( isset( $data['opportunities'] ) && is_array( $data['opportunities'] ) ) {
			$opportunities = $data['opportunities'];
		}

		if ( empty( $opportunities ) ) {
			return $this->render_notice( __( 'No volunteer opportunities are currently available.', 'memml' ) );
		}

		$timezone = $this->get_timezone( $data );

		if ( 'month' === $layout ) {
			return $this->render_month_calendar( $opportunities, 'volunteers', $timezone, $context );
		}

		$opportunities = $this->filter_list_items( $opportunities, $period, $timezone, $context['limit'] );

		if ( empty( $opportunities ) ) {
			return $this->render_notice(
				'past' === $period
					? __( 'No past volunteer opportunities are currently available.', 'memml' )
					: __( 'No upcoming volunteer opportunities are currently available.', 'memml' )
			);
		}

		$cards   = '';
		$count   = 0;
		$is_rows = 'rows' === $context['list_style'];

		foreach ( $opportunities as $opportunity ) {
			if ( is_array( $opportunity ) ) {
				$cards .= $is_rows
					? $this->render_volunteer_row( $opportunity, $timezone, 'past' === $period, $context )
					: $this->render_volunteer_card( $opportunity, $timezone, 'past' === $period, $context );
				++$count;
			}
		}

		$caption = sprintf(
			'past' === $period
				/* translators: %d: Number of volunteer opportunities shown. */
				? _n( '%d past volunteer opportunity', '%d past volunteer opportunities', $count, 'memml' )
				/* translators: %d: Number of volunteer opportunities shown. */
				: _n( '%d upcoming volunteer opportunity', '%d upcoming volunteer opportunities', $count, 'memml' ),
			$count
		);

		$count_caption = $this->is_visible( $context, 'show_item_count' ) ? $this->render_count_caption( $caption ) : '';

		return $count_caption . '<div class="' . esc_attr( $this->get_grid_class( $context ) ) . '">' . $cards . '</div>';
	}

	/**
	 * Renders the item-count caption shown above a list.
	 *
	 * @param string $caption Caption text.
	 * @return string
	 */
	private function render_count_caption( $caption ) {
		return '<p class="memml-calendar__count">' . esc_html( $caption ) . '</p>';
	}

	/**
	 * Gets the list container class for the configured list style.
	 *
	 * @param array $context Instance render context.
	 * @return string
	 */
	private function get_grid_class( $context ) {
		return 'rows' === ( isset( $context['list_style'] ) ? $context['list_style'] : 'grid' )
			? 'memml-calendar__grid memml-calendar__grid--rows'
			: 'memml-calendar__grid';
	}

	/**
	 * Filters and sorts list items relative to today in the organization timezone.
	 *
	 * @param array        $items    Feed records.
	 * @param string       $period   Upcoming or past.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @param int          $limit    Maximum items to keep, or 0 for every item.
	 * @return array
	 */
	private function filter_list_items( $items, $period, $timezone, $limit = 0 ) {
		$today_date = $this->get_today( $timezone )->format( 'Y-m-d' );
		$filtered   = array();
		$position   = 0;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$date = $this->get_item_datetime( $item, $timezone );

			if ( ! $date ) {
				continue;
			}

			// A multi-day item stays upcoming until its last day has passed.
			$is_past = $this->get_item_last_datetime( $item, $timezone )->format( 'Y-m-d' ) < $today_date;

			if ( ( 'past' === $period ) !== $is_past ) {
				continue;
			}

			$filtered[] = array(
				'item'      => $item,
				'position'  => $position,
				'timestamp' => $date->getTimestamp(),
			);
			++$position;
		}

		usort(
			$filtered,
			static function ( $left, $right ) use ( $period ) {
				if ( $left['timestamp'] === $right['timestamp'] ) {
					return $left['position'] <=> $right['position'];
				}

				return 'past' === $period
					? $right['timestamp'] <=> $left['timestamp']
					: $left['timestamp'] <=> $right['timestamp'];
			}
		);

		$filtered = array_column( $filtered, 'item' );

		return $limit > 0 ? array_slice( $filtered, 0, $limit ) : $filtered;
	}

	/**
	 * Renders feed items in one or more organization-timezone month grids.
	 *
	 * @param array        $items    Event or opportunity records.
	 * @param string       $feed     Feed identifier.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @param array        $context  Instance render context.
	 * @return string
	 */
	private function render_month_calendar( $items, $feed, $timezone, $context ) {
		$months = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$date = $this->get_item_datetime( $item, $timezone );

			if ( ! $date ) {
				continue;
			}

			// A multi-day item appears on every local day it runs, even
			// across month boundaries, as it does on memml.com.
			$current  = $date->setTime( 0, 0 );
			$last_key = $this->get_item_last_datetime( $item, $timezone )->format( 'Y-m-d' );
			$spanned  = 0;

			do {
				$month_key = $current->format( 'Y-m' );
				$day       = (int) $current->format( 'j' );

				if ( ! isset( $months[ $month_key ] ) ) {
					$months[ $month_key ] = array(
						'first_day' => $current->modify( 'first day of this month' )->setTime( 0, 0 ),
						'days'      => array(),
					);
				}

				if ( ! isset( $months[ $month_key ]['days'][ $day ] ) ) {
					$months[ $month_key ]['days'][ $day ] = array();
				}

				$months[ $month_key ]['days'][ $day ][] = $item;
				$current                                = $current->modify( '+1 day' );
				++$spanned;
			} while ( $current->format( 'Y-m-d' ) <= $last_key && $spanned < self::MAX_SPANNED_DAYS );
		}

		if ( empty( $months ) ) {
			return $this->render_notice( __( 'No dated calendar items are currently available.', 'memml' ) );
		}

		ksort( $months );
		$months = $this->fill_month_range( $months, $timezone );
		++self::$month_instance;

		$calendar_id     = 'memml-month-calendar-' . self::$month_instance;
		$month_keys      = array_keys( $months );
		$month_count     = count( $months );
		$requested_month = $this->get_requested_month( $context['query_prefix'] );
		$selected_index  = array_search( $requested_month, $month_keys, true );

		if ( false === $selected_index ) {
			$selected_index = $this->get_default_month_index( $month_keys, $timezone );
		}

		$selected_month = $months[ $month_keys[ $selected_index ] ];
		$first_label    = wp_date( 'F Y', $selected_month['first_day']->getTimestamp(), $timezone );
		$navigation     = sprintf(
			'<div class="memml-calendar__month-header"><h3 aria-live="polite" class="memml-calendar__month-label" data-memml-month-label>%s</h3></div>',
			esc_html( $first_label )
		);

		if ( $month_count > 1 ) {
			$navigation = sprintf(
				'<div class="memml-calendar__month-header">%1$s<h3 aria-live="polite" class="memml-calendar__month-label" data-memml-month-label>%2$s</h3>%3$s</div>',
				$this->render_month_link( $context, $calendar_id, 'prev', $month_keys, $selected_index - 1, $selected_index ),
				esc_html( $first_label ),
				$this->render_month_link( $context, $calendar_id, 'next', $month_keys, $selected_index + 1, $selected_index )
			);
		}

		$panels = '';
		$index  = 0;

		foreach ( $months as $month_key => $month ) {
			$panels .= $this->render_month_panel( $month, $month_key, $feed, $timezone, $index, $selected_index, $context );
			++$index;
		}

		return sprintf(
			'<div class="memml-calendar__month" data-feed="%1$s" data-memml-month-calendar data-month-count="%2$d">%3$s<div id="%4$s">%5$s</div></div>',
			esc_attr( $feed ),
			$month_count,
			$navigation,
			esc_attr( $calendar_id ),
			$panels
		);
	}

	/**
	 * Renders one month navigation link.
	 *
	 * Anchors cannot be disabled, and an anchor without an href leaves the
	 * accessibility tree entirely. An unreachable month therefore keeps a link
	 * to the month already shown -- a no-op without JavaScript -- and is marked
	 * with aria-disabled so assistive technology still announces it as inactive.
	 *
	 * @param array  $context       Instance render context.
	 * @param string $calendar_id   ID of the element the link switches.
	 * @param string $direction     Either prev or next.
	 * @param array  $month_keys    Month keys in chronological order.
	 * @param int    $index         Target month index.
	 * @param int    $current_index Month index currently shown.
	 * @return string
	 */
	private function render_month_link( $context, $calendar_id, $direction, $month_keys, $index, $current_index ) {
		$is_available = isset( $month_keys[ $index ] );
		$target       = $is_available ? $month_keys[ $index ] : $month_keys[ $current_index ];

		return sprintf(
			'<a aria-controls="%1$s"%2$s aria-label="%3$s" class="memml-calendar__month-button" data-memml-month-%4$s href="%5$s">%6$s</a>',
			esc_attr( $calendar_id ),
			$is_available ? '' : ' aria-disabled="true"',
			'prev' === $direction ? esc_attr__( 'Previous month', 'memml' ) : esc_attr__( 'Next month', 'memml' ),
			esc_attr( $direction ),
			esc_url( $this->build_state_url( $context, array( 'month' => $target ) ) ),
			'prev' === $direction ? '&lsaquo;' : '&rsaquo;'
		);
	}

	/**
	 * Renders one month table.
	 *
	 * @param array        $month          Grouped month data.
	 * @param string       $month_key      Month key in YYYY-MM format.
	 * @param string       $feed           Feed identifier.
	 * @param DateTimeZone $timezone       Organization timezone.
	 * @param int          $index          Month index.
	 * @param int          $selected_index Initially selected month index.
	 * @param array        $context        Instance render context.
	 * @return string
	 */
	private function render_month_panel( $month, $month_key, $feed, $timezone, $index, $selected_index, $context = array() ) {
		$first_day     = $month['first_day'];
		$month_label   = wp_date( 'F Y', $first_day->getTimestamp(), $timezone );
		$start_of_week = min( 6, max( 0, (int) get_option( 'start_of_week', 0 ) ) );
		$first_weekday = (int) $first_day->format( 'w' );
		$offset        = ( $first_weekday - $start_of_week + 7 ) % 7;
		$days_in_month = (int) $first_day->format( 't' );
		$cell_count    = (int) ceil( ( $offset + $days_in_month ) / 7 ) * 7;
		$weekday_row   = '';
		$today         = $this->get_today( $timezone )->format( 'Y-m-d' );
		$reference_day = new DateTimeImmutable( '2024-01-07 12:00:00', $timezone );

		for ( $column = 0; $column < 7; ++$column ) {
			$weekday_index = ( $start_of_week + $column ) % 7;
			$weekday       = $reference_day->modify( '+' . $weekday_index . ' days' );
			$weekday_row  .= '<th scope="col">' . esc_html( wp_date( 'D', $weekday->getTimestamp(), $timezone ) ) . '</th>';
		}

		$rows = '';

		for ( $cell = 0; $cell < $cell_count; ++$cell ) {
			if ( 0 === $cell % 7 ) {
				$rows .= '<tr>';
			}

			$day = $cell - $offset + 1;

			if ( $day < 1 || $day > $days_in_month ) {
				$rows .= '<td aria-hidden="true" class="memml-calendar__month-day is-empty"></td>';
			} else {
				$date       = $first_day->setDate( (int) $first_day->format( 'Y' ), (int) $first_day->format( 'n' ), $day );
				$date_label = wp_date( get_option( 'date_format' ), $date->getTimestamp(), $timezone );
				$is_today   = $date->format( 'Y-m-d' ) === $today;
				$entries    = '';

				if ( $is_today ) {
					/* translators: %s: Formatted date. */
					$date_label = sprintf( __( 'Today, %s', 'memml' ), $date_label );
				}

				if ( ! empty( $month['days'][ $day ] ) ) {
					$day_items = array_values( $month['days'][ $day ] );
					$total     = count( $day_items );

					// A crowded day keeps its row height in check: beyond
					// three items, two stay visible and the rest collapse
					// behind a native disclosure that works without script.
					$shown    = $total > 3 ? 2 : $total;
					$overflow = '';

					foreach ( $day_items as $item_index => $item ) {
						$entry = $this->render_month_entry( $item, $feed, $timezone, $context, $date );

						if ( $item_index < $shown ) {
							$entries .= $entry;
						} else {
							$overflow .= $entry;
						}
					}

					if ( '' !== $overflow ) {
						$entries .= sprintf(
							'<details class="memml-calendar__month-more"><summary>%1$s</summary>%2$s</details>',
							esc_html(
								sprintf(
									/* translators: %d: Number of additional items on the same day. */
									_n( '+%d more', '+%d more', $total - $shown, 'memml' ),
									$total - $shown
								)
							),
							$overflow
						);
					}
				}

				$rows .= sprintf(
					'<td aria-label="%1$s" class="memml-calendar__month-day%2$s%3$s"%4$s><span class="memml-calendar__day-number">%5$d</span>%6$s</td>',
					esc_attr( $date_label ),
					'' === $entries ? '' : ' has-items',
					$is_today ? ' is-today' : '',
					$is_today ? ' aria-current="date"' : '',
					$day,
					$entries
				);
			}

			if ( 6 === $cell % 7 ) {
				$rows .= '</tr>';
			}
		}

		return sprintf(
			'<section class="memml-calendar__month-panel" data-memml-month-index="%1$d" data-month="%2$s" data-month-label="%3$s"%4$s><div aria-label="%3$s" class="memml-calendar__month-scroll" role="region" tabindex="0"><table class="memml-calendar__month-table"><caption class="screen-reader-text">%3$s</caption><thead><tr>%5$s</tr></thead><tbody>%6$s</tbody></table></div></section>',
			$index,
			esc_attr( $month_key ),
			esc_attr( $month_label ),
			$selected_index === $index ? '' : ' hidden',
			$weekday_row,
			$rows
		);
	}

	/**
	 * Renders one compact item in a month day.
	 *
	 * @param array                  $item     Feed record.
	 * @param string                 $feed     Feed identifier.
	 * @param DateTimeZone           $timezone Organization timezone.
	 * @param array                  $context  Instance render context.
	 * @param DateTimeInterface|null $day      Month-grid day the entry is shown on.
	 * @return string
	 */
	private function render_month_entry( $item, $feed, $timezone, $context = array(), $day = null ) {
		$title   = isset( $item['title'] ) ? (string) $item['title'] : '';
		$time    = $this->render_time_only( $item, $timezone, $day );
		$start   = $this->get_item_datetime( $item, $timezone );
		$class   = $day && $start && $day->format( 'Y-m-d' ) !== $start->format( 'Y-m-d' )
			? 'memml-calendar__month-entry is-continued'
			: 'memml-calendar__month-entry';
		$is_past = $this->is_item_past( $item, $timezone );
		$status  = '';
		$details = '';
		$actions = '';

		if ( 'events' === $feed ) {
			$event_status = isset( $item['status'] ) ? (string) $item['status'] : 'scheduled';

			if ( in_array( $event_status, array( 'cancelled', 'postponed' ), true ) ) {
				$status = sprintf(
					'<span class="memml-calendar__month-status memml-calendar__month-status--%1$s">%2$s</span>',
					esc_attr( $event_status ),
					'cancelled' === $event_status ? esc_html__( 'Cancelled', 'memml' ) : esc_html__( 'Postponed', 'memml' )
				);
			}

			$actions = $this->render_event_actions( $item, $timezone, $context, $is_past );
		} else {
			if ( $this->is_visible( $context, 'show_volunteer_availability' ) && isset( $item['spotsRemaining'] ) ) {
				$spots   = max( 0, (int) $item['spotsRemaining'] );
				$details = '<span class="memml-calendar__month-spots">' . esc_html(
					sprintf(
						/* translators: %d: Number of volunteer positions still available. */
						_n( '%d spot', '%d spots', $spots, 'memml' ),
						$spots
					)
				) . '</span>';
			}

			if (
				$this->is_visible( $context, 'show_volunteer_signup' ) &&
				$this->is_item_actionable( $item, $timezone ) &&
				! empty( $item['url'] )
			) {
				$actions = sprintf(
					'<div class="memml-calendar__actions"><a class="memml-calendar__calendar-link" href="%1$s">%2$s</a></div>',
					esc_url( $item['url'] ),
					esc_html__( 'Volunteer', 'memml' )
				);
			}
		}

		return sprintf(
			'<article class="%7$s" data-memml-item>%1$s<h4 class="memml-calendar__month-title">%2$s</h4>%3$s%4$s%5$s%6$s</article>',
			$status,
			esc_html( $title ),
			$time,
			$details,
			$actions,
			$this->render_details( $item, $feed, $timezone, $is_past, $context ),
			esc_attr( $class )
		);
	}

	/**
	 * Gets an item's local date and time.
	 *
	 * @param array        $item     Feed record.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @return DateTimeImmutable|null
	 */
	private function get_item_datetime( $item, $timezone ) {
		try {
			if ( ! empty( $item['startsAt'] ) ) {
				return ( new DateTimeImmutable( $item['startsAt'] ) )->setTimezone( $timezone );
			}

			if ( ! empty( $item['eventDate'] ) ) {
				return new DateTimeImmutable( $item['eventDate'] . ' 00:00:00', $timezone );
			}
		} catch ( Exception $exception ) {
			unset( $exception );
		}

		return null;
	}

	/**
	 * Gets the instant on an item's last day in the organization timezone.
	 *
	 * Matches memml.com: an all-day item's end is the midnight after its last
	 * day, so that midnight belongs to the day before. An item without a
	 * usable end finishes on the day it starts.
	 *
	 * @param array        $item     Feed record.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @return DateTimeImmutable|null Null when the item has no usable date.
	 */
	private function get_item_last_datetime( $item, $timezone ) {
		$start = $this->get_item_datetime( $item, $timezone );

		if ( ! $start || empty( $item['startsAt'] ) || empty( $item['endsAt'] ) || ! is_string( $item['endsAt'] ) ) {
			return $start;
		}

		try {
			$end = ( new DateTimeImmutable( $item['endsAt'] ) )->setTimezone( $timezone );
		} catch ( Exception $exception ) {
			unset( $exception );
			return $start;
		}

		if ( ! empty( $item['allDay'] ) ) {
			$end = $end->modify( '-1 second' );
		}

		return $end > $start ? $end : $start;
	}

	/**
	 * Determines whether an item runs across more than one local day.
	 *
	 * @param array        $item     Feed record.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @return bool
	 */
	private function is_item_multi_day( $item, $timezone ) {
		$start = $this->get_item_datetime( $item, $timezone );

		return $start && $start->format( 'Y-m-d' ) !== $this->get_item_last_datetime( $item, $timezone )->format( 'Y-m-d' );
	}

	/**
	 * Renders a compact local time for month entries.
	 *
	 * On the later days of a multi-day item the label says how the item
	 * continues: All day for a middle day, or the end time on its last day.
	 *
	 * @param array                  $item     Feed record.
	 * @param DateTimeZone           $timezone Organization timezone.
	 * @param DateTimeInterface|null $day      Month-grid day the entry is shown on.
	 * @return string
	 */
	private function render_time_only( $item, $timezone, $day = null ) {
		if ( ! empty( $item['allDay'] ) || empty( $item['startsAt'] ) ) {
			return '<span class="memml-calendar__month-time">' . esc_html__( 'All day', 'memml' ) . '</span>';
		}

		$date = $this->get_item_datetime( $item, $timezone );

		if ( $date && $day && $day->format( 'Y-m-d' ) !== $date->format( 'Y-m-d' ) ) {
			$last = $this->get_item_last_datetime( $item, $timezone );

			if ( $day->format( 'Y-m-d' ) !== $last->format( 'Y-m-d' ) ) {
				return '<span class="memml-calendar__month-time">' . esc_html__( 'All day', 'memml' ) . '</span>';
			}

			return sprintf(
				'<time class="memml-calendar__month-time" datetime="%1$s">%2$s</time>',
				esc_attr( $item['endsAt'] ),
				esc_html(
					sprintf(
						/* translators: %s: Time a multi-day item ends on its last day. */
						__( 'Until %s', 'memml' ),
						wp_date( get_option( 'time_format' ), $last->getTimestamp(), $timezone )
					)
				)
			);
		}

		return $date
			? '<time class="memml-calendar__month-time" datetime="' . esc_attr( $item['startsAt'] ) . '">' . esc_html( wp_date( get_option( 'time_format' ), $date->getTimestamp(), $timezone ) ) . '</time>'
			: '';
	}

	/**
	 * Gets today at midnight in the organization timezone.
	 *
	 * @param DateTimeZone $timezone Organization timezone.
	 * @return DateTimeImmutable
	 */
	private function get_today( $timezone ) {
		$today    = new DateTimeImmutable( 'today', $timezone );
		$filtered = apply_filters( 'memml_calendar_today', $today, $timezone );

		if ( $filtered instanceof DateTimeInterface ) {
			return ( new DateTimeImmutable( '@' . $filtered->getTimestamp() ) )->setTimezone( $timezone )->setTime( 0, 0 );
		}

		return $today;
	}

	/**
	 * Determines whether an item is before today in the organization timezone.
	 *
	 * @param array        $item     Feed record.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @return bool
	 */
	private function is_item_past( $item, $timezone ) {
		$date = $this->get_item_last_datetime( $item, $timezone );

		return $date && $date->format( 'Y-m-d' ) < $this->get_today( $timezone )->format( 'Y-m-d' );
	}

	/**
	 * Determines whether an item's visitor actions are still timely.
	 *
	 * @param array        $item     Event or opportunity feed record.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @return bool
	 */
	private function is_item_actionable( $item, $timezone ) {
		$date = $this->get_item_last_datetime( $item, $timezone );

		return $date && $date->format( 'Y-m-d' ) >= $this->get_today( $timezone )->format( 'Y-m-d' );
	}

	/**
	 * Adds empty months between the first and last feed month.
	 *
	 * @param array        $months   Grouped feed months.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @return array
	 */
	private function fill_month_range( $months, $timezone ) {
		$keys    = array_keys( $months );
		$first   = new DateTimeImmutable( $keys[0] . '-01 00:00:00', $timezone );
		$last    = new DateTimeImmutable( end( $keys ) . '-01 00:00:00', $timezone );
		$span    = ( (int) $last->format( 'Y' ) - (int) $first->format( 'Y' ) ) * 12;
		$span   += (int) $last->format( 'n' ) - (int) $first->format( 'n' ) + 1;
		$filled  = array();
		$current = $first;

		if ( $span > self::MAX_CONTIGUOUS_MONTHS ) {
			return $months;
		}

		while ( $current <= $last ) {
			$key = $current->format( 'Y-m' );

			$filled[ $key ] = isset( $months[ $key ] )
				? $months[ $key ]
				: array(
					'first_day' => $current,
					'days'      => array(),
				);
			$current        = $current->modify( 'first day of next month' );
		}

		return $filled;
	}

	/**
	 * Chooses the current or next available month, falling back to the latest month.
	 *
	 * @param array        $month_keys Month keys in chronological order.
	 * @param DateTimeZone $timezone   Organization timezone.
	 * @return int
	 */
	private function get_default_month_index( $month_keys, $timezone ) {
		$current_month = $this->get_today( $timezone )->format( 'Y-m' );

		foreach ( $month_keys as $index => $month_key ) {
			if ( $month_key >= $current_month ) {
				return $index;
			}
		}

		return max( 0, count( $month_keys ) - 1 );
	}

	/**
	 * Gets a safe display layout from block or shortcode attributes.
	 *
	 * @param array|string $attributes Block or shortcode attributes.
	 * @return string
	 */
	private function get_layout_from_attributes( $attributes ) {
		$layout = is_array( $attributes ) && isset( $attributes['view'] ) ? $attributes['view'] : '';

		return $this->resolve_layout( $layout );
	}

	/**
	 * Resolves a layout, falling back to the site-wide display default.
	 *
	 * @param string $layout Layout from a block or shortcode, or '' when unset.
	 * @return string
	 */
	private function resolve_layout( $layout ) {
		if ( 'list' === $layout || 'month' === $layout ) {
			return $layout;
		}

		return $this->normalize_layout( Memml_Settings::get_options()['default_view'] );
	}

	/**
	 * Resolves a list style, falling back to the site-wide display default.
	 *
	 * @param string $style List style from a block or shortcode, or '' when unset.
	 * @return string
	 */
	private function resolve_list_style( $style ) {
		if ( 'grid' === $style || 'rows' === $style ) {
			return $style;
		}

		return $this->normalize_list_style( Memml_Settings::get_options()['default_list_style'] );
	}

	/**
	 * Resolves the subscribe-links preference, falling back to the site-wide
	 * display default.
	 *
	 * @param mixed $subscribe Preference from a block or shortcode; null or ''
	 *                         follows the site default.
	 * @return bool
	 */
	private function resolve_subscribe( $subscribe ) {
		if ( null === $subscribe || '' === $subscribe ) {
			return ! empty( Memml_Settings::get_options()['subscribe_links'] );
		}

		if ( is_string( $subscribe ) ) {
			return ! in_array( strtolower( $subscribe ), array( '0', 'false', 'no' ), true );
		}

		return (bool) $subscribe;
	}

	/**
	 * Resolves whether one visitor control is available.
	 *
	 * @param mixed  $value       Block or shortcode preference; null or '' follows the site default.
	 * @param string $option_name Site option containing the default.
	 * @return bool
	 */
	private function resolve_control_visibility( $value, $option_name ) {
		$site_default = ! empty( Memml_Settings::get_options()[ $option_name ] );

		if ( null === $value || '' === $value ) {
			return $site_default;
		}

		if ( is_string( $value ) ) {
			$value = strtolower( $value );

			if ( in_array( $value, array( '0', 'false', 'no' ), true ) ) {
				return false;
			}

			if ( in_array( $value, array( '1', 'true', 'yes' ), true ) ) {
				return true;
			}

			return $site_default;
		}

		return (bool) $value;
	}

	/**
	 * Resolves the initial calendar, falling back to the site-wide display default.
	 *
	 * @param string $calendar Calendar from a block or shortcode, or '' when unset.
	 * @return string
	 */
	private function resolve_calendar( $calendar ) {
		if ( 'events' === $calendar || 'volunteers' === $calendar ) {
			return $calendar;
		}

		return 'volunteers' === Memml_Settings::get_options()['default_calendar'] ? 'volunteers' : 'events';
	}

	/**
	 * Resolves the initial list period, falling back to the site-wide display default.
	 *
	 * @param string $period Period from a block or shortcode, or '' when unset.
	 * @return string
	 */
	private function resolve_period( $period ) {
		if ( 'upcoming' === $period || 'past' === $period ) {
			return $period;
		}

		return 'past' === Memml_Settings::get_options()['default_period'] ? 'past' : 'upcoming';
	}

	/**
	 * Resolves a list item limit, falling back to the site-wide display default.
	 *
	 * Zero is an explicit, meaningful value (show all), while an empty value or
	 * the block's -1 sentinel follows the site setting.
	 *
	 * @param mixed $limit Limit from a block or shortcode.
	 * @return int
	 */
	private function resolve_limit( $limit ) {
		if ( '' === $limit || null === $limit || ( is_numeric( $limit ) && -1 === (int) $limit ) ) {
			return max( 0, (int) Memml_Settings::get_options()['default_limit'] );
		}

		return max( 0, (int) $limit );
	}

	/**
	 * Gets a safe list period from block or shortcode attributes.
	 *
	 * @param array|string $attributes Block or shortcode attributes.
	 * @return string
	 */
	private function get_period_from_attributes( $attributes ) {
		$period = is_array( $attributes ) && isset( $attributes['period'] ) ? $attributes['period'] : '';

		return $this->resolve_period( $period );
	}

	/**
	 * Gets an optional stable share-link identifier from block or shortcode attributes.
	 *
	 * @param array|string $attributes Block or shortcode attributes.
	 * @return string
	 */
	private function get_url_key_from_attributes( $attributes ) {
		if ( ! is_array( $attributes ) ) {
			return '';
		}

		if ( isset( $attributes['urlKey'] ) ) {
			return sanitize_key( $attributes['urlKey'] );
		}

		return isset( $attributes['url_key'] ) ? sanitize_key( $attributes['url_key'] ) : '';
	}

	/**
	 * Gets a safe list style from block or shortcode attributes.
	 *
	 * @param array|string $attributes Block or shortcode attributes.
	 * @return string
	 */
	private function get_list_style_from_attributes( $attributes ) {
		if ( ! is_array( $attributes ) ) {
			return $this->resolve_list_style( '' );
		}

		if ( isset( $attributes['listStyle'] ) && '' !== $attributes['listStyle'] ) {
			return $this->resolve_list_style( $attributes['listStyle'] );
		}

		return $this->resolve_list_style( isset( $attributes['list_style'] ) ? $attributes['list_style'] : '' );
	}

	/**
	 * Normalizes a list presentation style.
	 *
	 * @param string $style Candidate style.
	 * @return string
	 */
	private function normalize_list_style( $style ) {
		return 'rows' === $style ? 'rows' : 'grid';
	}

	/**
	 * Gets the subscribe-links preference from block or shortcode attributes.
	 *
	 * @param array|string $attributes Block or shortcode attributes.
	 * @return bool
	 */
	private function get_subscribe_from_attributes( $attributes ) {
		if ( ! is_array( $attributes ) || ! isset( $attributes['subscribe'] ) ) {
			return $this->resolve_subscribe( null );
		}

		return $this->resolve_subscribe( $attributes['subscribe'] );
	}

	/**
	 * Gets an inherited visitor-control preference from block or shortcode attributes.
	 *
	 * @param array|string $attributes     Block or shortcode attributes.
	 * @param string       $block_name     Camel-case block attribute name.
	 * @param string       $shortcode_name Snake-case shortcode attribute name.
	 * @param string       $option_name    Site option containing the default.
	 * @return bool
	 */
	private function get_control_from_attributes( $attributes, $block_name, $shortcode_name, $option_name ) {
		if ( ! is_array( $attributes ) ) {
			return $this->resolve_control_visibility( null, $option_name );
		}

		if ( isset( $attributes[ $block_name ] ) && '' !== $attributes[ $block_name ] ) {
			return $this->resolve_control_visibility( $attributes[ $block_name ], $option_name );
		}

		$value = isset( $attributes[ $shortcode_name ] ) ? $attributes[ $shortcode_name ] : null;

		return $this->resolve_control_visibility( $value, $option_name );
	}

	/**
	 * Resolves every content and action visibility preference for one calendar.
	 *
	 * @param array|string $attributes Block or shortcode attributes.
	 * @return array
	 */
	private function get_visibility_from_attributes( $attributes ) {
		$visibility = array();

		foreach ( self::VISIBILITY_PREFERENCES as $option_name => $attribute_names ) {
			$visibility[ $option_name ] = $this->get_control_from_attributes(
				$attributes,
				$attribute_names[0],
				$attribute_names[1],
				$option_name
			);
		}

		return $visibility;
	}

	/**
	 * Checks a resolved visibility preference, defaulting on for legacy calls.
	 *
	 * @param array  $context Render context.
	 * @param string $name    Visibility preference name.
	 * @return bool
	 */
	private function is_visible( $context, $name ) {
		return ! is_array( $context ) || ! array_key_exists( $name, $context ) || ! empty( $context[ $name ] );
	}

	/**
	 * Gets a safe list item limit from block or shortcode attributes.
	 *
	 * @param array|string $attributes Block or shortcode attributes.
	 * @return int
	 */
	private function get_limit_from_attributes( $attributes ) {
		if ( ! is_array( $attributes ) || ! isset( $attributes['limit'] ) ) {
			return $this->resolve_limit( '' );
		}

		return $this->resolve_limit( $attributes['limit'] );
	}

	/**
	 * Reserves a unique share-link identifier for this rendered calendar.
	 *
	 * @param string $requested Requested identifier.
	 * @return string
	 */
	private function get_unique_url_key( $requested ) {
		$base = sanitize_key( $requested );

		if ( '' === $base ) {
			$base = (string) self::$calendar_instance;
		}

		$key    = $base;
		$suffix = 2;

		while ( isset( self::$used_url_keys[ $key ] ) ) {
			$key = $base . '-' . $suffix;
			++$suffix;
		}

		self::$used_url_keys[ $key ] = true;

		return $key;
	}

	/**
	 * Gets the initial calendar, allowing a direct-link query to override content settings.
	 *
	 * @param string $calendar     Calendar configured by the block or shortcode.
	 * @param string $query_prefix   Instance-scoped query prefix.
	 * @param bool   $allow_visitor Whether visitor URL state can change the calendar.
	 * @return string
	 */
	private function get_initial_calendar( $calendar, $query_prefix, $allow_visitor = true ) {
		$query_calendar = $allow_visitor
			? $this->get_query_choice( $query_prefix . 'calendar', array( 'events', 'volunteers' ) )
			: '';

		if ( '' !== $query_calendar ) {
			return $query_calendar;
		}

		return $this->resolve_calendar( $calendar );
	}

	/**
	 * Gets the initial layout, allowing a direct-link query to override content settings.
	 *
	 * @param string $layout       Layout configured by the block or shortcode.
	 * @param string $query_prefix   Instance-scoped query prefix.
	 * @param bool   $allow_visitor Whether visitor URL state can change the layout.
	 * @return string
	 */
	private function get_initial_layout( $layout, $query_prefix, $allow_visitor = true ) {
		$query_layout = $allow_visitor
			? $this->get_query_choice( $query_prefix . 'view', array( 'list', 'month' ) )
			: '';

		return '' !== $query_layout ? $query_layout : $this->resolve_layout( $layout );
	}

	/**
	 * Gets the initial list period, allowing a direct-link query to override settings.
	 *
	 * @param string $period       Period configured by the block or shortcode.
	 * @param string $query_prefix   Instance-scoped query prefix.
	 * @param bool   $allow_visitor Whether visitor URL state can change the period.
	 * @return string
	 */
	private function get_initial_period( $period, $query_prefix, $allow_visitor = true ) {
		$query_period = $allow_visitor
			? $this->get_query_choice( $query_prefix . 'period', array( 'upcoming', 'past' ) )
			: '';

		if ( '' !== $query_period ) {
			return $query_period;
		}

		return $this->resolve_period( $period );
	}

	/**
	 * Gets a valid requested month in YYYY-MM format.
	 *
	 * @param string $query_prefix Instance-scoped query prefix.
	 * @return string
	 */
	private function get_requested_month( $query_prefix ) {
		$month = $this->get_query_value( $query_prefix . 'month' );

		return preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/D', $month ) ? $month : '';
	}

	/**
	 * Gets an allow-listed public query value.
	 *
	 * @param string $parameter Query parameter name.
	 * @param array  $allowed   Allowed values.
	 * @return string
	 */
	private function get_query_choice( $parameter, $allowed ) {
		$value = $this->get_query_value( $parameter );

		return in_array( $value, $allowed, true ) ? $value : '';
	}

	/**
	 * Gets a sanitized scalar public query value.
	 *
	 * @param string $parameter Query parameter name.
	 * @return string
	 */
	private function get_query_value( $parameter ) {
		$value = isset( $_GET[ $parameter ] ) ? wp_unslash( $_GET[ $parameter ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Scalar checked and sanitized below; public display state only.

		return is_string( $value ) ? sanitize_key( $value ) : '';
	}

	/**
	 * Normalizes a display layout.
	 *
	 * @param string $layout Candidate layout.
	 * @return string
	 */
	private function normalize_layout( $layout ) {
		return 'month' === $layout ? 'month' : 'list';
	}

	/**
	 * Gets a configured feed response.
	 *
	 * @param string $feed Feed identifier.
	 * @return array|WP_Error
	 */
	private function get_client_result( $feed ) {
		$options = Memml_Settings::get_options();

		if ( '' === $options['organization_key'] ) {
			return new WP_Error(
				'memml_missing_organization_key',
				__( 'Memml Calendar has not been configured yet.', 'memml' )
			);
		}

		$cache_key = $feed . '|' . $options['organization_key'] . '|' . $options['base_url'];

		if ( ! isset( self::$feed_results[ $cache_key ] ) ) {
			$client = new Memml_Feed_Client( $options['base_url'] );

			if ( 'volunteers' === $feed ) {
				self::$feed_results[ $cache_key ] = $client->get_volunteer_opportunities( $options['organization_key'] );
			} elseif ( 'committees' === $feed ) {
				self::$feed_results[ $cache_key ] = $client->get_committees( $options['organization_key'] );
			} else {
				self::$feed_results[ $cache_key ] = $client->get_events( $options['organization_key'] );
			}
		}

		Memml_Page_Cache::limit_for_feed( $feed, self::$feed_results[ $cache_key ] );

		return self::$feed_results[ $cache_key ];
	}

	/**
	 * Renders one event card.
	 *
	 * @param array        $event    Event feed record.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @param bool         $is_past  Whether the event is in the Past list.
	 * @param array        $context  Instance render context.
	 * @return string
	 */
	private function render_event_card( $event, $timezone, $is_past = false, $context = array() ) {
		$title  = isset( $event['title'] ) ? (string) $event['title'] : '';
		$status = isset( $event['status'] ) ? (string) $event['status'] : 'scheduled';
		$status = in_array( $status, array( 'scheduled', 'cancelled', 'postponed' ), true ) ? $status : 'scheduled';

		return sprintf(
			'<article class="memml-calendar__card memml-calendar__card--%1$s" data-memml-item>%2$s<div class="memml-calendar__card-body">%3$s<h3 class="memml-calendar__title">%4$s</h3><div class="memml-calendar__meta">%5$s</div>%6$s%7$s</div>%8$s</article>',
			esc_attr( $status ),
			$this->is_visible( $context, 'show_images' ) ? $this->render_image( isset( $event['imageUrl'] ) ? $event['imageUrl'] : '', $title ) : '',
			$this->render_status_badge( $status ),
			esc_html( $title ),
			$this->render_event_meta( $event, $timezone, $is_past ? 'full' : 'compact', true, false, $context ),
			$this->is_visible( $context, 'show_descriptions' ) ? $this->render_description( isset( $event['description'] ) ? $event['description'] : '' ) : '',
			$this->render_event_actions( $event, $timezone, $context, $is_past ),
			$this->render_details( $event, 'events', $timezone, $is_past, $context )
		);
	}

	/**
	 * Renders one event as a compact full-width row.
	 *
	 * Mirrors memml.com's own list rows: date chip on the left, content in
	 * the middle, and the status badge and actions in a right-hand column.
	 *
	 * @param array        $event    Event feed record.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @param bool         $is_past  Whether the event is in the Past list.
	 * @param array        $context  Instance render context.
	 * @return string
	 */
	private function render_event_row( $event, $timezone, $is_past = false, $context = array() ) {
		$title  = isset( $event['title'] ) ? (string) $event['title'] : '';
		$status = isset( $event['status'] ) ? (string) $event['status'] : 'scheduled';
		$status = in_array( $status, array( 'scheduled', 'cancelled', 'postponed' ), true ) ? $status : 'scheduled';

		$add_links = $this->render_add_to_calendar( $event, $context, $is_past, $timezone );

		return sprintf(
			'<article class="memml-calendar__row memml-calendar__card--%1$s" data-memml-item>%2$s<div class="memml-calendar__row-body"><h3 class="memml-calendar__title">%3$s</h3><div class="memml-calendar__meta memml-calendar__meta--inline">%4$s</div>%5$s%6$s</div><div class="memml-calendar__row-aside">%7$s%8$s</div>%9$s</article>',
			esc_attr( $status ),
			$this->render_date_chip( $event, $timezone ),
			esc_html( $title ),
			$this->render_event_meta( $event, $timezone, $is_past ? 'full' : 'compact', false, false, $context ),
			$this->is_visible( $context, 'show_descriptions' ) ? $this->render_description( isset( $event['description'] ) ? $event['description'] : '' ) : '',
			'' === $add_links ? '' : '<div class="memml-calendar__row-links">' . $add_links . '</div>',
			$this->render_status_badge( $status ),
			$this->render_event_actions( $event, $timezone, $context, $is_past, false, false ),
			$this->render_details( $event, 'events', $timezone, $is_past, $context )
		);
	}

	/**
	 * Renders one volunteer opportunity as a compact full-width row.
	 *
	 * @param array        $opportunity Opportunity feed record.
	 * @param DateTimeZone $timezone    Organization timezone.
	 * @param bool         $is_past     Whether the opportunity is in the Past list.
	 * @param array        $context     Instance render context.
	 * @return string
	 */
	private function render_volunteer_row( $opportunity, $timezone, $is_past = false, $context = array() ) {
		$title      = isset( $opportunity['title'] ) ? (string) $opportunity['title'] : '';
		$needs_more = $this->is_visible( $context, 'show_volunteer_availability' ) && ! $is_past && ! empty( $opportunity['needsMore'] )
			? '<span class="memml-calendar__status memml-calendar__status--needed">' . esc_html__( 'Volunteers needed', 'memml' ) . '</span>'
			: '';

		return sprintf(
			'<article class="memml-calendar__row memml-calendar__card--volunteer" data-memml-item>%1$s<div class="memml-calendar__row-body"><h3 class="memml-calendar__title">%2$s</h3><div class="memml-calendar__meta memml-calendar__meta--inline">%3$s</div>%4$s</div><div class="memml-calendar__row-aside">%5$s%6$s</div>%7$s</article>',
			$this->render_date_chip( $opportunity, $timezone ),
			esc_html( $title ),
			$this->render_volunteer_meta( $opportunity, $timezone, $is_past ? 'full' : 'compact', false, $context ),
			$this->is_visible( $context, 'show_descriptions' ) ? $this->render_description( isset( $opportunity['description'] ) ? $opportunity['description'] : '' ) : '',
			$needs_more,
			$is_past ? '' : $this->render_volunteer_actions( $opportunity, $timezone, $context ),
			$this->render_details( $opportunity, 'volunteers', $timezone, $is_past, $context )
		);
	}

	/**
	 * Renders a status badge for a cancelled or postponed event.
	 *
	 * @param string $status Normalized event status.
	 * @return string
	 */
	private function render_status_badge( $status ) {
		if ( 'cancelled' !== $status && 'postponed' !== $status ) {
			return '';
		}

		return sprintf(
			'<span class="memml-calendar__status memml-calendar__status--%1$s">%2$s</span>',
			esc_attr( $status ),
			'cancelled' === $status ? esc_html__( 'Cancelled', 'memml' ) : esc_html__( 'Postponed', 'memml' )
		);
	}

	/**
	 * Renders the date, venue, and cost line for one event.
	 *
	 * @param array        $event     Event feed record.
	 * @param DateTimeZone $timezone  Organization timezone.
	 * @param string       $style     Datetime label style: compact or full.
	 * @param bool         $with_chip Whether the datetime includes the date chip.
	 * @param bool         $with_venue_details Whether to include optional structured venue details.
	 * @param array        $context   Instance render context.
	 * @return string
	 */
	private function render_event_meta( $event, $timezone, $style = 'full', $with_chip = true, $with_venue_details = false, $context = array() ) {
		$meta = $this->render_datetime( $event, $timezone, $style, $with_chip );

		if ( ! $this->is_visible( $context, 'show_venue_cost' ) ) {
			return $meta;
		}

		$meta .= $this->render_event_location( $event, $with_venue_details );

		if ( isset( $event['cost'] ) && null !== $event['cost'] && '' !== $event['cost'] ) {
			$meta .= '<span class="memml-calendar__cost">' . esc_html( $event['cost'] ) . '</span>';
		}

		return $meta;
	}

	/**
	 * Renders either structured venue data or the legacy location string.
	 *
	 * Structured venues are deliberately recognizable in the markup so themes
	 * can treat the richer feed variant differently. The compact form includes
	 * the venue name, address, and map link; optional operational details are
	 * reserved for the event dialog.
	 *
	 * @param array $event        Event feed record.
	 * @param bool  $with_details Whether to include optional venue details.
	 * @return string
	 */
	private function render_event_location( $event, $with_details = false ) {
		$venues = isset( $event['venues'] ) && is_array( $event['venues'] )
			? array_filter( $event['venues'], 'is_array' )
			: array();

		if ( empty( $venues ) ) {
			return empty( $event['location'] )
				? ''
				: '<span class="memml-calendar__location">' . esc_html( $event['location'] ) . '</span>';
		}

		$rendered = '';

		foreach ( $venues as $venue ) {
			$name    = isset( $venue['name'] ) ? trim( (string) $venue['name'] ) : '';
			$address = $this->format_venue_address( $venue );
			$content = '';

			if ( '' !== $name ) {
				$content .= '<span class="memml-calendar__venue-name">' . esc_html( $name ) . '</span>';
			}

			if ( '' !== $address ) {
				$content .= '<span class="memml-calendar__venue-address">' . esc_html( $address ) . '</span>';
			}

			$map_url = $this->build_google_maps_url( $venue );

			if ( '' !== $map_url ) {
				$map_label = '' !== $name
					? sprintf(
						/* translators: %s: Venue name. */
						__( 'View %s on Google Maps', 'memml' ),
						$name
					)
					: __( 'View address on Google Maps', 'memml' );

				$content .= sprintf(
					'<a class="memml-calendar__venue-link" href="%1$s" aria-label="%2$s" rel="noopener noreferrer" target="_blank">%3$s</a>',
					esc_url( $map_url ),
					esc_attr( $map_label ),
					esc_html__( 'View on Google Maps', 'memml' )
				);
			}

			if ( $with_details ) {
				$content .= $this->render_venue_details( $venue, $name );
			}

			if ( '' !== $content ) {
				$rendered .= '<span class="memml-calendar__venue memml-calendar__venue--enhanced">' . $content . '</span>';
			}
		}

		if ( '' !== $rendered ) {
			return '<span class="memml-calendar__venue-list">' . $rendered . '</span>';
		}

		return empty( $event['location'] )
			? ''
			: '<span class="memml-calendar__location">' . esc_html( $event['location'] ) . '</span>';
	}

	/**
	 * Formats the address fields supplied by a structured venue.
	 *
	 * @param array $venue Structured venue record.
	 * @return string
	 */
	private function format_venue_address( $venue ) {
		$street = array();

		foreach ( array( 'streetAddress', 'streetAddress2' ) as $field ) {
			if ( ! empty( $venue[ $field ] ) ) {
				$street[] = trim( (string) $venue[ $field ] );
			}
		}

		$locality     = isset( $venue['city'] ) ? trim( (string) $venue['city'] ) : '';
		$state_postal = trim(
			( isset( $venue['stateCode'] ) ? (string) $venue['stateCode'] : '' ) . ' ' .
			( isset( $venue['postalCode'] ) ? (string) $venue['postalCode'] : '' )
		);
		$country      = isset( $venue['countryCode'] ) ? trim( (string) $venue['countryCode'] ) : '';
		$parts        = array_filter( array_merge( $street, array( $locality, $state_postal, $country ) ) );

		return implode( ', ', $parts );
	}

	/**
	 * Builds a Google Maps search URL when a venue has a complete address.
	 *
	 * @param array $venue Structured venue record.
	 * @return string
	 */
	private function build_google_maps_url( $venue ) {
		foreach ( array( 'streetAddress', 'city', 'stateCode', 'postalCode' ) as $required_field ) {
			if ( empty( $venue[ $required_field ] ) ) {
				return '';
			}
		}

		$query = $this->format_venue_address( $venue );

		if ( ! empty( $venue['name'] ) ) {
			$query = trim( (string) $venue['name'] ) . ', ' . $query;
		}

		return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $query );
	}

	/**
	 * Renders optional details exposed by a structured venue.
	 *
	 * @param array  $venue Structured venue record.
	 * @param string $name  Venue name for accessible link labels.
	 * @return string
	 */
	private function render_venue_details( $venue, $name ) {
		$details = '';

		if ( ! empty( $venue['description'] ) ) {
			$details .= '<span class="memml-calendar__venue-description">' . esc_html( $venue['description'] ) . '</span>';
		}

		if ( ! empty( $venue['phone'] ) ) {
			$phone        = trim( (string) $venue['phone'] );
			$phone_target = preg_replace( '/[^0-9+]/', '', $phone );

			if ( '' !== $phone_target ) {
				$details .= sprintf(
					'<span class="memml-calendar__venue-detail"><strong>%1$s</strong> <a href="tel:%2$s">%3$s</a></span>',
					esc_html__( 'Phone:', 'memml' ),
					esc_attr( $phone_target ),
					esc_html( $phone )
				);
			}
		}

		if ( ! empty( $venue['websiteUrl'] ) ) {
			$website_label = '' !== $name
				? sprintf(
					/* translators: %s: Venue name. */
					__( 'Visit the %s website', 'memml' ),
					$name
				)
				: __( 'Visit the venue website', 'memml' );

			$details .= sprintf(
				'<a class="memml-calendar__venue-link" href="%1$s" aria-label="%2$s" rel="noopener noreferrer" target="_blank">%3$s</a>',
				esc_url( $venue['websiteUrl'] ),
				esc_attr( $website_label ),
				esc_html__( 'Venue website', 'memml' )
			);
		}

		foreach (
			array(
				'parkingInformation'  => __( 'Parking:', 'memml' ),
				'arrivalInstructions' => __( 'Arrival:', 'memml' ),
			) as $field => $label
		) {
			if ( ! empty( $venue[ $field ] ) ) {
				$details .= sprintf(
					'<span class="memml-calendar__venue-detail"><strong>%1$s</strong> %2$s</span>',
					esc_html( $label ),
					esc_html( $venue[ $field ] )
				);
			}
		}

		return $details;
	}

	/**
	 * Renders the date, location, and open-spots line for one opportunity.
	 *
	 * @param array        $opportunity Opportunity feed record.
	 * @param DateTimeZone $timezone    Organization timezone.
	 * @param string       $style       Datetime label style: compact or full.
	 * @param bool         $with_chip   Whether the datetime shows the date chip.
	 * @param array        $context     Instance render context.
	 * @return string
	 */
	private function render_volunteer_meta( $opportunity, $timezone, $style = 'full', $with_chip = true, $context = array() ) {
		$meta = $this->render_datetime( $opportunity, $timezone, $style, $with_chip );

		if ( $this->is_visible( $context, 'show_venue_cost' ) && ! empty( $opportunity['location'] ) ) {
			$meta .= '<span class="memml-calendar__location">' . esc_html( $opportunity['location'] ) . '</span>';
		}

		if ( $this->is_visible( $context, 'show_volunteer_availability' ) && isset( $opportunity['spotsRemaining'] ) ) {
			$spots = max( 0, (int) $opportunity['spotsRemaining'] );
			$meta .= sprintf(
				'<span class="memml-calendar__spots">%s</span>',
				esc_html(
					sprintf(
						/* translators: %d: Number of volunteer positions still available. */
						_n( '%d spot remaining', '%d spots remaining', $spots, 'memml' ),
						$spots
					)
				)
			);
		}

		return $meta;
	}

	/**
	 * Renders an item's full details for the pop-up dialog.
	 *
	 * The panel stays hidden in the page; calendar.js clones it into a modal
	 * dialog when a visitor opens the item, so the month grid and clamped list
	 * cards can stay compact without losing any information.
	 *
	 * @param array        $item     Feed record.
	 * @param string       $feed     Feed identifier.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @param bool         $is_past  Whether the item is in the past.
	 * @param array        $context  Instance render context.
	 * @return string
	 */
	private function render_details( $item, $feed, $timezone, $is_past, $context = array() ) {
		if ( ! $this->is_visible( $context, 'show_details' ) ) {
			return '';
		}

		$title = isset( $item['title'] ) ? (string) $item['title'] : '';

		if ( 'events' === $feed ) {
			$status  = isset( $item['status'] ) ? (string) $item['status'] : 'scheduled';
			$status  = in_array( $status, array( 'scheduled', 'cancelled', 'postponed' ), true ) ? $status : 'scheduled';
			$badge   = $this->render_status_badge( $status );
			$meta    = $this->render_event_meta( $item, $timezone, 'full', true, true, $context );
			$actions = $this->render_event_actions( $item, $timezone, $context, $is_past, true );
		} else {
			$badge   = $this->is_visible( $context, 'show_volunteer_availability' ) && ! $is_past && ! empty( $item['needsMore'] )
				? '<span class="memml-calendar__status memml-calendar__status--needed">' . esc_html__( 'Volunteers needed', 'memml' ) . '</span>'
				: '';
			$meta    = $this->render_volunteer_meta( $item, $timezone, 'full', true, $context );
			$actions = $is_past ? '' : $this->render_volunteer_actions( $item, $timezone, $context );
		}

		return sprintf(
			'<div class="memml-calendar__details" data-memml-details hidden>%1$s<h3 class="memml-calendar__details-title">%2$s</h3><div class="memml-calendar__meta">%3$s</div>%4$s%5$s</div>',
			$badge,
			esc_html( $title ),
			$meta,
			$this->is_visible( $context, 'show_descriptions' ) ? $this->render_description( isset( $item['description'] ) ? $item['description'] : '' ) : '',
			$actions
		);
	}

	/**
	 * Renders one volunteer opportunity card.
	 *
	 * @param array        $opportunity Opportunity feed record.
	 * @param DateTimeZone $timezone    Organization timezone.
	 * @param bool         $is_past     Whether the opportunity is in the Past list.
	 * @param array        $context     Instance render context.
	 * @return string
	 */
	private function render_volunteer_card( $opportunity, $timezone, $is_past = false, $context = array() ) {
		$title      = isset( $opportunity['title'] ) ? (string) $opportunity['title'] : '';
		$needs_more = $this->is_visible( $context, 'show_volunteer_availability' ) && ! $is_past && ! empty( $opportunity['needsMore'] )
			? '<span class="memml-calendar__status memml-calendar__status--needed">' . esc_html__( 'Volunteers needed', 'memml' ) . '</span>'
			: '';

		return sprintf(
			'<article class="memml-calendar__card memml-calendar__card--volunteer" data-memml-item>%1$s<div class="memml-calendar__card-body">%2$s<h3 class="memml-calendar__title">%3$s</h3><div class="memml-calendar__meta">%4$s</div>%5$s%6$s</div>%7$s</article>',
			$this->is_visible( $context, 'show_images' ) ? $this->render_image( isset( $opportunity['imageUrl'] ) ? $opportunity['imageUrl'] : '', $title ) : '',
			$needs_more,
			esc_html( $title ),
			$this->render_volunteer_meta( $opportunity, $timezone, $is_past ? 'full' : 'compact', true, $context ),
			$this->is_visible( $context, 'show_descriptions' ) ? $this->render_description( isset( $opportunity['description'] ) ? $opportunity['description'] : '' ) : '',
			$is_past ? '' : $this->render_volunteer_actions( $opportunity, $timezone, $context ),
			$this->render_details( $opportunity, 'volunteers', $timezone, $is_past, $context )
		);
	}

	/**
	 * Renders volunteer opportunity action links.
	 *
	 * @param array        $opportunity Opportunity feed record.
	 * @param DateTimeZone $timezone    Organization timezone.
	 * @param array        $context     Instance render context.
	 * @return string
	 */
	private function render_volunteer_actions( $opportunity, $timezone, $context = array() ) {
		if ( ! $this->is_visible( $context, 'show_volunteer_signup' ) || ! $this->is_item_actionable( $opportunity, $timezone ) || empty( $opportunity['url'] ) ) {
			return '';
		}

		return sprintf(
			'<div class="memml-calendar__actions"><a class="memml-calendar__button memml-calendar__button--primary" href="%1$s">%2$s</a></div>',
			esc_url( $opportunity['url'] ),
			esc_html__( 'Volunteer', 'memml' )
		);
	}

	/**
	 * Renders event action links.
	 *
	 * Timely actions (RSVP, registration, Join online, volunteer signup) and
	 * the add-to-calendar links are omitted once the event date has passed in
	 * the organization's timezone. Timely actions are also omitted for
	 * cancelled events, which have nothing to sign up for. The Memml event
	 * page link is kept in both cases so visitors always have a path to the
	 * full event record.
	 *
	 * @param array        $event    Event feed record.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @param array        $context      Instance render context.
	 * @param bool         $is_past      Whether the event is in the Past list.
	 * @param bool         $with_details   Whether to include dialog-only detail such as the RSVP deadline.
	 * @param bool         $with_add_links Whether to include the add-to-calendar links; rows place them in the body instead.
	 * @return string
	 */
	private function render_event_actions( $event, $timezone, $context = array(), $is_past = false, $with_details = false, $with_add_links = true ) {
		$actions       = '';
		$is_cancelled  = isset( $event['status'] ) && 'cancelled' === $event['status'];
		$is_actionable = ! $is_past && ! $is_cancelled && $this->is_item_actionable( $event, $timezone );

		if ( $this->is_visible( $context, 'show_rsvp' ) && $is_actionable ) {
			$actions .= $this->render_rsvp_action( $event, $timezone, $with_details );
		}

		if ( $this->is_visible( $context, 'show_registration' ) && $is_actionable && ! empty( $event['publicEventUrl'] ) && ! empty( $event['ctaLabel'] ) ) {
			$actions .= sprintf(
				'<a class="memml-calendar__button memml-calendar__button--primary" href="%1$s">%2$s</a>',
				esc_url( $event['publicEventUrl'] ),
				esc_html( $event['ctaLabel'] )
			);
		}

		if ( $this->is_visible( $context, 'show_online' ) && $is_actionable && ! empty( $event['meetingUrl'] ) ) {
			$actions .= sprintf(
				'<a class="memml-calendar__button" href="%1$s" rel="noopener noreferrer" target="_blank">%2$s</a>',
				esc_url( $event['meetingUrl'] ),
				esc_html__( 'Join online', 'memml' )
			);
		}

		if ( $this->is_visible( $context, 'show_volunteer_signup' ) && $is_actionable && ! empty( $event['volunteerSignupUrl'] ) ) {
			$actions .= sprintf(
				'<a class="memml-calendar__button" href="%1$s">%2$s</a>',
				esc_url( $event['volunteerSignupUrl'] ),
				esc_html__( 'Volunteer', 'memml' )
			);
		}

		if ( $with_add_links ) {
			$actions .= $this->render_add_to_calendar( $event, $context, $is_past, $timezone );
		}

		if ( $this->is_visible( $context, 'show_event_page' ) && ! empty( $event['url'] ) ) {
			$actions .= sprintf(
				'<span class="memml-calendar__event-page"><a class="memml-calendar__calendar-link memml-calendar__event-page-link" href="%1$s">%2$s</a></span>',
				esc_url( $event['url'] ),
				esc_html__( 'View event page', 'memml' )
			);
		}

		return '' === $actions ? '' : '<div class="memml-calendar__actions">' . $actions . '</div>';
	}

	/**
	 * Renders the labelled Apple / Outlook and Google add-to-calendar links.
	 *
	 * @param array             $event    Event feed record.
	 * @param array             $context  Instance render context.
	 * @param bool              $is_past  Whether the event is in the Past list.
	 * @param DateTimeZone|null $timezone Organization timezone.
	 * @return string Empty when the links are hidden, expired, or unavailable.
	 */
	private function render_add_to_calendar( $event, $context = array(), $is_past = false, $timezone = null ) {
		if ( $is_past || ! $this->is_visible( $context, 'show_add_to_calendar' ) ) {
			return '';
		}

		$add_links = '';

		if ( ! empty( $event['icsUrl'] ) ) {
			$add_links .= sprintf(
				'<a class="memml-calendar__calendar-link" href="%1$s">%2$s</a>',
				esc_url( $event['icsUrl'] ),
				esc_html__( 'Apple / Outlook', 'memml' )
			);
		}

		$google = $this->build_google_event_url( $event, $timezone );

		if ( '' !== $google ) {
			$add_links .= sprintf(
				'<a class="memml-calendar__calendar-link" href="%1$s" rel="noopener" target="_blank">%2$s</a>',
				esc_url( $google ),
				esc_html__( 'Google', 'memml' )
			);
		}

		if ( '' === $add_links ) {
			return '';
		}

		return sprintf(
			'<span class="memml-calendar__add-links"><span class="memml-calendar__add-label">%1$s</span>%2$s</span>',
			esc_html__( 'Add to calendar:', 'memml' ),
			$add_links
		);
	}

	/**
	 * Renders the RSVP action supplied by the feed's `rsvp` object.
	 *
	 * Memml includes `rsvp` only for scheduled events whose organizer
	 * advertises RSVP. While registration is open the action is a primary
	 * RSVP button with the organizer's chosen capacity wording beside it.
	 * When registration is closed or full, only a status pill is shown; the
	 * event page link remains the path for managing an existing RSVP.
	 *
	 * The details dialog also names an explicit RSVP deadline. Memml reports
	 * the event start as the cutoff when the organizer set none, so a cutoff
	 * equal to the start is not worth repeating.
	 *
	 * @param array        $event        Event feed record.
	 * @param DateTimeZone $timezone     Organization timezone.
	 * @param bool         $with_details Whether to include the RSVP deadline.
	 * @return string
	 */
	private function render_rsvp_action( $event, $timezone, $with_details = false ) {
		$rsvp = isset( $event['rsvp'] ) && is_array( $event['rsvp'] ) ? $event['rsvp'] : null;

		if ( null === $rsvp || empty( $rsvp['url'] ) ) {
			return '';
		}

		$status = isset( $event['status'] ) ? (string) $event['status'] : 'scheduled';

		if ( 'scheduled' !== $status ) {
			return '';
		}

		$capacity_label = isset( $rsvp['capacityLabel'] ) && is_string( $rsvp['capacityLabel'] ) ? trim( $rsvp['capacityLabel'] ) : '';

		if ( empty( $rsvp['canRegister'] ) ) {
			$closed_label = ! empty( $rsvp['full'] ) && '' !== $capacity_label ? $capacity_label : __( 'RSVP closed', 'memml' );

			return sprintf(
				'<span class="memml-calendar__status memml-calendar__status--rsvp-closed">%s</span>',
				esc_html( $closed_label )
			);
		}

		$notes = '' === $capacity_label ? array() : array( $capacity_label );

		if ( $with_details ) {
			$deadline = $this->format_rsvp_deadline( $event, $rsvp, $timezone );

			if ( '' !== $deadline ) {
				$notes[] = $deadline;
			}
		}

		$note = empty( $notes )
			? ''
			: sprintf( '<span class="memml-calendar__rsvp-note">%s</span>', esc_html( implode( ' · ', $notes ) ) );

		return sprintf(
			'<a class="memml-calendar__button memml-calendar__button--primary memml-calendar__button--rsvp" href="%1$s">%2$s</a>%3$s',
			esc_url( $rsvp['url'] ),
			esc_html__( 'RSVP', 'memml' ),
			$note
		);
	}

	/**
	 * Formats an explicit RSVP deadline in the organization's timezone.
	 *
	 * @param array        $event    Event feed record.
	 * @param array        $rsvp     Feed RSVP object.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @return string Empty when there is no deadline distinct from the event start.
	 */
	private function format_rsvp_deadline( $event, $rsvp, $timezone ) {
		if ( empty( $rsvp['cutoff'] ) || ! is_string( $rsvp['cutoff'] ) ) {
			return '';
		}

		$cutoff = strtotime( $rsvp['cutoff'] );
		$start  = empty( $event['startsAt'] ) ? false : strtotime( $event['startsAt'] );

		if ( false === $cutoff || $cutoff === $start ) {
			return '';
		}

		return sprintf(
			/* translators: 1: Deadline date, 2: Deadline time. */
			__( 'RSVP by %1$s at %2$s', 'memml' ),
			wp_date( get_option( 'date_format' ), $cutoff, $timezone ),
			wp_date( get_option( 'time_format' ), $cutoff, $timezone )
		);
	}

	/**
	 * Builds a Google Calendar event template URL, matching memml.com's own
	 * event pages.
	 *
	 * All-day events use organization-local dates. Like memml.com, the feed's
	 * all-day end is already the exclusive day after the last day, and an end
	 * that is missing or not after the start falls back to a one-day event.
	 *
	 * @param array             $event    Event feed record.
	 * @param DateTimeZone|null $timezone Organization timezone; UTC when omitted.
	 * @return string
	 */
	private function build_google_event_url( $event, $timezone = null ) {
		if ( empty( $event['title'] ) || empty( $event['startsAt'] ) ) {
			return '';
		}

		$start = strtotime( $event['startsAt'] );

		if ( false === $start ) {
			return '';
		}

		$end = empty( $event['endsAt'] ) ? false : strtotime( $event['endsAt'] );

		if ( empty( $event['allDay'] ) ) {
			if ( false === $end || $end < $start ) {
				$end = $start + HOUR_IN_SECONDS;
			}

			$dates = gmdate( 'Ymd\THis\Z', $start ) . '/' . gmdate( 'Ymd\THis\Z', $end );
		} else {
			$timezone   = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone( 'UTC' );
			$start_day  = ( new DateTimeImmutable( '@' . $start ) )->setTimezone( $timezone );
			$start_date = $start_day->format( 'Ymd' );
			$end_date   = false === $end ? '' : ( new DateTimeImmutable( '@' . $end ) )->setTimezone( $timezone )->format( 'Ymd' );

			if ( '' === $end_date || $end_date <= $start_date ) {
				$end_date = $start_day->modify( '+1 day' )->format( 'Ymd' );
			}

			$dates = $start_date . '/' . $end_date;
		}

		$arguments = array(
			'action' => 'TEMPLATE',
			'text'   => rawurlencode( (string) $event['title'] ),
			'dates'  => rawurlencode( $dates ),
		);

		if ( ! empty( $event['description'] ) ) {
			$details = (string) $event['description'];

			// Long descriptions would produce URLs beyond what browsers and
			// Google reliably accept, so the link carries a capped excerpt.
			if ( strlen( $details ) > 1000 ) {
				$details = ( function_exists( 'mb_substr' ) ? mb_substr( $details, 0, 1000 ) : substr( $details, 0, 1000 ) ) . '…';
			}

			$arguments['details'] = rawurlencode( $details );
		}

		if ( ! empty( $event['location'] ) ) {
			$arguments['location'] = rawurlencode( (string) $event['location'] );
		}

		return add_query_arg( $arguments, 'https://calendar.google.com/calendar/render' );
	}

	/**
	 * Renders an escaped image.
	 *
	 * The adjacent heading already names the item, so the image is marked
	 * decorative rather than repeating that name to screen readers.
	 *
	 * @param string $url   Image URL.
	 * @param string $title Card title, kept for signature compatibility.
	 * @return string
	 */
	private function render_image( $url, $title ) {
		unset( $title );

		if ( empty( $url ) ) {
			return '';
		}

		return sprintf(
			'<div class="memml-calendar__image"><img alt="" decoding="async" loading="lazy" src="%1$s" /></div>',
			esc_url( $url )
		);
	}

	/**
	 * Renders an escaped plain-text description.
	 *
	 * @param string $description Plain-text description.
	 * @return string
	 */
	private function render_description( $description ) {
		return '' === $description
			? ''
			: '<p class="memml-calendar__description">' . nl2br( esc_html( $description ) ) . '</p>';
	}

	/**
	 * Formats feed timestamps in the organization timezone.
	 *
	 * The compact style leads with the weekday and leaves the calendar date to
	 * the adjacent date chip, which is easier to scan for near-term items. The
	 * full style spells out weekday and date, for past items and the details
	 * dialog where the year matters.
	 *
	 * @param array        $item      Event or opportunity.
	 * @param DateTimeZone $timezone  Organization timezone.
	 * @param string       $style     Label style: compact or full.
	 * @param bool         $with_chip Whether to include the decorative date chip.
	 * @return string
	 */
	private function render_datetime( $item, $timezone, $style = 'full', $with_chip = true ) {
		if ( empty( $item['startsAt'] ) ) {
			return '';
		}

		$start_timestamp = strtotime( $item['startsAt'] );

		if ( false === $start_timestamp ) {
			return '';
		}

		$weekday    = wp_date( 'l', $start_timestamp, $timezone );
		$date       = wp_date( get_option( 'date_format' ), $start_timestamp, $timezone );
		$day_label  = 'compact' === $style ? $weekday : $weekday . ', ' . $date;
		$date_chip  = $with_chip ? $this->render_date_chip( $item, $timezone ) : '';
		$label_open = '<span class="memml-calendar__date-label">';

		if ( $this->is_item_multi_day( $item, $timezone ) ) {
			return '<time class="memml-calendar__date" datetime="' . esc_attr( $item['startsAt'] ) . '">' . $date_chip . $label_open . esc_html( $this->get_multi_day_label( $item, $timezone, $style ) ) . '</span></time>';
		}

		if ( ! empty( $item['allDay'] ) ) {
			$label = 'compact' === $style
				/* translators: %s: Weekday name. */
				? sprintf( __( '%s · All day', 'memml' ), $weekday )
				: $day_label;

			return '<time class="memml-calendar__date" datetime="' . esc_attr( $item['startsAt'] ) . '">' . $date_chip . $label_open . esc_html( $label ) . '</span></time>';
		}

		$time = wp_date( get_option( 'time_format' ), $start_timestamp, $timezone );

		if ( ! empty( $item['endsAt'] ) ) {
			$end_timestamp = strtotime( $item['endsAt'] );

			if ( false !== $end_timestamp ) {
				$time .= '–' . wp_date( get_option( 'time_format' ), $end_timestamp, $timezone );
			}
		}

		return '<time class="memml-calendar__date" datetime="' . esc_attr( $item['startsAt'] ) . '">' . $date_chip . $label_open . esc_html( $day_label . ' · ' . $time ) . '</span></time>';
	}

	/**
	 * Builds the date label for an item that runs across several days.
	 *
	 * All-day items name their first and last day. Timed items pair each day
	 * with its time, because a bare time range would read as one day.
	 *
	 * @param array        $item     Event or opportunity with a usable start.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @param string       $style    Label style: compact or full.
	 * @return string Unescaped label.
	 */
	private function get_multi_day_label( $item, $timezone, $style ) {
		$start = $this->get_item_datetime( $item, $timezone )->getTimestamp();
		$last  = $this->get_item_last_datetime( $item, $timezone )->getTimestamp();

		$format_day = static function ( $timestamp ) use ( $timezone, $style ) {
			if ( 'compact' === $style ) {
				/* translators: Compact PHP date format for the first and last day of a multi-day item. */
				return wp_date( __( 'D, M j', 'memml' ), $timestamp, $timezone );
			}

			return wp_date( 'l', $timestamp, $timezone ) . ', ' . wp_date( get_option( 'date_format' ), $timestamp, $timezone );
		};

		if ( ! empty( $item['allDay'] ) ) {
			$range = sprintf(
				/* translators: 1: First day, 2: Last day. */
				__( '%1$s – %2$s', 'memml' ),
				$format_day( $start ),
				$format_day( $last )
			);

			return 'compact' === $style
				/* translators: %s: Range of days. */
				? sprintf( __( '%s · All day', 'memml' ), $range )
				: $range;
		}

		$time_format = get_option( 'time_format' );

		return sprintf(
			/* translators: 1: First day, 2: Start time, 3: Last day, 4: End time. */
			__( '%1$s · %2$s – %3$s · %4$s', 'memml' ),
			$format_day( $start ),
			wp_date( $time_format, $start, $timezone ),
			$format_day( $last ),
			wp_date( $time_format, $last, $timezone )
		);
	}

	/**
	 * Renders the month-and-day date chip for one item.
	 *
	 * The chip is decorative; the accessible date lives in the adjacent time
	 * element's label.
	 *
	 * @param array        $item     Feed record.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @return string
	 */
	private function render_date_chip( $item, $timezone ) {
		if ( empty( $item['startsAt'] ) ) {
			return '';
		}

		$timestamp = strtotime( $item['startsAt'] );

		if ( false === $timestamp ) {
			return '';
		}

		return sprintf(
			'<span aria-hidden="true" class="memml-calendar__date-chip"><span>%1$s</span><strong>%2$s</strong></span>',
			esc_html( wp_date( 'M', $timestamp, $timezone ) ),
			esc_html( wp_date( 'j', $timestamp, $timezone ) )
		);
	}

	/**
	 * Gets a safe organization timezone.
	 *
	 * @param array $data Feed envelope.
	 * @return DateTimeZone
	 */
	private function get_timezone( $data ) {
		$timezone_name = isset( $data['organization']['timezone'] )
			? (string) $data['organization']['timezone']
			: 'UTC';

		try {
			return new DateTimeZone( $timezone_name );
		} catch ( Exception $exception ) {
			unset( $exception );
			return new DateTimeZone( 'UTC' );
		}
	}

	/**
	 * Renders a calm error message.
	 *
	 * @param WP_Error $error Feed error.
	 * @return string
	 */
	private function render_error( $error ) {
		$message = 'memml_organization_not_found' === $error->get_error_code()
			? __( 'The configured Memml organization could not be found.', 'memml' )
			: __( 'This calendar is temporarily unavailable. Please try again later.', 'memml' );

		return $this->render_notice( $message );
	}

	/**
	 * Renders a status notice.
	 *
	 * @param string $message Notice text.
	 * @return string
	 */
	private function render_notice( $message ) {
		return '<p class="memml-calendar__notice" role="status">' . esc_html( $message ) . '</p>';
	}

	/**
	 * Renders a live region for visitor-initiated calendar changes.
	 *
	 * @return string
	 */
	private function render_live_region() {
		return '<p aria-atomic="true" aria-live="polite" class="screen-reader-text" data-memml-status></p>';
	}

	/**
	 * Registers the shared front-end assets.
	 *
	 * The stylesheet is registered under a handle the blocks reference from
	 * block.json, so the editor can style server-rendered previews with the
	 * same rules visitors see.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			self::ASSET_HANDLE,
			MEMML_PLUGIN_URL . 'assets/calendar.css',
			array(),
			MEMML_VERSION
		);
		wp_register_script(
			self::ASSET_HANDLE,
			MEMML_PLUGIN_URL . 'assets/calendar.js',
			array(),
			MEMML_VERSION,
			true
		);
		wp_localize_script(
			self::ASSET_HANDLE,
			'memmlCalendarI18n',
			array(
				'event'         => __( 'event', 'memml' ),
				'events'        => __( 'events', 'memml' ),
				'opportunity'   => __( 'volunteer opportunity', 'memml' ),
				'opportunities' => __( 'volunteer opportunities', 'memml' ),
				'showing'       => __( 'Showing {count} {items}.', 'memml' ),
				'showingMonth'  => __( 'Showing {month}, {count} {items}.', 'memml' ),
				'close'         => __( 'Close', 'memml' ),
			)
		);
		wp_script_add_data( self::ASSET_HANDLE, 'strategy', 'defer' );

		$appearance_css = self::get_appearance_css( Memml_Settings::get_options() );

		if ( '' !== $appearance_css ) {
			wp_add_inline_style( self::ASSET_HANDLE, $appearance_css );
		}
	}

	/**
	 * Gets the root classes for appearance presets that differ from the default.
	 *
	 * Default presets add no class, so existing markup is unchanged.
	 *
	 * @param array $options Plugin settings.
	 * @return string Space-prefixed class list, or ''.
	 */
	public static function get_appearance_classes( $options ) {
		$prefixes = array(
			'appearance_palette' => 'palette',
			'appearance_spacing' => 'spacing',
			'appearance_corners' => 'corners',
			'image_size'         => 'images',
		);
		$classes  = '';

		foreach ( Memml_Settings::APPEARANCE_CHOICES as $name => $choices ) {
			$value = isset( $options[ $name ] ) ? $options[ $name ] : $choices[0];

			if ( $value !== $choices[0] && in_array( $value, $choices, true ) ) {
				$classes .= ' memml-calendar--' . $prefixes[ $name ] . '-' . $value;
			}
		}

		return $classes;
	}

	/**
	 * Gets the stylesheet rule for a custom accent color.
	 *
	 * Uses :where() like the rest of the stylesheet, so a theme's own
	 * custom-property overrides still win.
	 *
	 * @param array $options Plugin settings.
	 * @return string CSS, or '' for the default accent.
	 */
	public static function get_appearance_css( $options ) {
		$accent = isset( $options['accent_color'] ) && is_string( $options['accent_color'] ) ? strtolower( $options['accent_color'] ) : '';

		if ( ! preg_match( '/^#[0-9a-f]{6}$/', $accent ) ) {
			return '';
		}

		return sprintf(
			':where(.memml-calendar){--memml-accent:%1$s;--memml-accent-contrast:%2$s}',
			$accent,
			self::get_accent_contrast( $accent )
		);
	}

	/**
	 * Chooses white or dark text for the best contrast on an accent color.
	 *
	 * @param string $hex Six-digit hex color.
	 * @return string
	 */
	public static function get_accent_contrast( $hex ) {
		$dark      = '#1f2430';
		$luminance = static function ( $color ) {
			$channels = array_map(
				static function ( $pair ) {
					$value = hexdec( $pair ) / 255;

					return $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
				},
				str_split( substr( $color, 1 ), 2 )
			);

			return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
		};
		$accent    = $luminance( $hex );

		$white_contrast = 1.05 / ( $accent + 0.05 );
		$dark_contrast  = ( $accent + 0.05 ) / ( $luminance( $dark ) + 0.05 );

		return $white_contrast >= $dark_contrast ? '#fff' : $dark;
	}

	/**
	 * Enqueues low-specificity front-end assets only when needed.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! wp_style_is( self::ASSET_HANDLE, 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_style( self::ASSET_HANDLE );
		wp_enqueue_script( self::ASSET_HANDLE );
	}
}
