const { expect, test } = require( '@playwright/test' );

test( 'scopes URL state to each calendar and restores it through history', async ( {
	page,
} ) => {
	await page.goto( '/tests/e2e/fixture.html' );

	const main = page.locator( '[data-memml-url-prefix="memml_main_"]' );
	const sidebar = page.locator( '[data-memml-url-prefix="memml_sidebar_"]' );

	await main.getByRole( 'link', { name: 'Month' } ).click();
	await expect( page ).toHaveURL( /memml_main_view=month/ );
	await expect( page ).toHaveURL( /memml_main_month=2026-08/ );
	await expect( sidebar ).toHaveAttribute( 'data-layout', 'list' );

	await main.getByRole( 'link', { name: 'Next month' } ).click();
	await expect( page ).toHaveURL( /memml_main_month=2026-09/ );
	await expect(
		main.locator( '#main-events [data-memml-month-label]' )
	).toHaveText( 'September 2026' );
	await expect( main.locator( '[data-memml-status]' ) ).toHaveText(
		'Showing September 2026, 0 events.'
	);

	await sidebar.getByRole( 'link', { name: 'Past' } ).click();
	await expect( page ).toHaveURL( /memml_sidebar_period=past/ );
	await expect( main ).toHaveAttribute( 'data-period', 'upcoming' );
	await expect( sidebar.locator( '[data-memml-status]' ) ).toHaveText(
		'Showing 1 volunteer opportunity.'
	);

	await page.reload();
	await expect( main ).toHaveAttribute( 'data-layout', 'month' );
	await expect(
		main.locator( '#main-events [data-memml-month-label]' )
	).toHaveText( 'September 2026' );
	await expect( sidebar ).toHaveAttribute( 'data-period', 'past' );

	await page.goBack();
	await expect( sidebar ).toHaveAttribute( 'data-period', 'upcoming' );
	await expect( main ).toHaveAttribute( 'data-layout', 'month' );
} );

test( 'applies independently scoped direct-link parameters', async ( {
	page,
} ) => {
	await page.goto(
		'/tests/e2e/fixture.html?memml_main_calendar=volunteers&memml_main_view=month&memml_main_month=2026-10&memml_sidebar_period=past'
	);

	const main = page.locator( '[data-memml-url-prefix="memml_main_"]' );
	const sidebar = page.locator( '[data-memml-url-prefix="memml_sidebar_"]' );

	await expect( main ).toHaveAttribute( 'data-calendar', 'volunteers' );
	await expect( main ).toHaveAttribute( 'data-layout', 'month' );
	await expect(
		main.locator( '#main-volunteers [data-memml-month-label]' )
	).toHaveText( 'October 2026' );
	await expect( sidebar ).toHaveAttribute( 'data-layout', 'list' );
	await expect( sidebar ).toHaveAttribute( 'data-period', 'past' );
} );

test( 'opens an item’s full details in a modal dialog', async ( { page } ) => {
	await page.goto( '/tests/e2e/fixture.html' );

	const main = page.locator( '[data-memml-url-prefix="memml_main_"]' );
	const dialog = main.locator( 'dialog.memml-calendar__dialog' );

	// The item title becomes a real button, so keyboard users can open it.
	await main.getByRole( 'button', { name: 'Upcoming event' } ).click();
	await expect( dialog ).toBeVisible();
	await expect( dialog ).toContainText(
		'Full event description shown in the dialog.'
	);
	await expect( dialog ).toContainText( 'Use the lot behind the building.' );

	await page.keyboard.press( 'Escape' );
	await expect( dialog ).toBeHidden();

	// Clicking anywhere on the card, not just the title, opens it too.
	await main
		.locator( '[data-memml-period-panel="upcoming"] [data-memml-item]' )
		.first()
		.click( { position: { x: 5, y: 5 } } );
	await expect( dialog ).toBeVisible();

	await dialog.getByRole( 'button', { name: 'Close' } ).click();
	await expect( dialog ).toBeHidden();
} );

