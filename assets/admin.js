( function () {
	'use strict';

	if ( typeof window.memmlAdmin === 'undefined' ) {
		return;
	}

	const settings = window.memmlAdmin;
	const form = document.getElementById( 'memml-settings-form' );

	// Test connection checks the unsaved key and base URL.
	const button = document.getElementById( 'memml-test-connection' );
	const result = document.getElementById( 'memml-connection-result' );

	if ( button && result ) {
		button.addEventListener( 'click', async function () {
			const organizationKey = document.getElementById(
				'memml-organization-key'
			);
			const baseUrl = document.getElementById( 'memml-base-url' );
			const data = new window.FormData();

			data.append( 'action', 'memml_test_connection' );
			data.append( 'nonce', settings.nonce );
			data.append( 'organizationKey', organizationKey.value );
			data.append( 'baseUrl', baseUrl.value );

			button.disabled = true;
			result.className = 'memml-connection-result';
			result.textContent = settings.testing;

			try {
				const response = await window.fetch( settings.ajaxUrl, {
					method: 'POST',
					body: data,
					credentials: 'same-origin',
				} );
				const payload = await response.json();

				result.classList.add(
					payload.success ? 'is-success' : 'is-error'
				);
				result.textContent =
					payload.data?.message || settings.unknownError;
			} catch {
				result.classList.add( 'is-error' );
				result.textContent = settings.unknownError;
			} finally {
				button.disabled = false;
			}
		} );
	}

	if ( ! form ) {
		return;
	}

	const fieldName = function ( name ) {
		return settings.optionName + '[' + name + ']';
	};

	// The preview shows the unsaved display, content, action, and appearance
	// choices. The connection always comes from the saved settings.
	const frame = document.getElementById( 'memml-preview-frame' );
	const calendarChoice = document.getElementById( 'memml-preview-calendar' );
	const previewNames = Object.keys( settings.defaults || {} ).reduce(
		function ( names, section ) {
			return names.concat(
				Object.keys( settings.defaults[ section ] ).map( fieldName )
			);
		},
		[]
	);
	let previewTimer = null;

	const updatePreview = function () {
		if ( ! frame ) {
			return;
		}

		const url = new URL( frame.src, window.location.href );
		const data = new window.FormData( form );

		previewNames.forEach( function ( name ) {
			url.searchParams.delete( name );
		} );
		url.searchParams.set(
			'calendar',
			calendarChoice ? calendarChoice.value : 'calendar'
		);
		// Unchecked boxes are absent and resolve to off. The selects are
		// always present, so the preview never falls back to saved values.
		data.forEach( function ( value, name ) {
			if ( previewNames.includes( name ) ) {
				url.searchParams.append( name, value );
			}
		} );

		frame.src = url.toString();
	};

	const schedulePreview = function ( event ) {
		if ( event && ! previewNames.includes( event.target.name ) ) {
			return;
		}

		window.clearTimeout( previewTimer );
		previewTimer = window.setTimeout( updatePreview, 400 );
	};

	// Choosing a color implies using it.
	const accentColor = document.getElementById( 'memml-accent-color' );
	const accentCustom = document.getElementById( 'memml-accent-custom' );

	if ( accentColor && accentCustom ) {
		accentColor.addEventListener( 'input', function () {
			accentCustom.checked = true;
		} );
	}

	form.addEventListener( 'input', schedulePreview );
	form.addEventListener( 'change', schedulePreview );

	if ( calendarChoice ) {
		calendarChoice.addEventListener( 'change', function () {
			window.clearTimeout( previewTimer );
			updatePreview();
		} );
	}

	// Reset fills in a section's defaults; Save Changes applies them.
	document
		.querySelectorAll( '[data-memml-reset]' )
		.forEach( function ( resetButton ) {
			const section = resetButton.dataset.memmlReset;
			const defaults = ( settings.defaults || {} )[ section ];

			if ( ! defaults ) {
				return;
			}

			const status = document.querySelector(
				'[data-memml-reset-result="' + section + '"]'
			);

			resetButton.hidden = false;
			resetButton.addEventListener( 'click', function () {
				Object.keys( defaults ).forEach( function ( name ) {
					const field = form.elements.namedItem( fieldName( name ) );

					if ( ! field ) {
						return;
					}

					if ( 'checkbox' === field.type ) {
						field.checked = Boolean( defaults[ name ] );
					} else {
						field.value = String( defaults[ name ] );
					}
				} );

				if ( status ) {
					status.textContent = settings.resetDone;
				}

				window.clearTimeout( previewTimer );
				updatePreview();
			} );
		} );
} )();
