import { config as wdioDefaults } from 'wdio-mediawiki/wdio-defaults.conf.js';

export const config = { ...wdioDefaults,
	// Override, or add to, the setting from wdio-mediawiki.
	// Learn more at https://webdriver.io/docs/configurationfile/
	//
	// Example:
	// logLevel: 'info',

	maxInstances: 4,
	suites: {
		daily: [
			'specs/content_editable.js'
		]
	}
};
