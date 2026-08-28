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
