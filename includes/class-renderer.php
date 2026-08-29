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
	 * Shared handle for the front-end stylesheet and script.
	 */
	const ASSET_HANDLE = 'memml-calendar';

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
			$this->get_subscribe_from_attributes( $attributes )
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
			$this->get_subscribe_from_attributes( $attributes )
		);
	}

	/**
	 * Renders a visitor-facing calendar switcher.
	 *
	 * @param string $calendar   Initial calendar: events or volunteers.
	 * @param string $layout     Initial display layout: list or month.
	 * @param string $period     Initial list period: upcoming or past.
	 * @param string $url_key    Optional stable share-link identifier.
	 * @param int    $limit      Maximum list items per period, or 0 for every item.
	 * @param string $list_style List presentation: grid or rows.
	 * @param bool   $subscribe  Whether to offer calendar subscription links.
	 * @return string
	 */
	public function render_calendar( $calendar = 'events', $layout = 'list', $period = 'upcoming', $url_key = '', $limit = 0, $list_style = 'grid', $subscribe = true ) {
		$this->enqueue_assets();
		++self::$calendar_instance;

		$url_key      = $this->get_unique_url_key( $url_key );
		$query_prefix = 'memml_' . $url_key . '_';
		$calendar     = $this->get_initial_calendar( $calendar, $query_prefix );
		$context      = array(
			'feeds'        => array( 'events', 'volunteers' ),
			'instance_id'  => 'memml-calendar-' . self::$calendar_instance,
			'layout'       => $this->get_initial_layout( $layout, $query_prefix ),
			'limit'        => max( 0, (int) $limit ),
			'list_style'   => $this->normalize_list_style( $list_style ),
			'period'       => $this->get_initial_period( $period, $query_prefix ),
			'query_prefix' => $query_prefix,
			'subscribe'    => (bool) $subscribe,
		);

		$events_id         = $context['instance_id'] . '-events';
		$volunteers_id     = $context['instance_id'] . '-volunteers';
		$events_layouts    = $this->render_layout_panels( 'events', $context, $this->get_client_result( 'events' ) );
		$volunteer_layouts = $this->render_layout_panels( 'volunteers', $context, $this->get_client_result( 'volunteers' ) );
		$source_controls   = sprintf(
			'<div class="memml-calendar__filter" role="group" aria-label="%1$s">%2$s%3$s</div>',
			esc_attr__( 'Choose a calendar', 'memml' ),
			$this->render_control_link( $context, 'data-memml-view', 'events', __( 'Events', 'memml' ), $events_id, 'events' === $calendar, array( 'calendar' => 'events' ) ),
			$this->render_control_link( $context, 'data-memml-view', 'volunteers', __( 'Volunteer Opportunities', 'memml' ), $volunteers_id, 'volunteers' === $calendar, array( 'calendar' => 'volunteers' ) )
		);
		$toolbar           = '<div class="memml-calendar__toolbar">' . $source_controls . $this->render_period_controls( $context ) . $this->render_layout_controls( $context ) . '</div>';

		return sprintf(
			'<div class="memml-calendar memml-calendar--switchable" data-memml-calendar data-memml-url-prefix="%1$s" data-calendar="%2$s" data-layout="%3$s" data-period="%4$s">%5$s<div class="memml-calendar__panel" id="%6$s"%7$s>%8$s</div><div class="memml-calendar__panel" id="%9$s"%10$s>%11$s</div>%12$s</div>',
			esc_attr( $query_prefix ),
			esc_attr( $calendar ),
			esc_attr( $context['layout'] ),
			esc_attr( $context['period'] ),
			$toolbar,
			esc_attr( $events_id ),
			'events' === $calendar ? '' : ' hidden',
			$events_layouts,
			esc_attr( $volunteers_id ),
			'volunteers' === $calendar ? '' : ' hidden',
			$volunteer_layouts,
			$this->render_live_region()
		);
	}

	/**
	 * Renders a fixed feed with a visitor-facing layout switcher.
	 *
	 * @param string $feed       Feed identifier.
	 * @param string $layout     Initial display layout.
	 * @param string $period     Initial list period.
	 * @param string $url_key    Optional stable share-link identifier.
	 * @param int    $limit      Maximum list items per period, or 0 for every item.
	 * @param string $list_style List presentation: grid or rows.
	 * @param bool   $subscribe  Whether to offer calendar subscription links.
	 * @return string
	 */
	private function render_single_feed( $feed, $layout, $period, $url_key = '', $limit = 0, $list_style = 'grid', $subscribe = true ) {
		++self::$calendar_instance;

		$url_key      = $this->get_unique_url_key( $url_key );
		$query_prefix = 'memml_' . $url_key . '_';
		$context      = array(
			'feeds'        => array( $feed ),
			'instance_id'  => 'memml-calendar-' . self::$calendar_instance,
			'layout'       => $this->get_initial_layout( $layout, $query_prefix ),
			'limit'        => max( 0, (int) $limit ),
			'list_style'   => $this->normalize_list_style( $list_style ),
			'period'       => $this->get_initial_period( $period, $query_prefix ),
			'query_prefix' => $query_prefix,
			'subscribe'    => (bool) $subscribe,
		);

		return sprintf(
			'<div class="memml-calendar memml-calendar--%1$s" data-memml-calendar data-memml-url-prefix="%2$s" data-feed="%1$s" data-layout="%3$s" data-period="%4$s"><div class="memml-calendar__toolbar">%5$s%6$s</div>%7$s%8$s</div>',
			esc_attr( $feed ),
			esc_attr( $query_prefix ),
			esc_attr( $context['layout'] ),
			esc_attr( $context['period'] ),
			$this->render_period_controls( $context ),
			$this->render_layout_controls( $context ),
			$this->render_layout_panels( $feed, $context, $this->get_client_result( $feed ) ),
			$this->render_live_region()
		);
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
		$list_id    = $context['instance_id'] . '-' . $feed . '-list';
		$month_id   = $context['instance_id'] . '-' . $feed . '-month';
		$list_html  = $this->render_period_panels( $feed, $context, $result );
		$month_html = $this->render_feed_panel( $feed, 'month', $result, 'upcoming', $context );
		$subscribe  = empty( $context['subscribe'] ) ? '' : $this->render_subscribe_row( $feed, $result );

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

		$cards = '';
		$count = 0;

		foreach ( $events as $event ) {
			if ( is_array( $event ) ) {
				$cards .= $this->render_event_card( $event, $timezone, 'past' === $period );
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

		return $this->render_count_caption( $caption ) . '<div class="' . esc_attr( $this->get_grid_class( $context ) ) . '">' . $cards . '</div>';
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

		$cards = '';
		$count = 0;

		foreach ( $opportunities as $opportunity ) {
			if ( is_array( $opportunity ) ) {
				$cards .= $this->render_volunteer_card( $opportunity, $timezone, 'past' === $period );
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

		return $this->render_count_caption( $caption ) . '<div class="' . esc_attr( $this->get_grid_class( $context ) ) . '">' . $cards . '</div>';
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

			$is_past = $date->format( 'Y-m-d' ) < $today_date;

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

			$month_key = $date->format( 'Y-m' );
			$day       = (int) $date->format( 'j' );

			if ( ! isset( $months[ $month_key ] ) ) {
				$months[ $month_key ] = array(
					'first_day' => $date->modify( 'first day of this month' )->setTime( 0, 0 ),
					'days'      => array(),
				);
			}

			if ( ! isset( $months[ $month_key ]['days'][ $day ] ) ) {
				$months[ $month_key ]['days'][ $day ] = array();
			}

			$months[ $month_key ]['days'][ $day ][] = $item;
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
			$panels .= $this->render_month_panel( $month, $month_key, $feed, $timezone, $index, $selected_index );
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
	 * @return string
	 */
	private function render_month_panel( $month, $month_key, $feed, $timezone, $index, $selected_index ) {
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
						$entry = $this->render_month_entry( $item, $feed, $timezone );

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
	 * @param array        $item     Feed record.
	 * @param string       $feed     Feed identifier.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @return string
	 */
	private function render_month_entry( $item, $feed, $timezone ) {
		$title   = isset( $item['title'] ) ? (string) $item['title'] : '';
		$time    = $this->render_time_only( $item, $timezone );
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

			$actions = $is_past ? '' : $this->render_event_actions( $item );
		} else {
			if ( isset( $item['spotsRemaining'] ) ) {
				$spots   = max( 0, (int) $item['spotsRemaining'] );
				$details = '<span class="memml-calendar__month-spots">' . esc_html(
					sprintf(
						/* translators: %d: Number of volunteer positions still available. */
						_n( '%d spot', '%d spots', $spots, 'memml' ),
						$spots
					)
				) . '</span>';
			}

			if ( ! $is_past && ! empty( $item['url'] ) ) {
				$actions = sprintf(
					'<div class="memml-calendar__actions"><a class="memml-calendar__calendar-link" href="%1$s">%2$s</a></div>',
					esc_url( $item['url'] ),
					esc_html__( 'Volunteer', 'memml' )
				);
			}
		}

		return sprintf(
			'<article class="memml-calendar__month-entry" data-memml-item>%1$s<h4 class="memml-calendar__month-title">%2$s</h4>%3$s%4$s%5$s%6$s</article>',
			$status,
			esc_html( $title ),
			$time,
			$details,
			$actions,
			$this->render_details( $item, $feed, $timezone, $is_past )
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
	 * Renders a compact local time for month entries.
	 *
	 * @param array        $item     Feed record.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @return string
	 */
	private function render_time_only( $item, $timezone ) {
		if ( ! empty( $item['allDay'] ) || empty( $item['startsAt'] ) ) {
			return '<span class="memml-calendar__month-time">' . esc_html__( 'All day', 'memml' ) . '</span>';
		}

		$date = $this->get_item_datetime( $item, $timezone );

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
		$date = $this->get_item_datetime( $item, $timezone );

		return $date && $date->format( 'Y-m-d' ) < $this->get_today( $timezone )->format( 'Y-m-d' );
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
		$layout = is_array( $attributes ) && isset( $attributes['view'] ) ? $attributes['view'] : 'list';

		return $this->normalize_layout( $layout );
	}

	/**
	 * Gets a safe list period from block or shortcode attributes.
	 *
	 * @param array|string $attributes Block or shortcode attributes.
	 * @return string
	 */
	private function get_period_from_attributes( $attributes ) {
		$period = is_array( $attributes ) && isset( $attributes['period'] ) ? $attributes['period'] : 'upcoming';

		return 'past' === $period ? 'past' : 'upcoming';
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
			return 'grid';
		}

		if ( isset( $attributes['listStyle'] ) ) {
			return $this->normalize_list_style( $attributes['listStyle'] );
		}

		return $this->normalize_list_style( isset( $attributes['list_style'] ) ? $attributes['list_style'] : 'grid' );
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
			return true;
		}

		$value = $attributes['subscribe'];

		if ( is_string( $value ) ) {
			return ! in_array( strtolower( $value ), array( '', '0', 'false', 'no' ), true );
		}

		return (bool) $value;
	}

	/**
	 * Gets a safe list item limit from block or shortcode attributes.
	 *
	 * @param array|string $attributes Block or shortcode attributes.
	 * @return int
	 */
	private function get_limit_from_attributes( $attributes ) {
		if ( ! is_array( $attributes ) || ! isset( $attributes['limit'] ) ) {
			return 0;
		}

		return max( 0, (int) $attributes['limit'] );
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
	 * @param string $query_prefix Instance-scoped query prefix.
	 * @return string
	 */
	private function get_initial_calendar( $calendar, $query_prefix ) {
		$query_calendar = $this->get_query_choice( $query_prefix . 'calendar', array( 'events', 'volunteers' ) );

		if ( '' !== $query_calendar ) {
			return $query_calendar;
		}

		return 'volunteers' === $calendar ? 'volunteers' : 'events';
	}

	/**
	 * Gets the initial layout, allowing a direct-link query to override content settings.
	 *
	 * @param string $layout       Layout configured by the block or shortcode.
	 * @param string $query_prefix Instance-scoped query prefix.
	 * @return string
	 */
	private function get_initial_layout( $layout, $query_prefix ) {
		$query_layout = $this->get_query_choice( $query_prefix . 'view', array( 'list', 'month' ) );

		return '' !== $query_layout ? $query_layout : $this->normalize_layout( $layout );
	}

	/**
	 * Gets the initial list period, allowing a direct-link query to override settings.
	 *
	 * @param string $period       Period configured by the block or shortcode.
	 * @param string $query_prefix Instance-scoped query prefix.
	 * @return string
	 */
	private function get_initial_period( $period, $query_prefix ) {
		$query_period = $this->get_query_choice( $query_prefix . 'period', array( 'upcoming', 'past' ) );

		if ( '' !== $query_period ) {
			return $query_period;
		}

		return 'past' === $period ? 'past' : 'upcoming';
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

			self::$feed_results[ $cache_key ] = 'volunteers' === $feed
				? $client->get_volunteer_opportunities( $options['organization_key'] )
				: $client->get_events( $options['organization_key'] );
		}

		return self::$feed_results[ $cache_key ];
	}

	/**
	 * Renders one event card.
	 *
	 * @param array        $event    Event feed record.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @param bool         $is_past  Whether the event is in the Past list.
	 * @return string
	 */
	private function render_event_card( $event, $timezone, $is_past = false ) {
		$title  = isset( $event['title'] ) ? (string) $event['title'] : '';
		$status = isset( $event['status'] ) ? (string) $event['status'] : 'scheduled';
		$status = in_array( $status, array( 'scheduled', 'cancelled', 'postponed' ), true ) ? $status : 'scheduled';

		return sprintf(
			'<article class="memml-calendar__card memml-calendar__card--%1$s" data-memml-item>%2$s<div class="memml-calendar__card-body">%3$s<h3 class="memml-calendar__title">%4$s</h3><div class="memml-calendar__meta">%5$s</div>%6$s%7$s</div>%8$s</article>',
			esc_attr( $status ),
			$this->render_image( isset( $event['imageUrl'] ) ? $event['imageUrl'] : '', $title ),
			$this->render_status_badge( $status ),
			esc_html( $title ),
			$this->render_event_meta( $event, $timezone, $is_past ? 'full' : 'compact' ),
			$this->render_description( isset( $event['description'] ) ? $event['description'] : '' ),
			$is_past ? '' : $this->render_event_actions( $event ),
			$this->render_details( $event, 'events', $timezone, $is_past )
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
	 * Renders the date, location, and cost line for one event.
	 *
	 * @param array        $event    Event feed record.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @param string       $style    Datetime label style: compact or full.
	 * @return string
	 */
	private function render_event_meta( $event, $timezone, $style = 'full' ) {
		$meta = $this->render_datetime( $event, $timezone, $style );

		if ( ! empty( $event['location'] ) ) {
			$meta .= '<span class="memml-calendar__location">' . esc_html( $event['location'] ) . '</span>';
		}

		if ( isset( $event['cost'] ) && null !== $event['cost'] && '' !== $event['cost'] ) {
			$meta .= '<span class="memml-calendar__cost">' . esc_html( $event['cost'] ) . '</span>';
		}

		return $meta;
	}

	/**
	 * Renders the date, location, and open-spots line for one opportunity.
	 *
	 * @param array        $opportunity Opportunity feed record.
	 * @param DateTimeZone $timezone    Organization timezone.
	 * @param string       $style       Datetime label style: compact or full.
	 * @return string
	 */
	private function render_volunteer_meta( $opportunity, $timezone, $style = 'full' ) {
		$meta = $this->render_datetime( $opportunity, $timezone, $style );

		if ( ! empty( $opportunity['location'] ) ) {
			$meta .= '<span class="memml-calendar__location">' . esc_html( $opportunity['location'] ) . '</span>';
		}

		if ( isset( $opportunity['spotsRemaining'] ) ) {
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
	 * @return string
	 */
	private function render_details( $item, $feed, $timezone, $is_past ) {
		$title = isset( $item['title'] ) ? (string) $item['title'] : '';

		if ( 'events' === $feed ) {
			$status  = isset( $item['status'] ) ? (string) $item['status'] : 'scheduled';
			$status  = in_array( $status, array( 'scheduled', 'cancelled', 'postponed' ), true ) ? $status : 'scheduled';
			$badge   = $this->render_status_badge( $status );
			$meta    = $this->render_event_meta( $item, $timezone );
			$actions = $is_past ? '' : $this->render_event_actions( $item );
		} else {
			$badge   = ! $is_past && ! empty( $item['needsMore'] )
				? '<span class="memml-calendar__status memml-calendar__status--needed">' . esc_html__( 'Volunteers needed', 'memml' ) . '</span>'
				: '';
			$meta    = $this->render_volunteer_meta( $item, $timezone );
			$actions = $is_past ? '' : $this->render_volunteer_actions( $item );
		}

		return sprintf(
			'<div class="memml-calendar__details" data-memml-details hidden>%1$s<h3 class="memml-calendar__details-title">%2$s</h3><div class="memml-calendar__meta">%3$s</div>%4$s%5$s</div>',
			$badge,
			esc_html( $title ),
			$meta,
			$this->render_description( isset( $item['description'] ) ? $item['description'] : '' ),
			$actions
		);
	}

	/**
	 * Renders one volunteer opportunity card.
	 *
	 * @param array        $opportunity Opportunity feed record.
	 * @param DateTimeZone $timezone    Organization timezone.
	 * @param bool         $is_past     Whether the opportunity is in the Past list.
	 * @return string
	 */
	private function render_volunteer_card( $opportunity, $timezone, $is_past = false ) {
		$title      = isset( $opportunity['title'] ) ? (string) $opportunity['title'] : '';
		$needs_more = ! $is_past && ! empty( $opportunity['needsMore'] )
			? '<span class="memml-calendar__status memml-calendar__status--needed">' . esc_html__( 'Volunteers needed', 'memml' ) . '</span>'
			: '';

		return sprintf(
			'<article class="memml-calendar__card memml-calendar__card--volunteer" data-memml-item>%1$s<div class="memml-calendar__card-body">%2$s<h3 class="memml-calendar__title">%3$s</h3><div class="memml-calendar__meta">%4$s</div>%5$s%6$s</div>%7$s</article>',
			$this->render_image( isset( $opportunity['imageUrl'] ) ? $opportunity['imageUrl'] : '', $title ),
			$needs_more,
			esc_html( $title ),
			$this->render_volunteer_meta( $opportunity, $timezone, $is_past ? 'full' : 'compact' ),
			$this->render_description( isset( $opportunity['description'] ) ? $opportunity['description'] : '' ),
			$is_past ? '' : $this->render_volunteer_actions( $opportunity ),
			$this->render_details( $opportunity, 'volunteers', $timezone, $is_past )
		);
	}

	/**
	 * Renders volunteer opportunity action links.
	 *
	 * @param array $opportunity Opportunity feed record.
	 * @return string
	 */
	private function render_volunteer_actions( $opportunity ) {
		if ( empty( $opportunity['url'] ) ) {
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
	 * @param array $event Event feed record.
	 * @return string
	 */
	private function render_event_actions( $event ) {
		$actions = '';

		if ( ! empty( $event['publicEventUrl'] ) && ! empty( $event['ctaLabel'] ) ) {
			$actions .= sprintf(
				'<a class="memml-calendar__button memml-calendar__button--primary" href="%1$s">%2$s</a>',
				esc_url( $event['publicEventUrl'] ),
				esc_html( $event['ctaLabel'] )
			);
		}

		if ( ! empty( $event['volunteerSignupUrl'] ) ) {
			$actions .= sprintf(
				'<a class="memml-calendar__button" href="%1$s">%2$s</a>',
				esc_url( $event['volunteerSignupUrl'] ),
				esc_html__( 'Volunteer', 'memml' )
			);
		}

		$add_links = '';

		if ( ! empty( $event['icsUrl'] ) ) {
			$add_links .= sprintf(
				'<a class="memml-calendar__calendar-link" href="%1$s">%2$s</a>',
				esc_url( $event['icsUrl'] ),
				esc_html__( 'Apple / Outlook', 'memml' )
			);
		}

		$google = $this->build_google_event_url( $event );

		if ( '' !== $google ) {
			$add_links .= sprintf(
				'<a class="memml-calendar__calendar-link" href="%1$s" rel="noopener" target="_blank">%2$s</a>',
				esc_url( $google ),
				esc_html__( 'Google', 'memml' )
			);
		}

		if ( '' !== $add_links ) {
			$actions .= sprintf(
				'<span class="memml-calendar__add-links"><span class="memml-calendar__add-label">%1$s</span>%2$s</span>',
				esc_html__( 'Add to calendar:', 'memml' ),
				$add_links
			);
		}

		return '' === $actions ? '' : '<div class="memml-calendar__actions">' . $actions . '</div>';
	}

	/**
	 * Builds a Google Calendar event template URL, matching memml.com's own
	 * event pages.
	 *
	 * @param array $event Event feed record.
	 * @return string
	 */
	private function build_google_event_url( $event ) {
		if ( empty( $event['title'] ) || empty( $event['startsAt'] ) ) {
			return '';
		}

		$start = strtotime( $event['startsAt'] );

		if ( false === $start ) {
			return '';
		}

		$end = empty( $event['endsAt'] ) ? false : strtotime( $event['endsAt'] );

		if ( false === $end || $end < $start ) {
			$end = $start + HOUR_IN_SECONDS;
		}

		$dates = empty( $event['allDay'] )
			? gmdate( 'Ymd\THis\Z', $start ) . '/' . gmdate( 'Ymd\THis\Z', $end )
			: gmdate( 'Ymd', $start ) . '/' . gmdate( 'Ymd', $end + DAY_IN_SECONDS );

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
	 * @param array        $item     Event or opportunity.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @param string       $style    Label style: compact or full.
	 * @return string
	 */
	private function render_datetime( $item, $timezone, $style = 'full' ) {
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
		$date_chip  = sprintf(
			'<span aria-hidden="true" class="memml-calendar__date-chip"><span>%1$s</span><strong>%2$s</strong></span>',
			esc_html( wp_date( 'M', $start_timestamp, $timezone ) ),
			esc_html( wp_date( 'j', $start_timestamp, $timezone ) )
		);
		$label_open = '<span class="memml-calendar__date-label">';

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
