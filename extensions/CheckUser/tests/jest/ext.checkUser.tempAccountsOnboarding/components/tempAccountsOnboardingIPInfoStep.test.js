'use strict';

const TempAccountsOnboardingIPInfoStep = require( '../../../../modules/ext.checkUser.tempAccountsOnboarding/components/TempAccountsOnboardingIPInfoStep.vue' ),
	utils = require( '@vue/test-utils' ),
	{ mockApiSaveOptions, waitForAndExpectTextToExistInElement, mockJSConfig, mockStorageSessionGetValue, getSaveGlobalPreferenceButton } = require( '../../utils.js' );

const renderComponent = () => utils.mount( TempAccountsOnboardingIPInfoStep );

/**
 * Mocks mw.storage.session.get to return a specific value when asked for
 * the 'mw-checkuser-ipinfo-preference-checked-status' key.
 *
 * @param {false|'checked'|''|null} value null when no value was set, false when storage is not
 *   available, empty string when the preference was not checked, string 'checked' when the
 *   preference was checked.
 */
function mockIPInfoPreferenceCheckedSessionStorageValue( value ) {
	mockStorageSessionGetValue( 'mw-checkuser-ipinfo-preference-checked-status', value );
}

/**
 * Performs tests on the step that are the same for all
 * starting conditions.
 *
 * @return {*} The root element for the step
 */
function commonTestRendersCorrectly() {
	const wrapper = renderComponent();
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
		'ext-checkuser-image-temp-accounts-onboarding-ip-info'
	);

	// Expect that the main body is present, and contains the title and content
	const mainBodyElement = rootElement.find(
		'.ext-checkuser-temp-account-onboarding-dialog-main-body'
	);
	expect( mainBodyElement.exists() ).toEqual( true );
	const titleElement = mainBodyElement.find( 'h5' );
	expect( titleElement.exists() ).toEqual( true );
	expect( titleElement.text() ).toEqual(
		'(checkuser-temporary-accounts-onboarding-dialog-ip-info-step-title)'
	);
	const contentElement = mainBodyElement.find(
		'.ext-checkuser-temp-account-onboarding-dialog-content'
	);
	expect( contentElement.exists() ).toEqual( true );
	expect( contentElement.text() ).toContain(
		'(checkuser-temporary-accounts-onboarding-dialog-ip-info-step-content)'
	);

	return { rootElement: rootElement, wrapper: wrapper };
}

/**
 * Gets the IPInfo preference checkbox element
 * after checking that it exists.
 *
 * @param {*} rootElement The root element for the IPInfo step
 * @return {*} The IPInfo checkbox element
 */
function getIPInfoPreferenceCheckbox( rootElement ) {
	const ipInfoPreference = rootElement.find(
		'.ext-checkuser-temp-account-onboarding-dialog-preference'
	);
	expect( ipInfoPreference.exists() ).toEqual( true );
	expect( ipInfoPreference.text() ).toContain(
		'(ipinfo-preference-use-agreement)'
	);
	const ipInfoPreferenceCheckbox = ipInfoPreference.find( 'input[type="checkbox"]' );
	expect( ipInfoPreferenceCheckbox.exists() ).toEqual( true );
	return ipInfoPreferenceCheckbox;
}

