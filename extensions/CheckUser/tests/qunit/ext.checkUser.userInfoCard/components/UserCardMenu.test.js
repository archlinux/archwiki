'use strict';

const { shallowMount } = require( 'vue-test-utils' );
const UserCardMenu = require( 'ext.checkUser.userInfoCard/modules/ext.checkUser.userInfoCard/components/UserCardMenu.vue' );

QUnit.module( 'ext.checkUser.userInfoCard.UserCardMenu', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;

		// Stub mw.msg
		this.sandbox.stub( mw, 'msg' ).callsFake( ( key, ...args ) => {
			let returnValue = '(' + key;
			if ( args.length !== 0 ) {
				returnValue += ': ' + args.join( ', ' );
			}
			return returnValue + ')';
		} );

		// Stub mw.Title.makeTitle
		this.sandbox.stub( mw.Title, 'makeTitle' ).callsFake( ( namespace, title ) => ( {
			getUrl: ( query ) => {
				let url = `/${ namespace }/${ title }`;
				if ( query ) {
					const params = Object.entries( query )
						.map( ( [ key, value ] ) => `${ key }=${ value }` )
						.join( '&' );
					url += `?${ params }`;
				}
				return url;
			},
			getPrefixedText: () => {
				const nsText = namespace === 0 ? '' : `Namespace${ namespace }:`;
				return `${ nsText }${ title }`;
			}
		} ) );

		// Force permission configs
		mw.config.set( 'wgCheckUserCanPerformCheckUser', true );
		mw.config.set( 'wgCheckUserCanBlock', true );
	},
	afterEach: function () {
		this.server.restore();
	}
} ) );

// Reusable mount helper
function mountComponent( props = {} ) {
	return shallowMount( UserCardMenu, {
		propsData: {
			userId: '123',
			username: 'TestUser',
			gender: 'female',
			specialCentralAuthUrl: 'https://example.com/wiki/Special:CentralAuth/TestUser',
			...props
		}
	} );
}

QUnit.test( 'renders correctly with default props', ( assert ) => {
	const wrapper = mountComponent();

	assert.true( wrapper.exists(), 'Component renders' );
} );

QUnit.test( 'computes menu items correctly with all permissions', ( assert ) => {
	const wrapper = mountComponent();
	const menuItems = wrapper.vm.menuItems;

	assert.strictEqual( menuItems.length, 7, 'Menu has 7 items with all permissions' );

	assert.strictEqual(
		menuItems[ 0 ].value,
		'view-contributions',
		'First item is view-contributions'
	);
	assert.strictEqual(
		menuItems[ 0 ].label,
		'(checkuser-userinfocard-menu-view-contributions: female)',
		'Contributions label is correct'
	);
	assert.strictEqual(
		menuItems[ 0 ].url,
		'/-1/Contributions/TestUser',
		'Contributions link is correct'
	);

	assert.strictEqual(
		menuItems[ 1 ].value,
		'view-global-account',
		'Second item is view-global-account'
	);
	assert.strictEqual(
		menuItems[ 1 ].label,
		'(checkuser-userinfocard-menu-view-global-account: female)',
		'Global account label is correct'
	);
	assert.strictEqual(
		menuItems[ 1 ].url,
		'https://example.com/wiki/Special:CentralAuth/TestUser',
		'Global account link is correct'
	);

	assert.strictEqual( menuItems[ 2 ].value, 'toggle-watchlist', 'Third item is toggle-watchlist' );
	assert.strictEqual(
		menuItems[ 2 ].label,
		'(checkuser-userinfocard-menu-add-to-watchlist: female)',
		'Watchlist label is correct'
	);

	assert.strictEqual( menuItems[ 3 ].value, 'check-ip', 'Fourth item is check-ip' );
	assert.strictEqual(
		menuItems[ 3 ].label,
		'(checkuser-userinfocard-menu-check-ip: female)',
		'Check IP label is correct'
	);
	assert.strictEqual(
		menuItems[ 3 ].url,
		'/-1/CheckUser?user=TestUser',
		'Check IP link is correct'
	);

	assert.strictEqual( menuItems[ 4 ].value, 'block-user', 'Fifth item is block-user' );
	assert.strictEqual(
		menuItems[ 4 ].label,
		'(checkuser-userinfocard-menu-block-user: female)',
		'Block user label is correct'
	);
	assert.strictEqual( menuItems[ 4 ].url, '/-1/Block/TestUser', 'Block user link is correct' );

	assert.strictEqual( menuItems[ 5 ].value, 'provide-feedback', 'Sixth item is provide-feedback' );
	assert.strictEqual(
		menuItems[ 5 ].label,
		'(checkuser-userinfocard-menu-provide-feedback)',
		'Provide feedback label is correct'
	);
	assert.strictEqual(
		menuItems[ 5 ].url,
		'https://www.mediawiki.org/wiki/Talk:Trust_and_Safety_Product/Anti-abuse_signals/User_Info',
		'Provide feedback link is correct'
	);

	assert.strictEqual( menuItems[ 6 ].value, 'turn-off', 'Seventh item is turn-off' );
	assert.strictEqual(
		menuItems[ 6 ].label,
		'(checkuser-userinfocard-menu-turn-off)',
		'Turn off label is correct'
	);
	assert.strictEqual(
		menuItems[ 6 ].url,
		'/-1/Preferences#mw-prefsection-rendering-advancedrendering',
		'Turn off link is correct'
	);
} );

