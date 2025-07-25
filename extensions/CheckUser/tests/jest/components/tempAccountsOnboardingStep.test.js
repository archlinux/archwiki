'use strict';

const TempAccountsOnboardingStep = require( '../../../modules/ext.checkUser.tempAccountsOnboarding/components/TempAccountsOnboardingStep.vue' ),
	utils = require( '@vue/test-utils' );

const renderComponent = ( props, slots ) => utils.mount( TempAccountsOnboardingStep, {
	props: Object.assign( {}, props ),
	slots: Object.assign( {}, slots )
} );

describe( 'Temporary Accounts step component', () => {
	it( 'renders correctly', () => {
		const wrapper = renderComponent(
			{ stepName: 'testing-step', imageAriaLabel: 'Aria label for image' },
			{ title: 'Testing title', content: 'Testing content' }
		);
		expect( wrapper.exists() ).toEqual( true );

		// Check the root element exists
		const rootElement = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog-step'
		);
		expect( rootElement.exists() ).toEqual( true );

		// Check that the image element is present and has the necessary
		// class for the image to be placed there.
		const imageElement = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-image'
		);
		expect( imageElement.classes() ).toContain(
			'ext-checkuser-image-temp-accounts-onboarding-testing-step'
		);

		// Expect that the main body is present, and contains the title and content
		const mainBodyElement = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-main-body'
		);
		expect( mainBodyElement.exists() ).toEqual( true );
		const titleElement = mainBodyElement.find( 'h5' );
		expect( titleElement.exists() ).toEqual( true );
		expect( titleElement.text() ).toEqual( 'Testing title' );
		const contentElement = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-content'
		);
		expect( contentElement.exists() ).toEqual( true );
		expect( contentElement.text() ).toEqual( 'Testing content' );
	} );
} );
