( function () {
	'use strict';

	const rootControllers = [];
	const monthControllers = [];
	const messages = window.memmlCalendarI18n || {
		event: 'event',
		events: 'events',
		opportunity: 'volunteer opportunity',
		opportunities: 'volunteer opportunities',
		showing: 'Showing {count} {items}.',
		showingMonth: 'Showing {month}, {count} {items}.',
	};

	const formatMessage = function ( template, values ) {
		return Object.keys( values ).reduce( function ( message, key ) {
			return message.replace( '{' + key + '}', values[ key ] );
		}, template );
	};

	const updateUrl = function ( prefix, changes ) {
		const url = new URL( window.location.href );

		Object.keys( changes ).forEach( function ( name ) {
			const parameter = prefix + name;
			const value = changes[ name ];

			if ( value ) {
				url.searchParams.set( parameter, value );
			} else {
				url.searchParams.delete( parameter );
			}
		} );

		const nextUrl = url.pathname + url.search + url.hash;
		const currentUrl =
			window.location.pathname +
			window.location.search +
			window.location.hash;

		if ( nextUrl !== currentUrl ) {
			window.history.pushState( {}, '', nextUrl );
		}

		applyUrlState( prefix );
	};

	document
		.querySelectorAll( '[data-memml-calendar]' )
		.forEach( function ( calendar ) {
			const prefix = calendar.dataset.memmlUrlPrefix;
			const sourceButtons =
				calendar.querySelectorAll( '[data-memml-view]' );
			const layoutButtons = calendar.querySelectorAll(
				'[data-memml-layout]'
			);
			const layoutPanels = calendar.querySelectorAll(
				'[data-memml-layout-panel]'
			);
			const periodButtons = calendar.querySelectorAll(
				'[data-memml-period]'
			);
			const periodPanels = calendar.querySelectorAll(
				'[data-memml-period-panel]'
			);
			const periodControls = calendar.querySelector(
				'[data-memml-period-controls]'
			);
			const status = calendar.querySelector( '[data-memml-status]' );
			const initialCalendar = calendar.dataset.calendar || 'events';
			const initialLayout = calendar.dataset.layout || 'list';
			const initialPeriod = calendar.dataset.period || 'upcoming';

			const showSource = function ( source ) {
				const hasSource = Array.from( sourceButtons ).some(
					( button ) => button.dataset.memmlView === source
				);

				if ( ! hasSource ) {
					return;
				}

				calendar.dataset.calendar = source;
				sourceButtons.forEach( function ( candidate ) {
					const isActive = candidate.dataset.memmlView === source;
					const panel = document.getElementById(
						candidate.getAttribute( 'aria-controls' )
					);

					candidate.setAttribute(
						'aria-pressed',
						isActive ? 'true' : 'false'
					);
					if ( panel ) {
						panel.hidden = ! isActive;
					}
				} );
			};

			const showLayout = function ( layout ) {
				if ( 'list' !== layout && 'month' !== layout ) {
					return;
				}

				calendar.dataset.layout = layout;
				layoutButtons.forEach( function ( candidate ) {
					candidate.setAttribute(
						'aria-pressed',
						candidate.dataset.memmlLayout === layout
							? 'true'
							: 'false'
					);
				} );
				layoutPanels.forEach( function ( panel ) {
					panel.hidden = panel.dataset.memmlLayoutPanel !== layout;
				} );

				if ( periodControls ) {
					periodControls.hidden = 'list' !== layout;
				}
			};

			const showPeriod = function ( period ) {
				if ( 'upcoming' !== period && 'past' !== period ) {
					return;
				}

				calendar.dataset.period = period;
				periodButtons.forEach( function ( candidate ) {
					candidate.setAttribute(
						'aria-pressed',
						candidate.dataset.memmlPeriod === period
							? 'true'
							: 'false'
					);
				} );
				periodPanels.forEach( function ( panel ) {
					panel.hidden = panel.dataset.memmlPeriodPanel !== period;
				} );
			};

			const getSourceScope = function () {
				const activeSource = calendar.querySelector(
					'[data-memml-view][aria-pressed="true"]'
				);

				if ( activeSource ) {
					const sourcePanel = document.getElementById(
						activeSource.getAttribute( 'aria-controls' )
					);

					if ( sourcePanel ) {
						return sourcePanel;
					}
				}

				return calendar;
			};

			const getActiveMonth = function () {
				const panel = getSourceScope().querySelector(
					'[data-memml-layout-panel="month"] [data-memml-month-index]:not([hidden])'
				);

				return panel ? panel.dataset.month : '';
			};

			const announce = function () {
				if ( ! status ) {
					return;
				}

				const scope = getSourceScope();
				let content = scope.querySelector(
					'[data-memml-layout-panel="' +
						calendar.dataset.layout +
						'"]:not([hidden])'
				);

				if ( ! content ) {
					content = scope;
				}

				if ( 'list' === calendar.dataset.layout ) {
					const periodPanel = content.querySelector(
						'[data-memml-period-panel="' +
							calendar.dataset.period +
							'"]:not([hidden])'
					);

					if ( periodPanel ) {
						content = periodPanel;
					}
				} else {
					const monthPanel = content.querySelector(
						'[data-memml-month-index]:not([hidden])'
					);

					if ( monthPanel ) {
						content = monthPanel;
					}
				}

				const count =
					content.querySelectorAll( '[data-memml-item]' ).length;
				const feed = calendar.dataset.calendar || calendar.dataset.feed;
				let items = 1 === count ? messages.event : messages.events;

				if ( 'volunteers' === feed ) {
					items =
						1 === count
							? messages.opportunity
							: messages.opportunities;
				}
				const monthPanel = content.matches( '[data-memml-month-index]' )
					? content
					: null;

				status.textContent = formatMessage(
					monthPanel ? messages.showingMonth : messages.showing,
					{
						count,
						items,
						month: monthPanel ? monthPanel.dataset.monthLabel : '',
					}
				);
			};

			sourceButtons.forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					const source = button.dataset.memmlView;
					const changes = { calendar: source };

					showSource( source );
					if ( 'month' === calendar.dataset.layout ) {
						changes.month = getActiveMonth();
					}
					updateUrl( prefix, changes );
				} );
			} );

			layoutButtons.forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					const layout = button.dataset.memmlLayout;
					const changes = { view: layout };

					showLayout( layout );
					if ( 'month' === layout ) {
						changes.month = getActiveMonth();
					}
					updateUrl( prefix, changes );
				} );
			} );

			periodButtons.forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					const period = button.dataset.memmlPeriod;

					showPeriod( period );
					updateUrl( prefix, { period } );
				} );
			} );

			rootControllers.push( {
				announce,
				initialCalendar,
				initialLayout,
				initialPeriod,
				prefix,
				showLayout,
				showPeriod,
				showSource,
			} );
		} );

	document
		.querySelectorAll( '[data-memml-month-calendar]' )
		.forEach( function ( calendar ) {
			const root = calendar.closest( '[data-memml-calendar]' );
			const prefix = root ? root.dataset.memmlUrlPrefix : '';
			const panels = Array.from(
				calendar.querySelectorAll( '[data-memml-month-index]' )
			);
			const label = calendar.querySelector( '[data-memml-month-label]' );
			const previous = calendar.querySelector(
				'[data-memml-month-prev]'
			);
			const next = calendar.querySelector( '[data-memml-month-next]' );
			let current = panels.findIndex( function ( panel ) {
				return ! panel.hidden;
			} );

			if ( current < 0 ) {
				current = 0;
			}

			const initialMonth = panels[ current ]
				? panels[ current ].dataset.month
				: '';

			const showMonth = function ( index, updateHistory ) {
				current = Math.max( 0, Math.min( panels.length - 1, index ) );

				panels.forEach( function ( panel, panelIndex ) {
					panel.hidden = panelIndex !== current;
				} );

				if ( label && panels[ current ] ) {
					label.textContent = panels[ current ].dataset.monthLabel;
				}

				if ( previous ) {
					previous.disabled = 0 === current;
				}

				if ( next ) {
					next.disabled = current === panels.length - 1;
				}

				if ( updateHistory && panels[ current ] ) {
					updateUrl( prefix, {
						month: panels[ current ].dataset.month,
					} );
				}
			};

			const showMonthKey = function ( month ) {
				let index = panels.findIndex( function ( panel ) {
					return panel.dataset.month === month;
				} );

				if ( index < 0 ) {
					index = panels.findIndex( function ( panel ) {
						return panel.dataset.month === initialMonth;
					} );
				}

				showMonth( index < 0 ? 0 : index, false );
			};

			if ( previous ) {
				previous.addEventListener( 'click', function () {
					showMonth( current - 1, true );
				} );
			}

			if ( next ) {
				next.addEventListener( 'click', function () {
					showMonth( current + 1, true );
				} );
			}

			showMonth( current, false );
			monthControllers.push( { initialMonth, prefix, showMonthKey } );
		} );

	const applyUrlState = function ( announcePrefix ) {
		const parameters = new URL( window.location.href ).searchParams;

		rootControllers.forEach( function ( controller ) {
			const calendar = parameters.get( controller.prefix + 'calendar' );
			const layout = parameters.get( controller.prefix + 'view' );
			const period = parameters.get( controller.prefix + 'period' );

			controller.showSource(
				'events' === calendar || 'volunteers' === calendar
					? calendar
					: controller.initialCalendar
			);
			controller.showLayout(
				'list' === layout || 'month' === layout
					? layout
					: controller.initialLayout
			);
			controller.showPeriod(
				'upcoming' === period || 'past' === period
					? period
					: controller.initialPeriod
			);
		} );

		monthControllers.forEach( function ( controller ) {
			const month = parameters.get( controller.prefix + 'month' );

			controller.showMonthKey(
				/^\d{4}-(0[1-9]|1[0-2])$/.test( month || '' )
					? month
					: controller.initialMonth
			);
		} );

		if ( announcePrefix ) {
			rootControllers.forEach( function ( controller ) {
				if (
					'*' === announcePrefix ||
					controller.prefix === announcePrefix
				) {
					controller.announce();
				}
			} );
		}
	};

	window.addEventListener( 'popstate', function () {
		applyUrlState( '*' );
	} );
	applyUrlState( '' );

	document
		.querySelectorAll( '.memml-calendar__image img' )
		.forEach( function ( image ) {
			const hideBrokenImage = function () {
				const container = image.closest( '.memml-calendar__image' );

				if ( container ) {
					container.hidden = true;
				}
			};

			if ( image.complete && 0 === image.naturalWidth ) {
				hideBrokenImage();
			} else {
				image.addEventListener( 'error', hideBrokenImage, {
					once: true,
				} );
			}
		} );
} )();