QUnit.test( 'computes menu items correctly with no permissions', ( assert ) => {
	// Mock permission configs - default to false for tests
	mw.config.set( 'wgCheckUserCanPerformCheckUser', false );
	mw.config.set( 'wgCheckUserCanBlock', false );
	const wrapper = mountComponent();
	const menuItems = wrapper.vm.menuItems;

	assert.strictEqual( menuItems.length, 5, 'Menu has 5 items with no permissions' );

	// Check that the basic items are still there
	assert.strictEqual( menuItems[ 0 ].value, 'view-contributions', 'First item is view-contributions' );
	assert.strictEqual( menuItems[ 1 ].value, 'view-global-account', 'Second item is view-global-account' );
	assert.strictEqual( menuItems[ 2 ].value, 'toggle-watchlist', 'Third item is toggle-watchlist' );
	assert.strictEqual( menuItems[ 3 ].value, 'provide-feedback', 'Fourth item is provide-feedback' );
	assert.strictEqual( menuItems[ 4 ].value, 'turn-off', 'Fifth item is turn-off' );

	// Check that permission-based items are not present
	const checkIpItem = menuItems.find( ( item ) => item.value === 'check-ip' );
	assert.strictEqual( checkIpItem, undefined, 'check-ip item is not present when permission is not granted' );

	const blockUserItem = menuItems.find( ( item ) => item.value === 'block-user' );
	assert.strictEqual( blockUserItem, undefined, 'block-user item is not present when permission is not granted' );
} );

QUnit.test( 'computes menu items correctly with only check-ip permission', ( assert ) => {
	// Mock permission configs - default to true for tests
	mw.config.set( 'wgCheckUserCanPerformCheckUser', true );
	mw.config.set( 'wgCheckUserCanBlock', false );
	const wrapper = mountComponent();
	const menuItems = wrapper.vm.menuItems;

	assert.strictEqual( menuItems.length, 6, 'Menu has 6 items with only check-ip permission' );

	// Check that the check-ip item is present
	const checkIpItem = menuItems.find( ( item ) => item.value === 'check-ip' );
	assert.notStrictEqual( checkIpItem, undefined, 'check-ip item is present when permission is granted' );

	// Check that the block-user item is not present
	const blockUserItem = menuItems.find( ( item ) => item.value === 'block-user' );
	assert.strictEqual( blockUserItem, undefined, 'block-user item is not present when permission is not granted' );
} );

