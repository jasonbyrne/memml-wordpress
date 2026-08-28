( function () {
	'use strict';

	const button = document.getElementById( 'memml-test-connection' );
	const result = document.getElementById( 'memml-connection-result' );

	if ( ! button || ! result || typeof window.memmlAdmin === 'undefined' ) {
		return;
	}

	button.addEventListener( 'click', async function () {
		const organizationKey = document.getElementById( 'memml-organization-key' );
		const baseUrl = document.getElementById( 'memml-base-url' );
		const data = new window.FormData();

		data.append( 'action', 'memml_test_connection' );
		data.append( 'nonce', window.memmlAdmin.nonce );
		data.append( 'organizationKey', organizationKey.value );
		data.append( 'baseUrl', baseUrl.value );

		button.disabled = true;
		result.className = 'memml-connection-result';
		result.textContent = window.memmlAdmin.testing;

		try {
			const response = await window.fetch( window.memmlAdmin.ajaxUrl, {
				method: 'POST',
				body: data,
				credentials: 'same-origin',
			} );
			const payload = await response.json();

			result.classList.add( payload.success ? 'is-success' : 'is-error' );
			result.textContent = payload.data?.message || window.memmlAdmin.unknownError;
		} catch ( error ) {
			result.classList.add( 'is-error' );
			result.textContent = window.memmlAdmin.unknownError;
		} finally {
			button.disabled = false;
		}
	} );
}() );
