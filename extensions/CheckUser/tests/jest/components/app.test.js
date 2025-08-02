'use strict';

jest.mock( '../../../modules/ext.checkUser.tempAccountsOnboarding/components/icons.json', () => ( {
	cdxIconNext: '',
	cdxIconPrevious: ''
} ), { virtual: true } );

const App = require( '../../../modules/ext.checkUser.tempAccountsOnboarding/components/App.vue' ),
	utils = require( '@vue/test-utils' ),
	{ waitFor } = require( '../utils.js' );

const renderComponent = () => utils.mount( App );

/**
 * Mocks mw.config.get to mock the value of the
 * wgCheckUserIPInfoExtensionLoaded JS config.
 *
 * @param {boolean} isIPInfoEnabled
 */
function mockJSConfig( isIPInfoEnabled ) {
	jest.spyOn( mw.config, 'get' ).mockImplementation( ( actualConfigName ) => {
		if ( actualConfigName === 'wgCheckUserIPInfoExtensionLoaded' ) {
			return isIPInfoEnabled;
		} else {
			throw new Error( 'Did not expect a call to get the value of ' + actualConfigName );
		}
	} );
}

describe( 'Main app component', () => {

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'Renders correctly when IPInfo not installed', async () => {
		mockJSConfig( false );

		const wrapper = renderComponent();
		expect( wrapper.exists() ).toEqual( true );

		// Check the dialog exists and that the introduction to temporary accounts step is shown.
		const rootElement = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog'
		);
		expect( rootElement.exists() ).toEqual( true );
		const introStepImage = rootElement.find(
			'.ext-checkuser-image-temp-accounts-onboarding-temp-accounts'
		);
		expect( introStepImage.exists() ).toEqual( true );

		// Click the next button and wait for the DOM to be updated.
		const footer = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer'
		);
		const nextButton = footer.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer__navigation--next'
		);
		await nextButton.trigger( 'click' );
		await waitFor( () => !wrapper.find(
			'.ext-checkuser-image-temp-accounts-onboarding-temp-accounts'
		).exists() );

		// Expect that the IP reveal step is the second step and not the IPInfo step,
		// as the IPInfo step is removed if IPInfo is not installed.
		const ipRevealStepImage = rootElement.find(
			'.ext-checkuser-image-temp-accounts-onboarding-ip-reveal'
		);
		expect( ipRevealStepImage.exists() ).toEqual( true );
		const ipInfoStepImage = rootElement.find(
			'.ext-checkuser-image-temp-accounts-onboarding-ip-info'
		);
		expect( ipInfoStepImage.exists() ).toEqual( false );

		// Double check this by checking that the 'steps' prop only has two steps
		// (one for intro and the other for IP reveal)
		expect( wrapper.vm.steps ).toHaveLength( 2 );
	} );

	it( 'Renders correctly when IPInfo installed', async () => {
		mockJSConfig( true );

		const wrapper = renderComponent();
		expect( wrapper.exists() ).toEqual( true );

		// Check the dialog exists and that the introduction to temporary accounts step is shown.
		const rootElement = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog'
		);
		expect( rootElement.exists() ).toEqual( true );
		const introStepImage = rootElement.find(
			'.ext-checkuser-image-temp-accounts-onboarding-temp-accounts'
		);
		expect( introStepImage.exists() ).toEqual( true );

		// Click the next button and wait for the DOM to be updated.
		const footer = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer'
		);
		const nextButton = footer.find(
			'.ext-checkuser-temp-account-onboarding-dialog__footer__navigation--next'
		);
		await nextButton.trigger( 'click' );
		await waitFor( () => !wrapper.find(
			'.ext-checkuser-image-temp-accounts-onboarding-temp-accounts'
		).exists() );

		// Expect that the IPInfo step is the second step
		const ipInfoStepImage = rootElement.find(
			'.ext-checkuser-image-temp-accounts-onboarding-ip-info'
		);
		expect( ipInfoStepImage.exists() ).toEqual( true );

		// Click the next button again and wait for the DOM to be updated.
		await nextButton.trigger( 'click' );
		await waitFor( () => !wrapper.find(
			'.ext-checkuser-image-temp-accounts-onboarding-temp-accounts'
		).exists() );

		// Expect that the third step is the IP reveal step.
		const ipRevealStepImage = rootElement.find(
			'.ext-checkuser-image-temp-accounts-onboarding-ip-reveal'
		);
		expect( ipRevealStepImage.exists() ).toEqual( true );

		// Double check this by checking that the 'steps' prop has three steps.
		expect( wrapper.vm.steps ).toHaveLength( 3 );
	} );
} );
