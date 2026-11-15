const { expect, test } = require( '@playwright/test' );

const phone = { width: 375, height: 812 };
const desktop = { width: 1280, height: 800 };
const fixture = '/tests/e2e/month-phone.html';
const groupId = ( date ) => '#memml-month-calendar-1-day-' + date;

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

const isInViewport = ( locator ) =>
	locator.evaluate( ( element ) => {
		const rect = element.getBoundingClientRect();

		return rect.top >= 0 && rect.top < window.innerHeight;
	} );

test.describe( 'Month view on a phone', () => {
	test.use( { viewport: phone } );

	test( 'fits the screen with day links instead of titles', async ( {
		page,
	} ) => {
		await page.goto( fixture );

		const calendar = page.locator( '[data-memml-calendar]' );
		const busyDay = page.getByRole( 'link', {
			name: 'November 21, 2026, 3 events',
		} );

		await expect( calendar ).toHaveAttribute( 'data-layout', 'month' );
		await expect( busyDay ).toBeVisible();
		await expect(
			page.locator( '.memml-calendar__month-entry' ).first()
		).toBeHidden();
		expect( await scrollsSideways( page ) ).toBe( false );

		const linkBox = await busyDay.boundingBox();
		const quietDay = await page
			.locator(
				'.memml-calendar__month-day:not(.has-items):not(.is-empty)'
			)
			.first()
			.boundingBox();

		expect( linkBox.height ).toBeGreaterThanOrEqual( 44 );
		expect( linkBox.width ).toBeGreaterThanOrEqual( 44 );
		expect( quietDay.height ).toBeGreaterThanOrEqual( 44 );

		// The cancelled event on the 21st has a hollow dot.
		await expect(
			busyDay.locator( '.memml-calendar__day-dot.is-cancelled' )
		).toBeVisible();

		// The grid fits, so its scroll region is not a tab stop.
		await expect(
			page.locator( '.memml-calendar__month-scroll' ).first()
		).not.toHaveAttribute( 'tabindex' );

		// The day list repeats a multi-day item under every day it runs.
		const list = page.locator( '[data-memml-day-list]' ).first();

		await expect( list ).toBeVisible();
		await expect(
			page
				.locator( groupId( '2026-11-21' ) )
				.getByRole( 'heading', { name: 'Harvest weekend' } )
		).toBeVisible();
		await expect(
			page
				.locator( groupId( '2026-11-22' ) )
				.getByRole( 'heading', { name: 'Harvest weekend' } )
		).toBeVisible();
	} );

	test( 'choosing a day highlights it and moves focus to its items', async ( {
		page,
	} ) => {
		await page.goto( fixture );

		const group = page.locator( groupId( '2026-11-22' ) );

		await page
			.getByRole( 'link', { name: 'November 22, 2026, 1 event' } )
			.click();

		await expect( group ).toBeFocused();
		await expect( group ).toHaveClass( /is-selected/ );
		await expect(
			page.locator( '.memml-calendar__month-day.is-selected' )
		).toHaveCount( 1 );
		await expect.poll( () => isInViewport( group ) ).toBe( true );
		// No hash is added, so Back still leaves the page.
		expect( new URL( page.url() ).hash ).toBe( '' );

		// Items in the day list open the same details dialog.
		await group.getByRole( 'button', { name: 'Harvest weekend' } ).click();
		await expect( page.getByRole( 'dialog' ) ).toBeVisible();
		await expect(
			page.getByRole( 'dialog' ).getByRole( 'heading', {
				name: 'Harvest weekend',
			} )
		).toBeVisible();
	} );

	test( 'works from the keyboard and across months', async ( { page } ) => {
		await page.goto( fixture );

		const firstDay = page.getByRole( 'link', {
			name: 'November 3, 2026, 1 event',
		} );

		await firstDay.focus();
		await page.keyboard.press( 'Enter' );
		await expect( page.locator( groupId( '2026-11-03' ) ) ).toBeFocused();
		await page.keyboard.press( 'Tab' );
		await expect(
			page.getByRole( 'button', { name: 'Library story time' } ).last()
		).toBeFocused();

		await page.getByRole( 'link', { name: 'Next month' } ).click();
		await expect( page.locator( groupId( '2026-11-03' ) ) ).toBeHidden();
		await page
			.getByRole( 'link', { name: 'December 12, 2026, 4 events' } )
			.click();
		await expect( page.locator( groupId( '2026-12-12' ) ) ).toBeFocused();
		await expect(
			page.locator( groupId( '2026-12-12' ) ).locator( 'article' )
		).toHaveCount( 4 );
		expect( await scrollsSideways( page ) ).toBe( false );
	} );

	test( 'shows a plus sign for crowded days', async ( { page } ) => {
		await page.goto( fixture + '?memml_month_month=2026-12' );

		const crowdedDay = page.getByRole( 'link', {
			name: 'December 12, 2026, 4 events',
		} );

		await expect(
			crowdedDay.locator( '.memml-calendar__day-dot' )
		).toHaveCount( 3 );
		await expect(
			crowdedDay.locator( '.memml-calendar__day-more' )
		).toBeVisible();
	} );

	test( 'clears the chosen day when the month changes', async ( {
		page,
	} ) => {
		await page.goto( fixture );

		await page
			.getByRole( 'link', { name: 'November 22, 2026, 1 event' } )
			.click();
		await expect( page.locator( '.is-selected' ) ).toHaveCount( 2 );

		await page.getByRole( 'link', { name: 'Next month' } ).click();
		await page.getByRole( 'link', { name: 'Previous month' } ).click();
		await expect( page.locator( '.is-selected' ) ).toHaveCount( 0 );
	} );

	test( 'wraps words longer than the screen', async ( { page } ) => {
		await page.goto( fixture );
		await page
			.locator( '[data-memml-day-list] .memml-calendar__title' )
			.first()
			.evaluate( ( title ) => {
				title.textContent =
					'Antidisestablishmentarianism-themed-debate-club-championship-finals';
			} );

		expect( await scrollsSideways( page ) ).toBe( false );
	} );

	test( 'announces grid items only once', async ( { page } ) => {
		await page.goto( fixture );

		await page.getByRole( 'link', { name: 'List' } ).click();
		await page.getByRole( 'link', { name: 'Month' } ).click();
		// Five grid entries: one on the 3rd, three on the 21st, and the
		// continued multi-day item on the 22nd.
		await expect( page.locator( '[data-memml-status]' ) ).toHaveText(
			'Showing November 2026, 5 events.'
		);
	} );
} );

