const { expect, test } = require( '@playwright/test' );

test( 'scopes URL state to each calendar and restores it through history', async ( {
	page,
} ) => {
	await page.goto( '/tests/e2e/fixture.html' );

	const main = page.locator( '[data-memml-url-prefix="memml_main_"]' );
	const sidebar = page.locator( '[data-memml-url-prefix="memml_sidebar_"]' );

	await main.getByRole( 'button', { name: 'Month' } ).click();
	await expect( page ).toHaveURL( /memml_main_view=month/ );
	await expect( page ).toHaveURL( /memml_main_month=2026-08/ );
	await expect( sidebar ).toHaveAttribute( 'data-layout', 'list' );

	await main.getByRole( 'button', { name: 'Next month' } ).click();
	await expect( page ).toHaveURL( /memml_main_month=2026-09/ );
	await expect(
		main.locator( '#main-events [data-memml-month-label]' )
	).toHaveText( 'September 2026' );
	await expect( main.locator( '[data-memml-status]' ) ).toHaveText(
		'Showing September 2026, 0 events.'
	);

	await sidebar.getByRole( 'button', { name: 'Past' } ).click();
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