QUnit.test( 'computes menu items correctly with only block-user permission', ( assert ) => {
	// Mock permission configs - default to true for tests
	mw.config.set( 'wgCheckUserCanPerformCheckUser', false );
	mw.config.set( 'wgCheckUserCanBlock', true );
	const wrapper = mountComponent();
	const menuItems = wrapper.vm.menuItems;

	assert.strictEqual( menuItems.length, 6, 'Menu has 6 items with only block-user permission' );

	// Check that the check-ip item is not present
	const checkIpItem = menuItems.find( ( item ) => item.value === 'check-ip' );
	assert.strictEqual( checkIpItem, undefined, 'check-ip item is not present when permission is not granted' );

	// Check that the block-user item is present
	const blockUserItem = menuItems.find( ( item ) => item.value === 'block-user' );
	assert.notStrictEqual( blockUserItem, undefined, 'block-user item is present when permission is granted' );
} );

QUnit.test( 'watchlist label changes based on initial state', ( assert ) => {
	const wrapper = mountComponent( { userPageWatched: true, gender: 'male' } );
	const menuItems = wrapper.vm.menuItems;

	assert.strictEqual(
		menuItems[ 2 ].label,
		'(checkuser-userinfocard-menu-remove-from-watchlist: male)',
		'Watchlist label is correct'
	);
} );

QUnit.test( 'shows XTools link when configured', ( assert ) => {
	mw.config.set( 'wgCheckUserUserInfoCardShowXToolsLink', true );
	mw.config.set( 'wgDBname', 'enwiki' );
	const wrapper = mountComponent( { username: 'TestUser' } );
	const menuItems = wrapper.vm.menuItems;

	const xToolsItem = menuItems.find( ( item ) => item.value === 'view-xtools' );
	assert.notStrictEqual( xToolsItem, undefined, 'view-xtools item is present' );
	assert.strictEqual(
		xToolsItem.url,
		'https://xtools.wmcloud.org/ec/enwiki/TestUser',
		'XTools link is correct'
	);
} );

QUnit.test( 'hides XTools link when not configured', ( assert ) => {
	mw.config.set( 'wgCheckUserUserInfoCardShowXToolsLink', false );
	const wrapper = mountComponent();
	const menuItems = wrapper.vm.menuItems;

	const xToolsItem = menuItems.find( ( item ) => item.value === 'view-xtools' );
	assert.strictEqual( xToolsItem, undefined, 'view-xtools item is not present' );
} );

// TODO: T386440 - Fix the test and remove the skip
// This test fails when running in conjunction with the other test components in this folder.
// When running this test file alone, this test is passing.
QUnit.test.skip( 'logs an event when onMenuSelect is called', function ( assert ) {
	mw.config.set( 'CheckUserEnableUserInfoCardInstrumentation', true );
	this.sandbox.stub( mw.user, 'sessionId' ).returns( 'test-session-id' );
	this.sandbox.stub( mw.user, 'getId' ).returns( 123 );
	const submitInteractionStub = this.sandbox.stub();
	submitInteractionStub.respondImmediately = true;
	const instrumentStub = { submitInteraction: submitInteractionStub };
	this.sandbox.stub( mw.eventLog, 'newInstrument' ).returns( instrumentStub );

	const wrapper = mountComponent();
	// Call with a fake item so it doesn't trigger window navigation
	wrapper.vm.onMenuSelect( 'a-fake-item' );

	assert.strictEqual( submitInteractionStub.callCount, 1, 'submitInteraction is called once' );
	assert.strictEqual(
		submitInteractionStub.firstCall.args[ 0 ],
		'link_click',
		'First argument is "link_click"'
	);

	const interactionData = submitInteractionStub.firstCall.args[ 1 ];
	assert.strictEqual(
		interactionData.funnel_entry_token,
		'test-session-id',
		'Includes session token in interaction data'
	);
	assert.strictEqual(
		interactionData.action_subtype,
		'a-fake-item',
		'Includes correct subType in interaction data'
	);
	assert.strictEqual(
		interactionData.action_source,
		'card_menu',
		'Includes correct source in interaction data'
	);
} );
