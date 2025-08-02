'use strict';

const TempAccountsOnboardingStepper = require( '../../../modules/ext.checkUser.tempAccountsOnboarding/components/TempAccountsOnboardingStepper.vue' ),
	utils = require( '@vue/test-utils' );

const renderComponent = ( props ) => {
	const defaultProps = { modelValue: 1, totalSteps: 1 };
	return utils.mount( TempAccountsOnboardingStepper, {
		props: Object.assign( {}, defaultProps, props )
	} );
};

describe( 'Temporary Accounts dialog stepper component', () => {
	beforeEach( () => {
		jest.spyOn( mw.language, 'convertNumber' ).mockImplementation( ( number ) => number );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'renders correctly for a total of one step', () => {
		const wrapper = renderComponent();
		expect( wrapper.exists() ).toEqual( true );

		// Check the root element exists
		const dialogStepper = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog-stepper'
		);
		expect( dialogStepper.exists() ).toEqual( true );

		// Check that one dot is present and that it has the active class
		const dialogStepperDots = dialogStepper.findAll(
			'.ext-checkuser-temp-account-onboarding-dialog-stepper__dots__dot'
		);
		expect( dialogStepperDots.length ).toEqual( 1 );
		expect( dialogStepperDots[ 0 ].classes() ).toContain(
			'ext-checkuser-temp-account-onboarding-dialog-stepper__dots__dot--active'
		);

		// Expect that the label is present and that the label indicates one
		// step out of a total of one steps
		const dialogStepperLabel = dialogStepper.find(
			'.ext-checkuser-temp-account-onboarding-dialog-stepper__label'
		);
		expect( dialogStepperLabel.exists() ).toEqual( true );
		expect( dialogStepperLabel.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-stepper-label, 1, 1)'
		);
	} );

	it( 'renders correctly for a total of three steps on step 2', () => {
		const wrapper = renderComponent( { modelValue: 2, totalSteps: 3 } );
		expect( wrapper.exists() ).toEqual( true );

		// Check the root element exists
		const dialogStepper = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog-stepper'
		);
		expect( dialogStepper.exists() ).toEqual( true );

		// Check that three dots are present
		const dialogStepperDots = dialogStepper.findAll(
			'.ext-checkuser-temp-account-onboarding-dialog-stepper__dots__dot'
		);
		expect( dialogStepperDots.length ).toEqual( 3 );

		// Check that the second dot is the one that is active and the others are not.
		const activeClass =
			'ext-checkuser-temp-account-onboarding-dialog-stepper__dots__dot--active';
		expect( dialogStepperDots[ 1 ].classes() ).toContain( activeClass );
		expect( dialogStepperDots[ 0 ].classes() ).not.toContain( activeClass );
		expect( dialogStepperDots[ 2 ].classes() ).not.toContain( activeClass );

		// Expect that the label is present and that the label indicates one
		// step out of a total of one steps
		const dialogStepperLabel = dialogStepper.find(
			'.ext-checkuser-temp-account-onboarding-dialog-stepper__label'
		);
		expect( dialogStepperLabel.exists() ).toEqual( true );
		expect( dialogStepperLabel.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-stepper-label, 2, 3)'
		);
	} );

	it( 'Should react to totalSteps change', async () => {
		const dotSelector = '.ext-checkuser-temp-account-onboarding-dialog-stepper__dots__dot';
		const wrapper = renderComponent( { modelValue: 1, totalSteps: 4 } );
		await wrapper.setProps( { totalSteps: 3 } ).then( () => {
			expect( wrapper.findAll( dotSelector ) ).toHaveLength( 3 );
		} );
	} );

	it( 'Should react to modelValue change', async () => {
		const wrapper = renderComponent( { modelValue: 1, totalSteps: 2 } );
		const activeClass =
			'ext-checkuser-temp-account-onboarding-dialog-stepper__dots__dot--active';
		let dialogStepperDots;
		dialogStepperDots = wrapper.findAll(
			'.ext-checkuser-temp-account-onboarding-dialog-stepper__dots__dot'
		);
		expect( dialogStepperDots[ 0 ].classes() ).toContain( activeClass );
		expect( dialogStepperDots[ 1 ].classes() ).not.toContain( activeClass );
		await wrapper.setProps( { modelValue: 2 } ).then( () => {
			dialogStepperDots = wrapper.findAll(
				'.ext-checkuser-temp-account-onboarding-dialog-stepper__dots__dot'
			);
			expect( dialogStepperDots[ 0 ].classes() ).not.toContain( activeClass );
			expect( dialogStepperDots[ 1 ].classes() ).toContain( activeClass );
		} );
	} );
} );
