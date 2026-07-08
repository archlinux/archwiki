import { config as wdioDefaults } from 'wdio-mediawiki/wdio-defaults.conf.js';

export const config = { ...wdioDefaults
	// Override, or add to, the setting from wdio-mediawiki.
	// Learn more at https://webdriver.io/docs/configurationfile/
	//
	// Example:
	// logLevel: 'info',
	// To enable video recording, enable video and disable browser headless
	// recordVideo: true,
	// useBrowserHeadless: false,
	//
	// To enable screenshots on all tests, disable screenshotsOnFailureOnly
	// screenshotsOnFailureOnly: false,
};
