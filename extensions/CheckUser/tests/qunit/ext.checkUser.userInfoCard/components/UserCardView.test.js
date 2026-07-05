'use strict';

const { nextTick } = require( 'vue' );
const { mount } = require( 'vue-test-utils' );
const UserCardView = require( 'ext.checkUser.userInfoCard/components/UserCardView.vue' );

// Using mocks since we don't need to fully load child components
const mockComponents = {
	UserCardLoadingView: {
		name: 'UserCardLoadingView',
		template: '<div class="mock-loading-view"></div>'
	},
	UserInfoCardError: {
		name: 'UserInfoCardError',
		template: '<div class="mock-error-view">{{ message }}</div>',
		props: [ 'message' ]
	},
	UserCardHeader: {
		name: 'UserCardHeader',
		template: '<div class="mock-header">{{ username }}</div>',
		props: [ 'username', 'userPageUrl', 'userPageIsKnown', 'userId', 'userPageWatched' ],
		emits: [ 'close' ]
	},
	UserCardBody: {
		name: 'UserCardBody',
		template: '<div class="mock-body">{{ username }}</div>',
		props: [
			'userId', 'username', 'gender',
			'joinedDate', 'joinedRelative', 'isRegisteredWithUnknownTime', 'globalEdits',
			'thanksReceived', 'thanksSent', 'activeBlocks', 'pastBlocks',
			'localEdits', 'localEditsReverted', 'newArticles', 'checks',
			'lastChecked', 'activeWikis', 'recentLocalEdits', 'totalLocalEdits',
			'ipRevealCount', 'hasIpRevealInfo', 'suggestedInvestigationsCaseCount'
		]
	}
};

// Sample user data for testing
const sampleUserData = {
	name: 'TestUser',
	gender: 'female',
	firstRegistration: '20200101000000',
	globalEditCount: 1000,
	thanksReceived: 30,
	thanksGiven: 15,
	userPageIsKnown: true,
	userPageWatched: true,
	editCountByDay: [
		{ date: '20250101', count: 5 },
		{ date: '20250102', count: 3 },
		{ date: '20250103', count: 7 }
	],
	activeBlocksCount: 2,
	pastBlocksCount: 3,
	localEditCount: 500,
	localEditRevertedCount: 10,
	newArticlesCount: 20,
	checksCount: 5,
	lastCheckedDate: '2024-12-31',
	activeWikis: {
		enwiki: 'https://en.wikipedia.org',
		dewiki: 'https://de.wikipedia.org'
	},
	suggestedInvestigationsCaseCount: 3
};

let server;

QUnit.module( 'ext.checkUser.userInfoCard.UserCardView', QUnit.newMwEnvironment( {
	beforeEach: function () {
		this.server = this.sandbox.useFakeServer();
		this.server.respondImmediately = true;
		server = this.server;

		this.sandbox.stub( mw, 'msg' ).callsFake( ( key ) => key );
		this.sandbox.stub( mw.Title, 'makeTitle' ).callsFake( ( namespace, title ) => ( {
			getUrl: () => `/wiki/User:${ title }`,
			getPrefixedText: () => `User:${ title }`
		} ) );
		this.sandbox.stub( mw.config, 'get' ).callsFake( ( key ) => {
			switch ( key ) {
				case 'wgUserLanguage':
					return 'en';
				case 'wgNamespaceIds':
					return {
						special: -1
					};
			}
		} );
	},
	afterEach: function () {
		this.server.restore();
	}
} ) );

// Reusable mount helper
function mountComponent( props = {} ) {
	// Create container elements for the teleports
	const headerContainer = document.createElement( 'div' );
	const bodyContainer = document.createElement( 'div' );
	// document.body.appendChild( headerContainer );
	// document.body.appendChild( bodyContainer );

	return mount( UserCardView, {
		propsData: {
			username: 'TestUser',
			headerContainer,
			bodyContainer,
			...props
		},
		stubs: mockComponents,
		attachTo: bodyContainer
	} );
}

QUnit.test( 'renders loading state initially', ( assert ) => {
	const done = assert.async();
	const wrapper = mountComponent();

	// First tick: onMounted triggers, sets loading = true
	nextTick( () => {
		// Second tick: DOM updates to reflect loading=true
		nextTick( () => {
			const loadingView = wrapper.findComponent( mockComponents.UserCardLoadingView );
			assert.true( loadingView.exists(), 'Loading view is displayed initially' );

			// Other components should not be rendered
			const errorView = wrapper.findComponent( mockComponents.UserInfoCardError );
			assert.false( errorView.exists(), 'Error view is not displayed' );

			const cardView = wrapper.find( '.ext-checkuser-userinfocard-view' );
			assert.false( cardView.exists(), 'Card view is not displayed' );

			done();
		} );
	} );
} );

