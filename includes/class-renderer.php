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
	 * Number of toggle instances rendered during the request.
	 *
	 * @var int
	 */
	private static $instance = 0;

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
			$this->get_period_from_attributes( $attributes )
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
			$this->get_period_from_attributes( $attributes )
		);
	}

	/**
	 * Renders a visitor-facing calendar switcher.
	 *
	 * @param string $calendar Initial calendar: events or volunteers.
	 * @param string $layout   Initial display layout: list or month.
	 * @param string $period   Initial list period: upcoming or past.
	 * @return string
	 */
	public function render_calendar( $calendar = 'events', $layout = 'list', $period = 'upcoming' ) {
		$this->enqueue_assets();
		++self::$instance;

		$calendar          = $this->get_initial_calendar( $calendar );
		$layout            = $this->get_initial_layout( $layout );
		$period            = $this->get_initial_period( $period );
		$instance_id       = 'memml-calendar-' . self::$instance;
		$events_id         = $instance_id . '-events';
		$volunteers_id     = $instance_id . '-volunteers';
		$events_result     = $this->get_client_result( 'events' );
		$volunteers_result = $this->get_client_result( 'volunteers' );
		$feeds             = array( 'events', 'volunteers' );
		$events_layouts    = $this->render_layout_panels( 'events', $layout, $period, $instance_id, $events_result );
		$volunteer_layouts = $this->render_layout_panels( 'volunteers', $layout, $period, $instance_id, $volunteers_result );
		$source_controls   = sprintf(
			'<div class="memml-calendar__filter" role="group" aria-label="%1$s"><button aria-controls="%2$s" aria-pressed="%3$s" class="memml-calendar__filter-button" data-memml-view="events" type="button">%4$s</button><button aria-controls="%5$s" aria-pressed="%6$s" class="memml-calendar__filter-button" data-memml-view="volunteers" type="button">%7$s</button></div>',
			esc_attr__( 'Choose a calendar', 'memml' ),
			esc_attr( $events_id ),
			'events' === $calendar ? 'true' : 'false',
			esc_html__( 'Events', 'memml' ),
			esc_attr( $volunteers_id ),
			'volunteers' === $calendar ? 'true' : 'false',
			esc_html__( 'Volunteer Opportunities', 'memml' )
		);
		$toolbar           = '<div class="memml-calendar__toolbar">' . $source_controls . $this->render_layout_controls( $layout, $instance_id, $feeds ) . '</div>';

		return sprintf(
			'<div class="memml-calendar memml-calendar--switchable" data-memml-calendar data-calendar="%1$s" data-layout="%2$s" data-period="%3$s">%4$s%5$s<div class="memml-calendar__panel" id="%6$s"%7$s>%8$s</div><div class="memml-calendar__panel" id="%9$s"%10$s>%11$s</div></div>',
			esc_attr( $calendar ),
			esc_attr( $layout ),
			esc_attr( $period ),
			$toolbar,
			$this->render_period_controls( $period, $layout, $instance_id, $feeds ),
			esc_attr( $events_id ),
			'events' === $calendar ? '' : ' hidden',
			$events_layouts,
			esc_attr( $volunteers_id ),
			'volunteers' === $calendar ? '' : ' hidden',
			$volunteer_layouts
		);
	}

	/**
	 * Renders a fixed feed with a visitor-facing layout switcher.
	 *
	 * @param string $feed   Feed identifier.
	 * @param string $layout Initial display layout.
	 * @param string $period Initial list period.
	 * @return string
	 */
	private function render_single_feed( $feed, $layout, $period ) {
		++self::$instance;

		$instance_id = 'memml-calendar-' . self::$instance;
		$result      = $this->get_client_result( $feed );

		$feeds = array( $feed );

		return sprintf(
			'<div class="memml-calendar memml-calendar--%1$s" data-memml-calendar data-layout="%2$s" data-period="%3$s"><div class="memml-calendar__toolbar">%4$s</div>%5$s%6$s</div>',
			esc_attr( $feed ),
			esc_attr( $layout ),
			esc_attr( $period ),
			$this->render_layout_controls( $layout, $instance_id, $feeds ),
			$this->render_period_controls( $period, $layout, $instance_id, $feeds ),
			$this->render_layout_panels( $feed, $layout, $period, $instance_id, $result )
		);
	}

	/**
	 * Renders the List and Month visitor controls.
	 *
	 * @param string $layout      Initial display layout.
	 * @param string $instance_id Calendar instance ID.
	 * @param array  $feeds       Feeds controlled by the buttons.
	 * @return string
	 */
	private function render_layout_controls( $layout, $instance_id, $feeds ) {
		$list_ids  = array();
		$month_ids = array();

		foreach ( $feeds as $feed ) {
			$list_ids[]  = $instance_id . '-' . $feed . '-list';
			$month_ids[] = $instance_id . '-' . $feed . '-month';
		}

		return sprintf(
			'<div class="memml-calendar__filter memml-calendar__layout-filter" role="group" aria-label="%1$s"><button aria-controls="%2$s" aria-pressed="%3$s" class="memml-calendar__filter-button" data-memml-layout="list" type="button">%4$s</button><button aria-controls="%5$s" aria-pressed="%6$s" class="memml-calendar__filter-button" data-memml-layout="month" type="button">%7$s</button></div>',
			esc_attr__( 'Choose a display view', 'memml' ),
			esc_attr( implode( ' ', $list_ids ) ),
			'list' === $layout ? 'true' : 'false',
			esc_html__( 'List', 'memml' ),
			esc_attr( implode( ' ', $month_ids ) ),
			'month' === $layout ? 'true' : 'false',
			esc_html__( 'Month', 'memml' )
		);
	}

	/**
	 * Renders the Upcoming and Past list controls.
	 *
	 * @param string $period      Initial list period.
	 * @param string $layout      Initial display layout.
	 * @param string $instance_id Calendar instance ID.
	 * @param array  $feeds       Feeds controlled by the buttons.
	 * @return string
	 */
	private function render_period_controls( $period, $layout, $instance_id, $feeds ) {
		$upcoming_ids = array();
		$past_ids     = array();

		foreach ( $feeds as $feed ) {
			$upcoming_ids[] = $instance_id . '-' . $feed . '-upcoming';
			$past_ids[]     = $instance_id . '-' . $feed . '-past';
		}

		return sprintf(
			'<div class="memml-calendar__filter memml-calendar__period-filter" data-memml-period-controls role="group" aria-label="%1$s"%2$s><button aria-controls="%3$s" aria-pressed="%4$s" class="memml-calendar__filter-button" data-memml-period="upcoming" type="button">%5$s</button><button aria-controls="%6$s" aria-pressed="%7$s" class="memml-calendar__filter-button" data-memml-period="past" type="button">%8$s</button></div>',
			esc_attr__( 'Filter by date', 'memml' ),
			'list' === $layout ? '' : ' hidden',
			esc_attr( implode( ' ', $upcoming_ids ) ),
			'upcoming' === $period ? 'true' : 'false',
			esc_html__( 'Upcoming', 'memml' ),
			esc_attr( implode( ' ', $past_ids ) ),
			'past' === $period ? 'true' : 'false',
			esc_html__( 'Past', 'memml' )
		);
	}

	/**
	 * Renders both display layouts for one feed.
	 *
	 * @param string         $feed        Feed identifier.
	 * @param string         $layout      Initial display layout.
	 * @param string         $period      Initial list period.
	 * @param string         $instance_id Calendar instance ID.
	 * @param array|WP_Error $result      Feed client result.
	 * @return string
	 */
	private function render_layout_panels( $feed, $layout, $period, $instance_id, $result ) {
		$list_id    = $instance_id . '-' . $feed . '-list';
		$month_id   = $instance_id . '-' . $feed . '-month';
		$list_html  = $this->render_period_panels( $feed, $period, $instance_id, $result );
		$month_html = 'events' === $feed ? $this->render_events_panel( 'month', $result ) : $this->render_volunteers_panel( 'month', $result );

		return sprintf(
			'<div data-memml-layout-panel="list" id="%1$s"%2$s>%3$s</div><div data-memml-layout-panel="month" id="%4$s"%5$s>%6$s</div>',
			esc_attr( $list_id ),
			'list' === $layout ? '' : ' hidden',
			$list_html,
			esc_attr( $month_id ),
			'month' === $layout ? '' : ' hidden',
			$month_html
		);
	}

	/**
	 * Renders both list periods for one feed.
	 *
	 * @param string         $feed        Feed identifier.
	 * @param string         $period      Initial list period.
	 * @param string         $instance_id Calendar instance ID.
	 * @param array|WP_Error $result      Feed client result.
	 * @return string
	 */
	private function render_period_panels( $feed, $period, $instance_id, $result ) {
		$upcoming_id   = $instance_id . '-' . $feed . '-upcoming';
		$past_id       = $instance_id . '-' . $feed . '-past';
		$upcoming_html = 'events' === $feed ? $this->render_events_panel( 'list', $result, 'upcoming' ) : $this->render_volunteers_panel( 'list', $result, 'upcoming' );
		$past_html     = 'events' === $feed ? $this->render_events_panel( 'list', $result, 'past' ) : $this->render_volunteers_panel( 'list', $result, 'past' );

		return sprintf(
			'<div data-memml-period-panel="upcoming" id="%1$s"%2$s>%3$s</div><div data-memml-period-panel="past" id="%4$s"%5$s>%6$s</div>',
			esc_attr( $upcoming_id ),
			'upcoming' === $period ? '' : ' hidden',
			$upcoming_html,
			esc_attr( $past_id ),
			'past' === $period ? '' : ' hidden',
			$past_html
		);
	}

	/**
	 * Renders the events feed content.
	 *
	 * @param string         $layout Display layout.
	 * @param array|WP_Error $result Feed client result.
	 * @param string         $period List period.
	 * @return string
	 */
	private function render_events_panel( $layout, $result, $period = 'upcoming' ) {
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
			return $this->render_month_calendar( $events, 'events', $timezone );
		}

		$events = $this->filter_list_items( $events, $period, $timezone );

		if ( empty( $events ) ) {
			return $this->render_notice(
				'past' === $period
					? __( 'No past events are currently available.', 'memml' )
					: __( 'No upcoming events are currently available.', 'memml' )
			);
		}

		$cards = '';

		foreach ( $events as $event ) {
			if ( is_array( $event ) ) {
				$cards .= $this->render_event_card( $event, $timezone );
			}
		}

		return '<div class="memml-calendar__grid">' . $cards . '</div>';
	}

	/**
	 * Renders the volunteer feed content.
	 *
	 * @param string         $layout Display layout.
	 * @param array|WP_Error $result Feed client result.
	 * @param string         $period List period.
	 * @return string
	 */
	private function render_volunteers_panel( $layout, $result, $period = 'upcoming' ) {
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
			return $this->render_month_calendar( $opportunities, 'volunteers', $timezone );
		}

		$opportunities = $this->filter_list_items( $opportunities, $period, $timezone );

		if ( empty( $opportunities ) ) {
			return $this->render_notice(
				'past' === $period
					? __( 'No past volunteer opportunities are currently available.', 'memml' )
					: __( 'No upcoming volunteer opportunities are currently available.', 'memml' )
			);
		}

		$cards = '';

		foreach ( $opportunities as $opportunity ) {
			if ( is_array( $opportunity ) ) {
				$cards .= $this->render_volunteer_card( $opportunity, $timezone );
			}
		}

		return '<div class="memml-calendar__grid">' . $cards . '</div>';
	}

	/**
	 * Filters and sorts list items relative to today in the organization timezone.
	 *
	 * @param array        $items    Feed records.
	 * @param string       $period   Upcoming or past.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @return array
	 */
	private function filter_list_items( $items, $period, $timezone ) {
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

		return array_column( $filtered, 'item' );
	}

	/**
	 * Renders feed items in one or more organization-timezone month grids.
	 *
	 * @param array        $items    Event or opportunity records.
	 * @param string       $feed     Feed identifier.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @return string
	 */
	private function render_month_calendar( $items, $feed, $timezone ) {
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
		++self::$instance;

		$calendar_id     = 'memml-month-calendar-' . self::$instance;
		$month_keys      = array_keys( $months );
		$month_count     = count( $months );
		$requested_month = $this->get_requested_month();
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
				'<div class="memml-calendar__month-header"><button aria-controls="%1$s" aria-label="%2$s" class="memml-calendar__month-button" data-memml-month-prev%3$s type="button">&lsaquo;</button><h3 aria-live="polite" class="memml-calendar__month-label" data-memml-month-label>%4$s</h3><button aria-controls="%1$s" aria-label="%5$s" class="memml-calendar__month-button" data-memml-month-next%6$s type="button">&rsaquo;</button></div>',
				esc_attr( $calendar_id ),
				esc_attr__( 'Previous month', 'memml' ),
				0 === $selected_index ? ' disabled' : '',
				esc_html( $first_label ),
				esc_attr__( 'Next month', 'memml' ),
				$selected_index === $month_count - 1 ? ' disabled' : ''
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
				$entries    = '';

				if ( ! empty( $month['days'][ $day ] ) ) {
					foreach ( $month['days'][ $day ] as $item ) {
						$entries .= $this->render_month_entry( $item, $feed, $timezone );
					}
				}

				$rows .= sprintf(
					'<td aria-label="%1$s" class="memml-calendar__month-day%2$s"><span class="memml-calendar__day-number">%3$d</span>%4$s</td>',
					esc_attr( $date_label ),
					'' === $entries ? '' : ' has-items',
					$day,
					$entries
				);
			}

			if ( 6 === $cell % 7 ) {
				$rows .= '</tr>';
			}
		}

		return sprintf(
			'<section class="memml-calendar__month-panel" data-memml-month-index="%1$d" data-month="%2$s" data-month-label="%3$s"%4$s><div class="memml-calendar__month-scroll"><table class="memml-calendar__month-table"><caption class="screen-reader-text">%3$s</caption><thead><tr>%5$s</tr></thead><tbody>%6$s</tbody></table></div></section>',
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

			$actions = $this->render_event_actions( $item );
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

			if ( ! empty( $item['url'] ) ) {
				$actions = sprintf(
					'<div class="memml-calendar__actions"><a class="memml-calendar__calendar-link" href="%1$s">%2$s</a></div>',
					esc_url( $item['url'] ),
					esc_html__( 'Volunteer', 'memml' )
				);
			}
		}

		return sprintf(
			'<article class="memml-calendar__month-entry">%1$s<h4 class="memml-calendar__month-title">%2$s</h4>%3$s%4$s%5$s</article>',
			$status,
			esc_html( $title ),
			$time,
			$details,
			$actions
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

		return $this->get_initial_layout( $layout );
	}

	/**
	 * Gets a safe list period from block or shortcode attributes.
	 *
	 * @param array|string $attributes Block or shortcode attributes.
	 * @return string
	 */
	private function get_period_from_attributes( $attributes ) {
		$period = is_array( $attributes ) && isset( $attributes['period'] ) ? $attributes['period'] : 'upcoming';

		return $this->get_initial_period( $period );
	}

	/**
	 * Gets the initial calendar, allowing a direct-link query to override content settings.
	 *
	 * @param string $calendar Calendar configured by the block or shortcode.
	 * @return string
	 */
	private function get_initial_calendar( $calendar ) {
		$query_calendar = $this->get_query_choice( 'memml_calendar', array( 'events', 'volunteers' ) );

		if ( '' !== $query_calendar ) {
			return $query_calendar;
		}

		return 'volunteers' === $calendar ? 'volunteers' : 'events';
	}

	/**
	 * Gets the initial layout, allowing a direct-link query to override content settings.
	 *
	 * @param string $layout Layout configured by the block or shortcode.
	 * @return string
	 */
	private function get_initial_layout( $layout ) {
		$query_layout = $this->get_query_choice( 'memml_view', array( 'list', 'month' ) );

		return '' !== $query_layout ? $query_layout : $this->normalize_layout( $layout );
	}

	/**
	 * Gets the initial list period, allowing a direct-link query to override settings.
	 *
	 * @param string $period Period configured by the block or shortcode.
	 * @return string
	 */
	private function get_initial_period( $period ) {
		$query_period = $this->get_query_choice( 'memml_period', array( 'upcoming', 'past' ) );

		if ( '' !== $query_period ) {
			return $query_period;
		}

		return 'past' === $period ? 'past' : 'upcoming';
	}

	/**
	 * Gets a valid requested month in YYYY-MM format.
	 *
	 * @return string
	 */
	private function get_requested_month() {
		$month = $this->get_query_value( 'memml_month' );

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

		$client = new Memml_Feed_Client( $options['base_url'] );

		return 'volunteers' === $feed
			? $client->get_volunteer_opportunities( $options['organization_key'] )
			: $client->get_events( $options['organization_key'] );
	}

	/**
	 * Renders one event card.
	 *
	 * @param array        $event    Event feed record.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @return string
	 */
	private function render_event_card( $event, $timezone ) {
		$title       = isset( $event['title'] ) ? (string) $event['title'] : '';
		$status      = isset( $event['status'] ) ? (string) $event['status'] : 'scheduled';
		$status      = in_array( $status, array( 'scheduled', 'cancelled', 'postponed' ), true ) ? $status : 'scheduled';
		$status_html = '';

		if ( 'cancelled' === $status || 'postponed' === $status ) {
			$status_html = sprintf(
				'<span class="memml-calendar__status memml-calendar__status--%1$s">%2$s</span>',
				esc_attr( $status ),
				'cancelled' === $status ? esc_html__( 'Cancelled', 'memml' ) : esc_html__( 'Postponed', 'memml' )
			);
		}

		$meta = $this->render_datetime( $event, $timezone );

		if ( ! empty( $event['location'] ) ) {
			$meta .= '<span class="memml-calendar__location">' . esc_html( $event['location'] ) . '</span>';
		}

		if ( isset( $event['cost'] ) && null !== $event['cost'] && '' !== $event['cost'] ) {
			$meta .= '<span class="memml-calendar__cost">' . esc_html( $event['cost'] ) . '</span>';
		}

		return sprintf(
			'<article class="memml-calendar__card memml-calendar__card--%1$s">%2$s<div class="memml-calendar__card-body">%3$s<h3 class="memml-calendar__title">%4$s</h3><div class="memml-calendar__meta">%5$s</div>%6$s%7$s</div></article>',
			esc_attr( $status ),
			$this->render_image( isset( $event['imageUrl'] ) ? $event['imageUrl'] : '', $title ),
			$status_html,
			esc_html( $title ),
			$meta,
			$this->render_description( isset( $event['description'] ) ? $event['description'] : '' ),
			$this->render_event_actions( $event )
		);
	}

	/**
	 * Renders one volunteer opportunity card.
	 *
	 * @param array        $opportunity Opportunity feed record.
	 * @param DateTimeZone $timezone    Organization timezone.
	 * @return string
	 */
	private function render_volunteer_card( $opportunity, $timezone ) {
		$title = isset( $opportunity['title'] ) ? (string) $opportunity['title'] : '';
		$meta  = $this->render_datetime( $opportunity, $timezone );

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

		$needs_more = ! empty( $opportunity['needsMore'] )
			? '<span class="memml-calendar__status memml-calendar__status--needed">' . esc_html__( 'Volunteers needed', 'memml' ) . '</span>'
			: '';
		$actions    = '';

		if ( ! empty( $opportunity['url'] ) ) {
			$actions = sprintf(
				'<div class="memml-calendar__actions"><a class="memml-calendar__button memml-calendar__button--primary" href="%1$s">%2$s</a></div>',
				esc_url( $opportunity['url'] ),
				esc_html__( 'Volunteer', 'memml' )
			);
		}

		return sprintf(
			'<article class="memml-calendar__card memml-calendar__card--volunteer">%1$s<div class="memml-calendar__card-body">%2$s<h3 class="memml-calendar__title">%3$s</h3><div class="memml-calendar__meta">%4$s</div>%5$s%6$s</div></article>',
			$this->render_image( isset( $opportunity['imageUrl'] ) ? $opportunity['imageUrl'] : '', $title ),
			$needs_more,
			esc_html( $title ),
			$meta,
			$this->render_description( isset( $opportunity['description'] ) ? $opportunity['description'] : '' ),
			$actions
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

		if ( ! empty( $event['icsUrl'] ) ) {
			$actions .= sprintf(
				'<a class="memml-calendar__calendar-link" href="%1$s">%2$s</a>',
				esc_url( $event['icsUrl'] ),
				esc_html__( 'Add to calendar', 'memml' )
			);
		}

		return '' === $actions ? '' : '<div class="memml-calendar__actions">' . $actions . '</div>';
	}

	/**
	 * Renders an escaped image.
	 *
	 * @param string $url   Image URL.
	 * @param string $title Card title for alt text.
	 * @return string
	 */
	private function render_image( $url, $title ) {
		if ( empty( $url ) ) {
			return '';
		}

		return sprintf(
			'<div class="memml-calendar__image"><img alt="%1$s" loading="lazy" src="%2$s" /></div>',
			esc_attr( $title ),
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
	 * @param array        $item     Event or opportunity.
	 * @param DateTimeZone $timezone Organization timezone.
	 * @return string
	 */
	private function render_datetime( $item, $timezone ) {
		if ( empty( $item['startsAt'] ) ) {
			return '';
		}

		$start_timestamp = strtotime( $item['startsAt'] );

		if ( false === $start_timestamp ) {
			return '';
		}

		$date       = wp_date( get_option( 'date_format' ), $start_timestamp, $timezone );
		$date_chip  = sprintf(
			'<span aria-hidden="true" class="memml-calendar__date-chip"><span>%1$s</span><strong>%2$s</strong></span>',
			esc_html( wp_date( 'M', $start_timestamp, $timezone ) ),
			esc_html( wp_date( 'j', $start_timestamp, $timezone ) )
		);
		$label_open = '<span class="memml-calendar__date-label">';

		if ( ! empty( $item['allDay'] ) ) {
			return '<time class="memml-calendar__date" datetime="' . esc_attr( $item['startsAt'] ) . '">' . $date_chip . $label_open . esc_html( $date ) . '</span></time>';
		}

		$time = wp_date( get_option( 'time_format' ), $start_timestamp, $timezone );

		if ( ! empty( $item['endsAt'] ) ) {
			$end_timestamp = strtotime( $item['endsAt'] );

			if ( false !== $end_timestamp ) {
				$time .= '–' . wp_date( get_option( 'time_format' ), $end_timestamp, $timezone );
			}
		}

		return '<time class="memml-calendar__date" datetime="' . esc_attr( $item['startsAt'] ) . '">' . $date_chip . $label_open . esc_html( $date . ' · ' . $time ) . '</span></time>';
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
	 * Enqueues low-specificity front-end assets only when needed.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'memml-calendar',
			MEMML_PLUGIN_URL . 'assets/calendar.css',
			array(),
			MEMML_VERSION
		);
		wp_enqueue_script(
			'memml-calendar',
			MEMML_PLUGIN_URL . 'assets/calendar.js',
			array(),
			MEMML_VERSION,
			true
		);
		wp_script_add_data( 'memml-calendar', 'strategy', 'defer' );
	}
}
