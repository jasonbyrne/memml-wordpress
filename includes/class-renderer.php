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
	 * @return string
	 */
	public function render_events() {
		$this->enqueue_assets();

		return sprintf(
			'<div class="memml-calendar memml-calendar--events">%s</div>',
			$this->render_events_panel()
		);
	}

	/**
	 * Renders the volunteer opportunities calendar.
	 *
	 * @return string
	 */
	public function render_volunteers() {
		$this->enqueue_assets();

		return sprintf(
			'<div class="memml-calendar memml-calendar--volunteers">%s</div>',
			$this->render_volunteers_panel()
		);
	}

	/**
	 * Renders a visitor-facing calendar switcher.
	 *
	 * @param string $default_view Initial view: events or volunteers.
	 * @return string
	 */
	public function render_calendar( $default_view = 'events' ) {
		$this->enqueue_assets();
		++self::$instance;

		$default_view  = 'volunteers' === $default_view ? 'volunteers' : 'events';
		$instance_id   = 'memml-calendar-' . self::$instance;
		$events_id     = $instance_id . '-events';
		$volunteers_id = $instance_id . '-volunteers';

		return sprintf(
			'<div class="memml-calendar memml-calendar--switchable" data-memml-calendar data-default-view="%1$s">' .
			'<div class="memml-calendar__filter" role="group" aria-label="%2$s">' .
			'<button aria-controls="%3$s" aria-pressed="%4$s" class="memml-calendar__filter-button" data-memml-view="events" type="button">%5$s</button>' .
			'<button aria-controls="%6$s" aria-pressed="%7$s" class="memml-calendar__filter-button" data-memml-view="volunteers" type="button">%8$s</button>' .
			'</div>' .
			'<div class="memml-calendar__panel" id="%3$s"%9$s>%10$s</div>' .
			'<div class="memml-calendar__panel" id="%6$s"%11$s>%12$s</div>' .
			'</div>',
			esc_attr( $default_view ),
			esc_attr__( 'Choose a calendar', 'memml' ),
			esc_attr( $events_id ),
			'events' === $default_view ? 'true' : 'false',
			esc_html__( 'Events', 'memml' ),
			esc_attr( $volunteers_id ),
			'volunteers' === $default_view ? 'true' : 'false',
			esc_html__( 'Volunteer Opportunities', 'memml' ),
			'events' === $default_view ? '' : ' hidden',
			$this->render_events_panel(),
			'volunteers' === $default_view ? '' : ' hidden',
			$this->render_volunteers_panel()
		);
	}

	/**
	 * Renders the events feed content.
	 *
	 * @return string
	 */
	private function render_events_panel() {
		$result = $this->get_client_result( 'events' );

		if ( is_wp_error( $result ) ) {
			return $this->render_error( $result );
		}

		$events = isset( $result['data']['events'] ) && is_array( $result['data']['events'] )
			? $result['data']['events']
			: array();

		if ( empty( $events ) ) {
			return $this->render_notice( __( 'No upcoming events are currently available.', 'memml' ) );
		}

		$timezone = $this->get_timezone( $result['data'] );
		$cards    = '';

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
	 * @return string
	 */
	private function render_volunteers_panel() {
		$result = $this->get_client_result( 'volunteers' );

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
		$cards    = '';

		foreach ( $opportunities as $opportunity ) {
			if ( is_array( $opportunity ) ) {
				$cards .= $this->render_volunteer_card( $opportunity, $timezone );
			}
		}

		return '<div class="memml-calendar__grid">' . $cards . '</div>';
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
