const { expect, test } = require( '@playwright/test' );

const phone = { width: 375, height: 812 };
const desktop = { width: 1280, height: 800 };

const calendars = ( page ) => ( {
	main: page.locator( '[data-memml-url-prefix="memml_phone_"]' ),
	fixed: page.locator( '[data-memml-url-prefix="memml_fixed_"]' ),
} );

// The month grid scrolls inside its own region rather than the page, so
// check every visible scroll region as well as the document.
const scrollsSideways = ( page ) =>
	page.evaluate( () =>
		[
			document.documentElement,
			...document.querySelectorAll( '.memml-calendar__month-scroll' ),
		].some(
			( element ) =>
				element.getClientRects().length > 0 &&
				element.scrollWidth > element.clientWidth
		)
	);

test.describe( 'on a phone', () => {
	test.use( { viewport: phone } );

	test( 'opens Month calendars in List view without sideways scrolling', async ( {
		page,
	} ) => {
		await page.goto( '/tests/e2e/phone.html' );

		const { main, fixed } = calendars( page );

		await expect( main ).toHaveAttribute( 'data-layout', 'list' );
		await expect( main.locator( '#phone-events-list' ) ).toBeVisible();
		await expect( main.locator( '#phone-events-month' ) ).toBeHidden();
		await expect(
			main.getByRole( 'link', { name: 'List' } )
		).toHaveAttribute( 'aria-current', 'true' );
		await expect(
			main.getByRole( 'link', { name: 'Month' } )
		).not.toHaveAttribute( 'aria-current', 'true' );
		await expect(
			main.getByRole( 'group', { name: 'Filter by date' } )
		).toBeVisible();
		await expect( main ).not.toHaveClass( /memml-calendar--phone-list/ );

		await expect( fixed ).toHaveAttribute( 'data-layout', 'list' );
		await expect(
			fixed.getByText( 'Fixed upcoming opportunity' )
		).toBeVisible();
		await expect( fixed.locator( '#fixed-volunteers-month' ) ).toBeHidden();

		expect( await scrollsSideways( page ) ).toBe( false );
	} );

	test( 'still lets visitors choose Month, and a shared Month link opens Month', async ( {
		page,
	} ) => {
		await page.goto( '/tests/e2e/phone.html' );

		const { main } = calendars( page );

		await main.getByRole( 'link', { name: 'Month' } ).click();
		await expect( page ).toHaveURL( /memml_phone_view=month/ );
		await expect( main.locator( '#phone-events-month' ) ).toBeVisible();
		await expect( main.locator( '#phone-events-list' ) ).toBeHidden();
		// The compact phone grid fits without scrolling sideways.
		expect( await scrollsSideways( page ) ).toBe( false );

		await page.reload();
		await expect( main ).toHaveAttribute( 'data-layout', 'month' );
		await expect( main.locator( '#phone-events-month' ) ).toBeVisible();

		await page.goBack();
		await expect( main ).toHaveAttribute( 'data-layout', 'list' );
		await expect( main.locator( '#phone-events-list' ) ).toBeVisible();
	} );
} );

test.describe( 'on a wide screen', () => {
	test.use( { viewport: desktop } );

	test( 'keeps Month view', async ( { page } ) => {
		await page.goto( '/tests/e2e/phone.html' );

		const { main, fixed } = calendars( page );

		await expect( main ).toHaveAttribute( 'data-layout', 'month' );
		await expect( main.locator( '#phone-events-month' ) ).toBeVisible();
		await expect( main.locator( '#phone-events-list' ) ).toBeHidden();
		await expect(
			main.getByRole( 'group', { name: 'Filter by date' } )
		).toBeHidden();
		await expect(
			fixed.locator( '#fixed-volunteers-month' )
		).toBeVisible();
		await expect( fixed.locator( '#fixed-volunteers-list' ) ).toBeHidden();
	} );
} );

test.describe( 'without JavaScript', () => {
	test.use( { javaScriptEnabled: false } );

	test( 'shows the list on phones and the month grid on wide screens', async ( {
		page,
	} ) => {
		const { main, fixed } = calendars( page );

		await page.setViewportSize( phone );
		await page.goto( '/tests/e2e/phone.html' );
		await expect( main.locator( '#phone-events-list' ) ).toBeVisible();
		await expect( main.locator( '#phone-events-month' ) ).toBeHidden();
		await expect(
			main.getByRole( 'group', { name: 'Filter by date' } )
		).toBeVisible();
		await expect( fixed.locator( '#fixed-volunteers-list' ) ).toBeVisible();
		await expect( fixed.locator( '#fixed-volunteers-month' ) ).toBeHidden();
		expect( await scrollsSideways( page ) ).toBe( false );

		await page.setViewportSize( desktop );
		await expect( main.locator( '#phone-events-month' ) ).toBeVisible();
		await expect( main.locator( '#phone-events-list' ) ).toBeHidden();
		await expect(
			main.getByRole( 'group', { name: 'Filter by date' } )
		).toBeHidden();
	} );
} );
