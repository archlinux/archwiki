'use strict';

const TempAccountsOnboardingIntroStep = require( '../../../modules/ext.checkUser.tempAccountsOnboarding/components/TempAccountsOnboardingIntroStep.vue' ),
	utils = require( '@vue/test-utils' );

describe( 'First step of temporary accounts onboarding dialog', () => {
	it( 'renders correctly', () => {
		const wrapper = utils.mount( TempAccountsOnboardingIntroStep );
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
			'ext-checkuser-image-temp-accounts-onboarding-temp-accounts'
		);

		// Expect that the main body is present, and contains the title and content
		const mainBodyElement = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-main-body'
		);
		expect( mainBodyElement.exists() ).toEqual( true );
		const titleElement = mainBodyElement.find( 'h5' );
		expect( titleElement.exists() ).toEqual( true );
		expect( titleElement.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-temp-accounts-step-title)'
		);
		const contentElement = mainBodyElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-content'
		);
		expect( contentElement.exists() ).toEqual( true );
		expect( contentElement.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-temp-accounts-step-content)'
		);
	} );
} );