describe( 'IPInfo step temporary accounts onboarding dialog', () => {

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'Renders correctly for when IPInfo preference was already checked', () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( null );
		mockJSConfig( {
			wgCheckUserUserHasIPInfoRight: true,
			wgCheckUserIPInfoPreferenceChecked: true,
			wgCheckUserGlobalPreferencesExtensionLoaded: true
		} );

		const { rootElement } = commonTestRendersCorrectly();

		// Expect that the IPInfo preference is not shown if the user has already checked it.
		const ipInfoPreferenceSectionTitle = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference-title'
		);
		expect( ipInfoPreferenceSectionTitle.exists() ).toEqual( false );
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference'
		);
		expect( ipInfoPreference.exists() ).toEqual( false );
	} );

	it( 'Renders correctly when user does not have "ipinfo" right', () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( null );
		mockJSConfig( {
			wgCheckUserUserHasIPInfoRight: false,
			wgCheckUserIPInfoPreferenceChecked: false,
			wgCheckUserGlobalPreferencesExtensionLoaded: false
		} );

		const { rootElement } = commonTestRendersCorrectly();

		// Expect that the IPInfo preference is not shown if the user has already checked it.
		const ipInfoPreferenceSectionTitle = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference-title'
		);
		expect( ipInfoPreferenceSectionTitle.exists() ).toEqual( false );
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference'
		);
		expect( ipInfoPreference.exists() ).toEqual( false );
	} );

	it( 'Renders correctly when IPInfo preference was checked previously via the dialog', () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( 'checked' );
		// Mock wgCheckUserIPInfoPreferenceChecked to say the preference is
		// unchecked, which can happen if the user had checked the preference
		// and then moved back to this step.
		mockJSConfig( {
			wgCheckUserUserHasIPInfoRight: true,
			wgCheckUserIPInfoPreferenceChecked: false,
			wgCheckUserGlobalPreferencesExtensionLoaded: false
		} );

		const { rootElement } = commonTestRendersCorrectly();

		// Expect that the IPInfo preference is not shown if the user has
		// checked it previously using the dialog
		const ipInfoPreferenceSectionTitle = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference-title'
		);
		expect( ipInfoPreferenceSectionTitle.exists() ).toEqual( false );
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference'
		);
		expect( ipInfoPreference.exists() ).toEqual( false );
	} );

	it( 'Renders correctly for when IPInfo preference is default value', () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( false );
		mockJSConfig( {
			wgCheckUserUserHasIPInfoRight: true,
			wgCheckUserIPInfoPreferenceChecked: false,
			wgCheckUserGlobalPreferencesExtensionLoaded: true
		} );

		const { rootElement } = commonTestRendersCorrectly();

		// Check that the preference exists in the step and verify the structure of the preference
		// and it's title.
		const ipInfoPreferenceSectionTitle = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference-title'
		);
		expect( ipInfoPreferenceSectionTitle.exists() ).toEqual( true );
		expect( ipInfoPreferenceSectionTitle.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-title)'
		);
		getIPInfoPreferenceCheckbox( rootElement );
		getSaveGlobalPreferenceButton( rootElement, true );
	} );

	it( 'Updates IPInfo preference value after checkbox and submit pressed', async () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( '' );
		mockJSConfig( {
			wgCheckUserUserHasIPInfoRight: true,
			wgCheckUserIPInfoPreferenceChecked: false,
			wgCheckUserGlobalPreferencesExtensionLoaded: false
		} );
		const apiSaveOptionsMock = mockApiSaveOptions( true );

		const { rootElement, wrapper } = commonTestRendersCorrectly();

		const ipInfoPreferenceCheckbox = getIPInfoPreferenceCheckbox( rootElement );
		const ipInfoSavePreferenceButton = getSaveGlobalPreferenceButton( rootElement, false );
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference'
		);

		// Check the preference checkbox and then press the "Save preference" button
		// and check that an API call is made to set the preference.
		ipInfoPreferenceCheckbox.setChecked();
		await ipInfoSavePreferenceButton.trigger( 'click' );
		expect( apiSaveOptionsMock ).toHaveBeenLastCalledWith( { 'ipinfo-use-agreement': 1 }, { global: 'create' } );

		// Expect that the preference checkbox has a success message shown to indicate the
		// preference was updated successfully.
		await waitForAndExpectTextToExistInElement(
			ipInfoPreference, '(checkuser-temporary-accounts-onboarding-dialog-preference-success)'
		);

		// Check that if the preference saved, the user can move forward to another
		// step and/or close the dialog.
		expect( wrapper.vm.canMoveToAnotherStep() ).toEqual( true );
		expect( wrapper.vm.shouldWarnBeforeClosingDialog() ).toEqual( false );
	} );

	it( 'Prevents step move and dialog close if IPInfo preference checked but not saved', async () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( '' );
		mockJSConfig( {
			wgCheckUserUserHasIPInfoRight: true,
			wgCheckUserIPInfoPreferenceChecked: false,
			wgCheckUserGlobalPreferencesExtensionLoaded: false
		} );

		const { rootElement, wrapper } = commonTestRendersCorrectly();

		const ipInfoPreferenceCheckbox = getIPInfoPreferenceCheckbox( rootElement );

		// Check the preference checkbox, but don't save the preference using the button
		ipInfoPreferenceCheckbox.setChecked();

		expect( wrapper.vm.canMoveToAnotherStep() ).toEqual( false );
		expect( wrapper.vm.shouldWarnBeforeClosingDialog() ).toEqual( true );
	} );
} );
