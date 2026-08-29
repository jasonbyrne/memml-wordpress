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
		close: 'Close',
	};

	// Controls are real links, so the calendar works without JavaScript. Only
	// take over plain activations; modified clicks keep the browser's own
	// behaviour, such as opening the view in a new tab.
	const takesOver = function ( event ) {
		return (
			! event.defaultPrevented &&
			( ! event.button || 0 === event.button ) &&
			! event.altKey &&
			! event.ctrlKey &&
			! event.metaKey &&
			! event.shiftKey
		);
	};

	const setCurrent = function ( element, isCurrent ) {
		if ( isCurrent ) {
			element.setAttribute( 'aria-current', 'true' );
		} else {
			element.removeAttribute( 'aria-current' );
		}
	};

	const formatMessage = function ( template, values ) {
		return Object.keys( values ).reduce( function ( message, key ) {
			return message.replace( '{' + key + '}', values[ key ] );
		}, template );
	};

	const buildUrl = function ( prefix, changes ) {
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

		return url.pathname + url.search + url.hash;
	};

	const updateUrl = function ( prefix, changes ) {
		const nextUrl = buildUrl( prefix, changes );
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

					setCurrent( candidate, isActive );
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
					setCurrent(
						candidate,
						candidate.dataset.memmlLayout === layout
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
					setCurrent(
						candidate,
						candidate.dataset.memmlPeriod === period
					);
				} );
				periodPanels.forEach( function ( panel ) {
					panel.hidden = panel.dataset.memmlPeriodPanel !== period;
				} );
			};

			const getSourceScope = function () {
				const activeSource = calendar.querySelector(
					'[data-memml-view][aria-current="true"]'
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
				button.addEventListener( 'click', function ( event ) {
					if ( ! takesOver( event ) ) {
						return;
					}

					event.preventDefault();

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
				button.addEventListener( 'click', function ( event ) {
					if ( ! takesOver( event ) ) {
						return;
					}

					event.preventDefault();

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
				button.addEventListener( 'click', function ( event ) {
					if ( ! takesOver( event ) ) {
						return;
					}

					event.preventDefault();

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

			// Anchors cannot be disabled, and an anchor without an href leaves
			// the accessibility tree. An unreachable month therefore keeps a
			// link to the month already shown and is marked aria-disabled,
			// matching the server output.
			const setMonthLink = function ( link, index ) {
				if ( ! link ) {
					return;
				}

				const panel = panels[ index ] || panels[ current ];

				if ( panels[ index ] ) {
					link.removeAttribute( 'aria-disabled' );
				} else {
					link.setAttribute( 'aria-disabled', 'true' );
				}

				if ( panel ) {
					link.setAttribute(
						'href',
						buildUrl( prefix, { month: panel.dataset.month } )
					);
				}
			};

			const showMonth = function ( index, updateHistory ) {
				current = Math.max( 0, Math.min( panels.length - 1, index ) );

				panels.forEach( function ( panel, panelIndex ) {
					panel.hidden = panelIndex !== current;
				} );

				if ( label && panels[ current ] ) {
					label.textContent = panels[ current ].dataset.monthLabel;
				}

				setMonthLink( previous, current - 1 );
				setMonthLink( next, current + 1 );

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

			const navigate = function ( link, step ) {
				if ( ! link ) {
					return;
				}

				link.addEventListener( 'click', function ( event ) {
					if ( ! takesOver( event ) ) {
						return;
					}

					event.preventDefault();

					if ( link.hasAttribute( 'aria-disabled' ) ) {
						return;
					}

					showMonth( current + step, true );
				} );
			};

			navigate( previous, -1 );
			navigate( next, 1 );

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

	// Each item carries a hidden panel with its full details. Clicking the
	// item (or its title, which becomes a real button for keyboard and
	// assistive-technology users) opens those details in a modal dialog.
	// Without JavaScript nothing is wired up and the summaries stand alone.
	document
		.querySelectorAll( '[data-memml-calendar]' )
		.forEach( function ( calendar ) {
			if ( ! window.HTMLDialogElement ) {
				return;
			}

			let dialog = null;
			let content = null;

			const ensureDialog = function () {
				if ( dialog ) {
					return;
				}

				dialog = document.createElement( 'dialog' );
				dialog.className = 'memml-calendar__dialog';

				const close = document.createElement( 'button' );

				close.type = 'button';
				close.className = 'memml-calendar__dialog-close';
				close.setAttribute( 'aria-label', messages.close || 'Close' );
				close.innerHTML = '&times;';
				close.addEventListener( 'click', function () {
					dialog.close();
				} );

				// A click on the backdrop lands on the dialog element itself;
				// clicks inside land on its children.
				dialog.addEventListener( 'click', function ( event ) {
					if ( event.target === dialog ) {
						dialog.close();
					}
				} );

				content = document.createElement( 'div' );
				content.className = 'memml-calendar__dialog-content';

				dialog.append( close, content );
				calendar.append( dialog );
			};

			const openDetails = function ( item ) {
				const details = item.querySelector( '[data-memml-details]' );

				if ( ! details ) {
					return;
				}

				ensureDialog();

				const clone = details.cloneNode( true );
				const heading = clone.querySelector(
					'.memml-calendar__details-title'
				);

				clone.removeAttribute( 'hidden' );
				content.replaceChildren( clone );
				if ( heading ) {
					dialog.setAttribute( 'aria-label', heading.textContent );
				}
				dialog.showModal();
			};

			calendar
				.querySelectorAll( '[data-memml-item]' )
				.forEach( function ( item ) {
					if ( ! item.querySelector( '[data-memml-details]' ) ) {
						return;
					}

					const title = item.querySelector(
						'.memml-calendar__title, .memml-calendar__month-title'
					);

					if ( title && ! title.querySelector( 'button' ) ) {
						const opener = document.createElement( 'button' );

						opener.type = 'button';
						opener.className = 'memml-calendar__title-button';
						opener.append( ...title.childNodes );
						title.append( opener );
					}

					item.classList.add( 'memml-calendar__item--openable' );
					item.addEventListener( 'click', function ( event ) {
						const interactive = event.target.closest( 'a, button' );

						if (
							interactive &&
							! interactive.classList.contains(
								'memml-calendar__title-button'
							)
						) {
							return;
						}

						// Releasing a text selection also fires a click;
						// opening the dialog would discard the selection.
						const selection =
							item.ownerDocument.defaultView.getSelection();

						if ( selection && 'Range' === selection.type ) {
							return;
						}

						openDetails( item );
					} );
				} );
		} );

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
