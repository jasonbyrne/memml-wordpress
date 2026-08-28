( function () {
	'use strict';

	document
		.querySelectorAll( '[data-memml-calendar]' )
		.forEach( function ( calendar ) {
			const buttons = calendar.querySelectorAll( '[data-memml-view]' );

			buttons.forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					const view = button.dataset.memmlView;

					buttons.forEach( function ( candidate ) {
						const isActive = candidate.dataset.memmlView === view;
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
				} );
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
			let current = 0;

			const showMonth = function ( index ) {
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
			};

			if ( previous ) {
				previous.addEventListener( 'click', function () {
					showMonth( current - 1 );
				} );
			}

			if ( next ) {
				next.addEventListener( 'click', function () {
					showMonth( current + 1 );
				} );
			}
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
