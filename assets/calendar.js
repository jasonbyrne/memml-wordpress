( function () {
	'use strict';

	const rootControllers = [];
	const monthControllers = [];

	const updateUrl = function ( changes ) {
		const url = new URL( window.location.href );

		Object.keys( changes ).forEach( function ( parameter ) {
			const value = changes[ parameter ];

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

		applyUrlState();
	};

	document
		.querySelectorAll( '[data-memml-calendar]' )
		.forEach( function ( calendar ) {
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

			const getActiveMonth = function () {
				let scope = calendar;
				const activeSource = calendar.querySelector(
					'[data-memml-view][aria-pressed="true"]'
				);

				if ( activeSource ) {
					const sourcePanel = document.getElementById(
						activeSource.getAttribute( 'aria-controls' )
					);

					if ( sourcePanel ) {
						scope = sourcePanel;
					}
				}

				const panel = scope.querySelector(
					'[data-memml-layout-panel="month"] [data-memml-month-index]:not([hidden])'
				);

				return panel ? panel.dataset.month : '';
			};

			sourceButtons.forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					const source = button.dataset.memmlView;
					const changes = { memml_calendar: source };

					showSource( source );
					if ( 'month' === calendar.dataset.layout ) {
						changes.memml_month = getActiveMonth();
					}
					updateUrl( changes );
				} );
			} );

			layoutButtons.forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					const layout = button.dataset.memmlLayout;
					const changes = { memml_view: layout };

					showLayout( layout );
					if ( 'month' === layout ) {
						changes.memml_month = getActiveMonth();
					}
					updateUrl( changes );
				} );
			} );

			periodButtons.forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					const period = button.dataset.memmlPeriod;

					showPeriod( period );
					updateUrl( { memml_period: period } );
				} );
			} );

			rootControllers.push( {
				initialCalendar,
				initialLayout,
				initialPeriod,
				showLayout,
				showPeriod,
				showSource,
			} );
		} );

	document
		.querySelectorAll( '[data-memml-month-calendar]' )
		.forEach( function ( calendar ) {
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
					updateUrl( {
						memml_month: panels[ current ].dataset.month,
					} );
				}
			};

			const showMonthKey = function ( month ) {
				const index = panels.findIndex( function ( panel ) {
					return panel.dataset.month === month;
				} );

				if ( index >= 0 ) {
					showMonth( index, false );
				}
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
			monthControllers.push( { initialMonth, showMonthKey } );
		} );

	const applyUrlState = function () {
		const parameters = new URL( window.location.href ).searchParams;
		const calendar = parameters.get( 'memml_calendar' );
		const layout = parameters.get( 'memml_view' );
		const month = parameters.get( 'memml_month' );
		const period = parameters.get( 'memml_period' );

		rootControllers.forEach( function ( controller ) {
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
			controller.showMonthKey(
				/^\d{4}-(0[1-9]|1[0-2])$/.test( month || '' )
					? month
					: controller.initialMonth
			);
		} );
	};

	window.addEventListener( 'popstate', applyUrlState );
	applyUrlState();

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
