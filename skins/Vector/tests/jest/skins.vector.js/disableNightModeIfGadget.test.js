const {
	isNightModeGadgetEnabled,
	disableNightModeForGadget,
	alterDisableLink,
	alterExclusionMessage
} = require( '../../../resources/skins.vector.js/disableNightModeIfGadget.js' );

describe( 'isNightModeGadgetEnabled', () => {
	beforeEach( () => {
		// https://github.com/wikimedia/mw-node-qunit/pull/38
		mw.loader.getState = () => null;
	} );

	it( 'should return false if no gadgets are installed', () => {
		expect( isNightModeGadgetEnabled() ).toBeFalsy();
	} );

	it( 'should return false if the gadgets are installed but not enabled', () => {
		// https://github.com/wikimedia/mw-node-qunit/pull/38
		mw.loader.getState = () => 'registered';

		expect( isNightModeGadgetEnabled() ).toBeFalsy();
	} );

	it( 'should return true if the gadgets are enabled', () => {
		// https://github.com/wikimedia/mw-node-qunit/pull/38
		mw.loader.getState = () => 'ready';

		expect( isNightModeGadgetEnabled() ).toBeTruthy();
	} );
} );

describe( 'disableNightModeForGadget', () => {
	beforeEach( () => {
		document.documentElement.classList.remove( 'skin-theme-clientpref--excluded' );
		document.documentElement.classList.remove( 'skin-theme-clientpref-night' );
		document.documentElement.classList.remove( 'skin-theme-clientpref-os' );
	} );

	it( 'should disable night mode', () => {
		document.documentElement.classList.add( 'skin-theme-clientpref-night' );

		disableNightModeForGadget();

		expect( document.documentElement.classList.contains( 'skin-theme-clientpref-night' ) ).toBeFalsy();
	} );

	it( 'should disable automatic mode', () => {
		document.documentElement.classList.add( 'skin-theme-clientpref-os' );

		disableNightModeForGadget();

		expect( document.documentElement.classList.contains( 'skin-theme-clientpref-os' ) ).toBeFalsy();
	} );

	it( 'should add the excluded class', () => {
		disableNightModeForGadget();

		expect( document.documentElement.classList.contains( 'skin-theme-clientpref--excluded' ) ).toBeTruthy();
	} );
} );

describe( 'alterDisableLink', () => {
	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'should exit early if the gadget names are empty', () => {
		jest.spyOn( mw, 'msg' ).mockImplementation( () => '' );

		const p = document.createElement( 'p' );
		const a = document.createElement( 'a' );
		p.appendChild( a );

		a.href = 'https://test.com/';
		a.title = 'test';

		alterDisableLink( p );

		expect( a.href ).toBe( 'https://test.com/' );
		expect( a.title ).toBe( 'test' );
	} );

	it( 'should leave the surrounding element unaltered', () => {
		const p = document.createElement( 'p' );
		const a = document.createElement( 'a' );
		p.appendChild( a );

		p.textContent = 'test';

		alterDisableLink( p );

		expect( p.textContent ).toBe( 'test' );
	} );

	it( 'should strip the title and href attributes', () => {
		const p = document.createElement( 'p' );
		const a = document.createElement( 'a' );
		p.appendChild( a );

		a.href = 'test.com';
		a.title = 'test';

		alterDisableLink( p );

		expect( a.href ).toBe( '' );
		expect( a.title ).toBe( '' );
	} );

	it( 'should make the link display inline', () => {
		const p = document.createElement( 'p' );
		const a = document.createElement( 'a' );
		p.appendChild( a );

		alterDisableLink( p );

		expect( a.style.display ).toBe( 'inline' );
	} );

	// actual click test to be added after https://github.com/wikimedia/mw-node-qunit/pull/39
} );

describe( 'alterExclusionMessage', () => {
	beforeEach( () => {
		jest.spyOn( mw.loader, 'using' ).mockImplementation( () => ( {
			then: ( fn ) => fn()
		} ) );

		// https://github.com/wikimedia/mw-node-qunit/pull/40
		jest.spyOn( mw, 'message' ).mockImplementation( () => ( {
			parseDom: () => ( {
				appendTo: () => {}
			} )
		} ) );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'should remove the existing text from the notice', () => {
		const div = document.createElement( 'div' );
		const p = document.createElement( 'p' );
		document.documentElement.appendChild( div );
		div.appendChild( p );

		div.id = 'skin-client-prefs-skin-theme';
		p.className = 'exclusion-notice';
		p.textContent = 'test';

		alterExclusionMessage();

		expect( p.textContent ).toBe( '' );
	} );

	it( 'should not target other client prefs', () => {
		const div = document.createElement( 'div' );
		const p = document.createElement( 'p' );
		document.documentElement.appendChild( div );
		div.appendChild( p );

		div.id = 'skin-client-prefs-vector-feature-custom-font-size';
		p.className = 'exclusion-notice';
		p.textContent = 'test';

		alterExclusionMessage();

		expect( p.textContent ).toBe( 'test' );
	} );
} );