test( 'renders structured venue data without turning its map link into a dialog opener', async ( {
	page,
} ) => {
	await page.goto( '/tests/e2e/fixture.html' );

	const main = page.locator( '[data-memml-url-prefix="memml_main_"]' );
	const venue = main.locator( '.memml-calendar__venue--enhanced' ).first();
	const mapLink = venue.getByRole( 'link', { name: 'View on Google Maps' } );
	const dialog = main.locator( 'dialog.memml-calendar__dialog' );

	await expect( venue ).toContainText( 'Longwood Historic Civic Center' );
	await expect( venue ).toContainText(
		'135 W Church Ave, Longwood, FL 32750, US'
	);
	await expect( mapLink ).toHaveAttribute(
		'href',
		/^https:\/\/www\.google\.com\/maps\/search\/\?api=1&query=/
	);

	await mapLink.evaluate( ( link ) => {
		link.addEventListener( 'click', ( event ) => event.preventDefault(), {
			once: true,
		} );
	} );
	await mapLink.click();
	await expect( dialog ).toBeHidden();
} );

test( 'exposes every control as a shareable link and handles clicks in place', async ( {
	page,
} ) => {
	await page.goto( '/tests/e2e/fixture.html' );

	const main = page.locator( '[data-memml-url-prefix="memml_main_"]' );
	const monthLink = main.getByRole( 'link', { name: 'Month' } );
	const pastLink = main.getByRole( 'link', { name: 'Past' } );

	// Controls carry real URLs, so they work without JavaScript and can be
	// opened in a new tab or copied.
	await expect( monthLink ).toHaveAttribute(
		'href',
		/memml_main_view=month/
	);
	await expect( pastLink ).toHaveAttribute(
		'href',
		/memml_main_period=past/
	);
	await expect(
		main.getByRole( 'link', { name: 'Volunteer Opportunities' } )
	).toHaveAttribute( 'href', /memml_main_calendar=volunteers/ );

	// With JavaScript the same click is handled in place, without a reload.
	await page.evaluate( () => {
		window.memmlNotReloaded = true;
	} );
	await monthLink.click();
	await expect( page ).toHaveURL( /memml_main_view=month/ );
	expect( await page.evaluate( () => window.memmlNotReloaded ) ).toBe( true );

	// Anchors cannot be disabled, so an unreachable month drops its href.
	const previousLink = main.getByRole( 'link', { name: 'Previous month' } );
	const nextLink = main.getByRole( 'link', { name: 'Next month' } );

	await expect( previousLink ).toHaveAttribute( 'aria-disabled', 'true' );
	await expect( previousLink ).toHaveAttribute(
		'href',
		/memml_main_month=2026-08/
	);
	await expect( nextLink ).toHaveAttribute(
		'href',
		/memml_main_month=2026-09/
	);

	await nextLink.click();
	await expect( previousLink ).not.toHaveAttribute( 'aria-disabled', 'true' );
	await expect( previousLink ).toHaveAttribute(
		'href',
		/memml_main_month=2026-08/
	);
	await expect( nextLink ).toHaveAttribute(
		'href',
		/memml_main_month=2026-10/
	);

	await nextLink.click();
	await expect( nextLink ).toHaveAttribute( 'aria-disabled', 'true' );
	expect( await page.evaluate( () => window.memmlNotReloaded ) ).toBe( true );
} );

test( 'keeps add-to-calendar links grouped beneath their label', async ( {
	page,
} ) => {
	await page.goto( '/tests/e2e/preview.html' );

	const actionGroup = page
		.locator( '.memml-calendar__actions' )
		.filter( { has: page.getByRole( 'link', { name: 'Volunteer' } ) } )
		.first();
	const styles = await actionGroup.evaluate( ( actions ) => {
		const addLinks = actions.querySelector( '.memml-calendar__add-links' );
		const label = actions.querySelector( '.memml-calendar__add-label' );

		return {
			addLinksBasis: window.getComputedStyle( addLinks ).flexBasis,
			labelBasis: window.getComputedStyle( label ).flexBasis,
			marginTop: Number.parseFloat(
				window.getComputedStyle( addLinks ).marginBlockStart
			),
		};
	} );

	expect( styles.addLinksBasis ).toBe( '100%' );
	expect( styles.labelBasis ).toBe( '100%' );
	expect( styles.marginTop ).toBeGreaterThan( 0 );
} );