test.describe( 'Month view on a wide screen', () => {
	test.use( { viewport: desktop } );

	test( 'keeps titles in the grid and no day list', async ( { page } ) => {
		await page.goto( fixture );

		await expect(
			page.getByRole( 'heading', { name: 'Saturday fair' } ).first()
		).toBeVisible();
		await expect(
			page.locator( '[data-memml-day-link]' ).first()
		).toBeHidden();
		await expect(
			page.locator( '[data-memml-day-list]' ).first()
		).toBeHidden();
		// The full grid fits, so its scroll region is not a tab stop.
		await expect(
			page.locator( '.memml-calendar__month-scroll' ).first()
		).not.toHaveAttribute( 'tabindex' );
		expect( await scrollsSideways( page ) ).toBe( false );
	} );

	test( 'uses the compact grid in a narrow column', async ( { page } ) => {
		await page.goto( fixture );
		await page.addStyleTag( {
			content: '[data-memml-calendar] { max-width: 20rem; }',
		} );

		await expect(
			page.getByRole( 'link', { name: 'November 21, 2026, 3 events' } )
		).toBeVisible();
		await expect(
			page.locator( '.memml-calendar__month-entry' ).first()
		).toBeHidden();
		await expect(
			page.locator( '[data-memml-day-list]' ).first()
		).toBeVisible();
		await expect
			.poll( () =>
				page
					.locator( '.memml-calendar__month-scroll' )
					.first()
					.getAttribute( 'tabindex' )
			)
			.toBeNull();
		expect( await scrollsSideways( page ) ).toBe( false );
	} );

	test( 'returns to the full grid when its column widens', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 500, height: 800 } );
		await page.goto( fixture );
		await expect(
			page.locator( '[data-memml-day-link]' ).first()
		).toBeVisible();

		await page.setViewportSize( desktop );

		await expect(
			page.getByRole( 'heading', { name: 'Saturday fair' } ).first()
		).toBeVisible();
		await expect(
			page.locator( '[data-memml-day-link]' ).first()
		).toBeHidden();
		expect( await scrollsSideways( page ) ).toBe( false );
	} );
} );

test.describe( 'Month view at every common width', () => {
	for ( const width of [ 320, 360, 414, 600, 640, 700, 768, 1024 ] ) {
		test( `never scrolls sideways at ${ width }px`, async ( { page } ) => {
			await page.setViewportSize( { width, height: 800 } );
			await page.goto( fixture );

			expect( await scrollsSideways( page ) ).toBe( false );

			// One layout is shown: the compact grid or the full one.
			const links = await page
				.locator( '[data-memml-day-link]:visible' )
				.count();
			const entries = await page
				.locator( '.memml-calendar__month-entry:visible' )
				.count();

			expect( links > 0 ).not.toBe( entries > 0 );
		} );
	}
} );

test.describe( 'Month view on a phone without JavaScript', () => {
	test.use( { javaScriptEnabled: false, viewport: phone } );

	test( 'day links jump to their day', async ( { page } ) => {
		await page.goto( fixture );

		expect( await scrollsSideways( page ) ).toBe( false );

		await page
			.getByRole( 'link', { name: 'November 21, 2026, 3 events' } )
			.click();

		const group = page.locator( groupId( '2026-11-21' ) );

		expect( new URL( page.url() ).hash ).toBe( groupId( '2026-11-21' ) );
		await expect.poll( () => isInViewport( group ) ).toBe( true );
		await expect(
			group.getByRole( 'heading', { name: 'Saturday fair' } )
		).toBeVisible();
	} );
} );
