function createStubUserSettings( expectEnabled ) {
	return {
		isPreviewTypeEnabled() {
			return expectEnabled !== false;
		}
	};
}

function createStubUser( isAnon, options ) {
	return {
		isNamed() {
			return !isAnon;
		},
		isAnon() {
			return isAnon;
		},
		options
	};
}

const options = { get: () => '1' };

( mw.loader.getModuleNames().indexOf( 'ext.popups.main' ) !== -1 ?
	QUnit.module :
	QUnit.module.skip )( 'ext.cite.referencePreviews#isReferencePreviewsEnabled' );

QUnit.test( 'relevant combinations of anonymous flags', ( assert ) => {
	[
		{
			testCase: 'enabled for an anonymous user',
			wgCiteReferencePreviewsActive: true,
			isAnon: true,
			enabledByAnon: true,
			expected: true
		},
		{
			testCase: 'turned off via the feature flag (anonymous user)',
			wgCiteReferencePreviewsActive: false,
			isAnon: true,
			enabledByAnon: true,
			expected: null
		},
		{
			testCase: 'manually disabled by the anonymous user',
			wgCiteReferencePreviewsActive: true,
			isAnon: true,
			enabledByAnon: false,
			expected: false
		}
	].forEach( ( data ) => {
		const user = {
				isNamed: () => !data.isAnon && !data.isIPMasked,
				isAnon: () => data.isAnon,
				options: {
					get: () => {}
				}
			},
			isPreviewTypeEnabled = () => {
				if ( !data.isAnon ) {
					assert.true( false, 'not expected to be called' );
				}
				return data.enabledByAnon;
			},
			config = new Map();
		config.set( 'wgCiteReferencePreviewsActive', data.wgCiteReferencePreviewsActive );

		if ( data.isAnon ) {
			user.options.get = () => assert.true( false, 'not expected to be called 2' );
		} else {
			user.options.get = () => data.enabledByRegistered ? '1' : '0';
		}

		assert.strictEqual(
			require( 'ext.cite.referencePreviews' ).private.isReferencePreviewsEnabled( user, isPreviewTypeEnabled, config ),
			data.expected,
			data.testCase
		);
	} );
} );

QUnit.test( 'it should display reference previews when conditions are fulfilled', ( assert ) => {
	const user = createStubUser( false, options ),
		userSettings = createStubUserSettings( false ),
		config = new Map();

	config.set( 'wgCiteReferencePreviewsActive', true );

	assert.true(
		require( 'ext.cite.referencePreviews' ).private.isReferencePreviewsEnabled( user, userSettings, config ),
		'If the user is logged in and the user is in the on group, then it\'s enabled.'
	);
} );

QUnit.test( 'it should not be enabled when the global is disabling it', ( assert ) => {
	const user = createStubUser( false ),
		userSettings = createStubUserSettings( false ),
		config = new Map();

	config.set( 'wgCiteReferencePreviewsActive', false );

	assert.strictEqual(
		require( 'ext.cite.referencePreviews' ).private.isReferencePreviewsEnabled( user, userSettings, config ),
		null,
		'Reference Previews is disabled.'
	);
} );
