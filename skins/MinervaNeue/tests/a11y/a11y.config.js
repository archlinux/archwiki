const path = require( 'path' );

const testData = {
	baseUrl: process.env.MW_SERVER,
	pageUrl: '/wiki/Polar_bear?mobileaction=toggle_view_mobile',
	loginUser: process.env.MEDIAWIKI_USER,
	loginPassword: process.env.MEDIAWIKI_PASSWORD
};

module.exports = {
	// LOG_DIR set in CI, used to make report files available in Jenkins
	reportDir: process.env.LOG_DIR || path.join( process.cwd(), 'a11y/' ),
	namespace: 'Minerva',
	defaults: {
		viewport: {
			width: 1200,
			height: 1080
		},
		runners: [
			'axe',
			'htmlcs'
		],
		includeWarnings: true,
		includeNotices: true,
		hideElements: '#bodyContent, #siteNotice, #mwe-pt-toolbar, #centralnotice, #centralnotice_testbanner',
		chromeLaunchConfig: {
			headless: false,
			args: [
				'--no-sandbox',
				'--disable-setuid-sandbox'
			]
		}
	},
	tests: [
		{
			name: 'default',
			url: testData.baseUrl + testData.pageUrl,
			actions: [
				'check field #main-menu-input' // Open main menu
			]
		},
		{
			name: 'logged_in',
			url: testData.baseUrl + testData.pageUrl,
			wait: '500',
			actions: [
				'check field #main-menu-input',
				'wait for .menu__item--login to be visible',
				'click .menu__item--login',
				'wait for #wpName1 to be visible',
				'set field #wpName1 to ' + testData.loginUser,
				'set field #wpPassword1 to ' + testData.loginPassword,
				'click #wpLoginAttempt',
				'navigate to ' + testData.baseUrl + testData.pageUrl
			]
		},
		{
			name: 'language',
			url: testData.baseUrl + testData.pageUrl,
			wait: '500',
			actions: [
				'click .language-selector'
			]
		},
		{
			name: 'anon_edit',
			url: testData.baseUrl + testData.pageUrl,
			wait: '500',
			actions: [
				'click #ca-edit',
				'wait for .actions to be visible',
				'click .actions a:first-child', // click edit without login
				'wait for .oo-ui-popupToolGroup-handle to be visible',
				'click .oo-ui-popupToolGroup-handle'
			]
		},
		{
			name: 'search',
			url: testData.baseUrl + testData.pageUrl,
			wait: '500',
			actions: [
				'click #searchInput',
				'wait for #searchInput to be visible',
				'set field #searchInput to foo'
			]
		}
	]
};
