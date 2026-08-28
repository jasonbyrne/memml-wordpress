const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	use: {
		baseURL: 'http://127.0.0.1:8891',
		channel: process.env.CI ? undefined : 'chrome',
		headless: true,
	},
	webServer: {
		command: 'python3 -m http.server 8891 --bind 127.0.0.1 --directory .',
		reuseExistingServer: true,
		url: 'http://127.0.0.1:8891/tests/e2e/fixture.html',
	},
} );