/**
 * Waits for the return value of a given function to be true.
 * Will wait for a maximum of 1 second for the condition to be true.
 *
 * @param {Function} conditionCheck
 */
async function waitFor( conditionCheck ) {
	let tries = 0;
	while ( !conditionCheck() && tries < 20 ) {
		tries++;
		await new Promise( ( resolve ) => {
			setTimeout( () => resolve(), 50 );
		} );
	}
}

// forcing explicit `function` to add `this` context
QUnit.test( 'renders error state when API call fails', ( assert ) => {
	let userInfoCardApiCalled = false;
	server.respond( ( request ) => {
		if ( request.url.endsWith( '/checkuser/v0/userinfo?uselang=en' ) ) {
			request.respond(
				400, { 'Content-Type': 'application/json' },
				JSON.stringify( {
					messageTranslations: {
						en: 'Mocked error message'
					}
				} )
			);
			userInfoCardApiCalled = true;
		}
	} );

	const wrapper = mountComponent();

	return waitFor( () => {
		const loadingView = wrapper.findComponent( mockComponents.UserCardLoadingView );
		return userInfoCardApiCalled && !loadingView.exists();
	} ).then( () => {
		const loadingView = wrapper.findComponent( mockComponents.UserCardLoadingView );
		assert.false( loadingView.exists(), 'Loading view is not displayed after error' );

		const errorView = wrapper.findComponent( mockComponents.UserInfoCardError );
		assert.true( errorView.exists(), 'Error view is displayed' );
		assert.strictEqual(
			errorView.props( 'message' ),
			'Mocked error message',
			'Error message is passed correctly'
		);

		const cardView = wrapper.find( '.ext-checkuser-userinfocard-view' );
		assert.false( cardView.exists(), 'Card view is not displayed' );
	} );
} );

QUnit.test( 'renders card view when API call succeeds', ( assert ) => {
	let userInfoCardApiCalled = false;
	server.respond( ( request ) => {
		if ( request.url.endsWith( '/checkuser/v0/userinfo?uselang=en' ) ) {
			request.respond(
				200,
				{ 'Content-Type': 'application/json' },
				JSON.stringify( sampleUserData )
			);
			userInfoCardApiCalled = true;
		}
	} );

	const wrapper = mountComponent();

	return waitFor( () => {
		const loadingView = wrapper.findComponent( mockComponents.UserCardLoadingView );
		return userInfoCardApiCalled && !loadingView.exists();
	} ).then( () => {
		const loadingView = wrapper.findComponent( mockComponents.UserCardLoadingView );
		assert.false( loadingView.exists(), 'Loading view is not displayed after data is loaded' );

		const errorView = wrapper.findComponent( mockComponents.UserInfoCardError );
		assert.false( errorView.exists(), 'Error view is not displayed' );

		const headerView = wrapper.findComponent( mockComponents.UserCardHeader );
		assert.true( headerView.exists(), 'Header view is displayed' );
	} );
} );

QUnit.test( 'sets suggestedInvestigationsCaseCount from API response', ( assert ) => {
	let userInfoCardApiCalled = false;
	server.respond( ( request ) => {
		if ( request.url.endsWith( '/checkuser/v0/userinfo?uselang=en' ) ) {
			request.respond(
				200,
				{ 'Content-Type': 'application/json' },
				JSON.stringify( sampleUserData )
			);
			userInfoCardApiCalled = true;
		}
	} );

	const wrapper = mountComponent();

	return waitFor( () => {
		const loadingView = wrapper.findComponent( mockComponents.UserCardLoadingView );
		return userInfoCardApiCalled && !loadingView.exists();
	} ).then( () => {
		assert.strictEqual(
			wrapper.vm.userCard.suggestedInvestigationsCaseCount,
			3,
			'suggestedInvestigationsCaseCount is set from API response'
		);
	} );
} );

QUnit.test( 'defaults suggestedInvestigationsCaseCount to 0 when not in API response', ( assert ) => {
	let userInfoCardApiCalled = false;
	const dataWithoutSI = Object.assign( {}, sampleUserData );
	delete dataWithoutSI.suggestedInvestigationsCaseCount;
	server.respond( ( request ) => {
		if ( request.url.endsWith( '/checkuser/v0/userinfo?uselang=en' ) ) {
			request.respond(
				200,
				{ 'Content-Type': 'application/json' },
				JSON.stringify( dataWithoutSI )
			);
			userInfoCardApiCalled = true;
		}
	} );

	const wrapper = mountComponent();

	return waitFor( () => {
		const loadingView = wrapper.findComponent( mockComponents.UserCardLoadingView );
		return userInfoCardApiCalled && !loadingView.exists();
	} ).then( () => {
		assert.strictEqual(
			wrapper.vm.userCard.suggestedInvestigationsCaseCount,
			0,
			'suggestedInvestigationsCaseCount defaults to 0 when not in response'
		);
	} );
} );
