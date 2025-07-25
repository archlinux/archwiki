'use strict';

const { config } = require( 'wdio-mediawiki/wdio-defaults.conf.js' ),
	childProcess = require( 'child_process' ),
	path = require( 'path' ),
	ip = path.resolve( __dirname + '/../../../../' ),
	LocalSettingsSetup = require( './LocalSettingsSetup' ),
	LoginAsCheckUser = require( './checkuserlogin' );

const { SevereServiceError } = require( 'webdriverio' );

exports.config = { ...config,
	// Override, or add to, the setting from wdio-mediawiki.
	// Learn more at https://webdriver.io/docs/configurationfile/
	//
	// Example:
	// logLevel: 'info',
	maxInstances: 5,
	async onPrepare() {
		await LocalSettingsSetup.overrideLocalSettings();
		await LocalSettingsSetup.restartPhpFpmService();

		const { username, password } = LoginAsCheckUser.getCheckUserAccountDetails();

		// Setup required user account.
		const createAndPromoteResult = childProcess.spawnSync(
			'php',
			[
				'maintenance/run.php',
				'createAndPromote',
				'--force',
				'--custom-groups',
				'checkuser',
				username,
				password
			],
			{ cwd: ip }
		);
		if ( createAndPromoteResult.status === 1 ) {
			console.log( String( createAndPromoteResult.stderr ) );
			throw new SevereServiceError( 'Unable to populate test data' );
		}
	},
	async onComplete() {
		await LocalSettingsSetup.restoreLocalSettings();
		await LocalSettingsSetup.restartPhpFpmService();
	}
};
