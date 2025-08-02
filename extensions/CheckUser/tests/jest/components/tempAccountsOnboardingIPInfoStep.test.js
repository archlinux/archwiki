'use strict';

const TempAccountsOnboardingIPInfoStep = require( '../../../modules/ext.checkUser.tempAccountsOnboarding/components/TempAccountsOnboardingIPInfoStep.vue' ),
	utils = require( '@vue/test-utils' ),
	{ mockApiSaveOption, waitFor } = require( '../utils.js' );

const renderComponent = () => utils.mount( TempAccountsOnboardingIPInfoStep );

/**
 * Mocks mw.user.options.get to mock the value of the
 * ipinfo-use-agreement preference.
 *
 * @param {string|0} ipInfoPreferenceValue Value of the ipinfo-use-agreement
 *    preference for the test
 */
function mockUserOptions( ipInfoPreferenceValue ) {
	jest.spyOn( mw.user.options, 'get' ).mockImplementation( ( actualPreferenceName ) => {
		if ( actualPreferenceName === 'ipinfo-use-agreement' ) {
			return ipInfoPreferenceValue;
		} else {
			throw new Error(
				'Did not expect a call to get the value of ' + actualPreferenceName +
					' for mw.user.options.get'
			);
		}
	} );
}

/**
 * Mocks mw.storage.session.get to return a specific value when asked for
 * the 'mw-checkuser-ipinfo-preference-checked-status' key.
 *
 * @param {false|'checked'|''|null} value null when no value was set, false when storage is not
 *   available, empty string when the preference was not checked, string 'checked' when the
 *   preference was checked.
 */
function mockIPInfoPreferenceCheckedSessionStorageValue( value ) {
	jest.spyOn( mw.storage.session, 'get' ).mockImplementation( ( actualStorageKey ) => {
		if ( actualStorageKey === 'mw-checkuser-ipinfo-preference-checked-status' ) {
			return value;
		} else {
			throw new Error(
				'Did not expect a call to get the value of ' + actualStorageKey +
					' for mw.storage.session.get'
			);
		}
	} );
}

/**
 * Mocks mw.config.get to mock the value of the wgCheckUserUserHasIPInfoRight JS config.
 *
 * @param {boolean} userHasIPInfoRight
 */
function mockJSConfig( userHasIPInfoRight ) {
	jest.spyOn( mw.config, 'get' ).mockImplementation( ( actualConfigName ) => {
		if ( actualConfigName === 'wgCheckUserUserHasIPInfoRight' ) {
			return userHasIPInfoRight;
		} else {
			throw new Error( 'Did not expect a call to get the value of ' + actualConfigName );
		}
	} );
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
		'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference'
	);
	expect( ipInfoPreference.exists() ).toEqual( true );
	expect( ipInfoPreference.text() ).toContain(
		'(ipinfo-preference-use-agreement)'
	);
	const ipInfoPreferenceCheckbox = ipInfoPreference.find( 'input[type="checkbox"]' );
	expect( ipInfoPreferenceCheckbox.exists() ).toEqual( true );
	return ipInfoPreferenceCheckbox;
}

/**
 * Gets the IPInfo "Save preference" button
 * after checking that it exists.
 *
 * @param {*} rootElement The root element for the IPInfo step
 * @return {*} The IPInfo "Save preference" button
 */
function getIPInfoSavePreferenceButton( rootElement ) {
	const ipInfoSavePreference = rootElement.find(
		'.ext-checkuser-temp-account-onboarding-dialog-ip-info-save-preference'
	);
	expect( ipInfoSavePreference.exists() ).toEqual( true );
	const ipInfoSavePreferenceButton = ipInfoSavePreference.find( 'button' );
	expect( ipInfoSavePreferenceButton.exists() ).toEqual( true );
	return ipInfoSavePreferenceButton;
}

/**
 * Expect that the given element contains the given text, or if
 * this is not the case then wait for this to be the case.
 *
 * @param {*} element The element that should contain the text
 * @param {string} text The text to search for
 */
async function waitForAndExpectTextToExistInElement( element, text ) {
	await waitFor( () => element.text().includes( text ) );
	expect( element.text() ).toContain( text );
}

describe( 'IPInfo step temporary accounts onboarding dialog', () => {

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'Renders correctly for when IPInfo preference was already checked', () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( null );
		mockUserOptions( '1' );
		mockJSConfig( true );

		const { rootElement } = commonTestRendersCorrectly();

		// Expect that the IPInfo preference is not shown if the user has already checked it.
		const ipInfoPreferenceSectionTitle = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference-title'
		);
		expect( ipInfoPreferenceSectionTitle.exists() ).toEqual( false );
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference'
		);
		expect( ipInfoPreference.exists() ).toEqual( false );
	} );

	it( 'Renders correctly when user does not have "ipinfo" right', () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( null );
		mockUserOptions( '0' );
		mockJSConfig( false );

		const { rootElement } = commonTestRendersCorrectly();

		// Expect that the IPInfo preference is not shown if the user has already checked it.
		const ipInfoPreferenceSectionTitle = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference-title'
		);
		expect( ipInfoPreferenceSectionTitle.exists() ).toEqual( false );
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference'
		);
		expect( ipInfoPreference.exists() ).toEqual( false );
	} );

	it( 'Renders correctly when IPInfo preference was checked previously via the dialog', () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( 'checked' );
		// Mock mw.user.options to say the preference is unchecked, which can happen
		// if the user had checked the preference and then moved back to this step.
		mockUserOptions( '0' );
		mockJSConfig( true );

		const { rootElement } = commonTestRendersCorrectly();

		// Expect that the IPInfo preference is not shown if the user has
		// checked it previously using the dialog
		const ipInfoPreferenceSectionTitle = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference-title'
		);
		expect( ipInfoPreferenceSectionTitle.exists() ).toEqual( false );
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference'
		);
		expect( ipInfoPreference.exists() ).toEqual( false );
	} );

	it( 'Renders correctly for when IPInfo preference is default value', () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( false );
		// Test using the integer 0, as the default value is the integer 0 for users which do
		// not have a different value for the preference.
		mockUserOptions( 0 );
		mockJSConfig( true );

		const { rootElement } = commonTestRendersCorrectly();

		// Check that the preference exists in the step and verify the structure of the preference
		// and it's title.
		const ipInfoPreferenceSectionTitle = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference-title'
		);
		expect( ipInfoPreferenceSectionTitle.exists() ).toEqual( true );
		expect( ipInfoPreferenceSectionTitle.text() ).toEqual(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-title)'
		);
		getIPInfoPreferenceCheckbox( rootElement );
		getIPInfoSavePreferenceButton( rootElement );
	} );

	it( 'Does nothing if IPInfo preference checkbox is checked but not saved', async () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( '' );
		mockUserOptions( '0' );
		mockJSConfig( true );
		const apiSaveOptionMock = mockApiSaveOption( true );

		const { rootElement } = commonTestRendersCorrectly();

		const ipInfoPreferenceCheckbox = getIPInfoPreferenceCheckbox( rootElement );

		// Check the preference, but check that no API call is made by doing that. The user
		// would need to press "Save preference" to make the change
		ipInfoPreferenceCheckbox.setChecked();
		expect( apiSaveOptionMock ).toHaveBeenCalledTimes( 0 );
	} );

	it( 'Updates IPInfo preference value after checkbox and submit pressed', async () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( '' );
		// Test using the string with 0 in it, to test in case the user_properties table has the
		// preference specifically marked as unchecked.
		mockUserOptions( '0' );
		mockJSConfig( true );
		const apiSaveOptionMock = mockApiSaveOption( true );

		const { rootElement, wrapper } = commonTestRendersCorrectly();

		const ipInfoPreferenceCheckbox = getIPInfoPreferenceCheckbox( rootElement );
		const ipInfoSavePreferenceButton = getIPInfoSavePreferenceButton( rootElement );
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference'
		);

		// Check the preference checkbox and then press the "Save preference" button
		// and check that an API call is made to set the preference.
		ipInfoPreferenceCheckbox.setChecked();
		await ipInfoSavePreferenceButton.trigger( 'click' );
		expect( apiSaveOptionMock ).toHaveBeenLastCalledWith( 'ipinfo-use-agreement', 1 );

		// Expect that the preference checkbox has a success message shown to indicate the
		// preference was updated successfully.
		await waitForAndExpectTextToExistInElement(
			ipInfoPreference, '(checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-success)'
		);

		// Check that if the preference saved, the user can move forward to another
		// step and/or close the dialog.
		expect( wrapper.vm.canMoveToAnotherStep() ).toEqual( true );
		expect( wrapper.vm.shouldWarnBeforeClosingDialog() ).toEqual( false );

		// Uncheck the preference
		ipInfoPreferenceCheckbox.setChecked( false );

		// Check that the success message disappears when the preference is unchecked
		await waitFor( () => !ipInfoPreference.text().includes( '(checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-success)' ) );
		expect( ipInfoPreference.text() ).not.toContain(
			'(checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-success)'
		);

		// Save the change to the preference and then check that this has caused
		// the API to be called.
		await ipInfoSavePreferenceButton.trigger( 'click' );

		// Expect that the preference checkbox has a success message shown to indicate the
		// preference was updated successfully.
		await waitForAndExpectTextToExistInElement(
			ipInfoPreference, '(checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-success)'
		);
		expect( apiSaveOptionMock ).toHaveBeenLastCalledWith( 'ipinfo-use-agreement', 0 );
	} );

	it( 'Prevents step move if IPInfo preference checked but not saved', async () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( '' );
		mockUserOptions( 0 );
		mockJSConfig( true );

		const { rootElement, wrapper } = commonTestRendersCorrectly();

		const ipInfoPreferenceCheckbox = getIPInfoPreferenceCheckbox( rootElement );
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference'
		);

		// Check the preference checkbox, but don't save the preference using the button
		ipInfoPreferenceCheckbox.setChecked();

		// Call the canMoveToAnotherStep() method and expect that it returns false, to
		// indicate that moving to another step is not allowed yet.
		expect( wrapper.vm.canMoveToAnotherStep() ).toEqual( false );

		// Expect that the preference checkbox has a warning in the UI indicating to the
		// user to save the preference before proceeding to the next step.
		await waitForAndExpectTextToExistInElement(
			ipInfoPreference, '(checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-warning)'
		);
	} );

	it( 'Prevents dialog close if IPInfo preference checked but not saved', async () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( '' );
		mockUserOptions( 0 );
		mockJSConfig( true );

		const { rootElement, wrapper } = commonTestRendersCorrectly();

		const ipInfoPreferenceCheckbox = getIPInfoPreferenceCheckbox( rootElement );
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference'
		);

		// Check the preference checkbox, but don't save the preference using the button
		ipInfoPreferenceCheckbox.setChecked();

		// Call the shouldWarnBeforeClosingDialog() method and expect that it returns true, to
		// indicate that closing the dialog is not allowed at the moment.
		expect( wrapper.vm.shouldWarnBeforeClosingDialog() ).toEqual( true );

		// Expect that the preference checkbox has a warning in the UI indicating to the
		// user to save the preference before closing the dialog
		await waitForAndExpectTextToExistInElement(
			ipInfoPreference, '(checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-warning)'
		);
	} );

	it( 'Displays error message if IPInfo preference check failed', async () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( '' );
		mockUserOptions( '0' );
		mockJSConfig( true );
		const apiSaveOptionMock = mockApiSaveOption(
			false, { error: { info: 'Wiki is in read only mode' } }
		);

		const { rootElement, wrapper } = commonTestRendersCorrectly();

		const ipInfoPreferenceCheckbox = getIPInfoPreferenceCheckbox( rootElement );
		const ipInfoSavePreferenceButton = getIPInfoSavePreferenceButton( rootElement );
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference'
		);

		// Turn on the preference and click the "Save preference" button
		ipInfoPreferenceCheckbox.setChecked();
		ipInfoSavePreferenceButton.trigger( 'click' );

		// Expect that an error appears indicating the preference update failed.
		await waitForAndExpectTextToExistInElement(
			ipInfoPreference,
			'(checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-error' +
				', Wiki is in read only mode)'
		);
		expect( apiSaveOptionMock ).toHaveBeenLastCalledWith( 'ipinfo-use-agreement', 1 );

		// Check that if the preference failed to save, the user can still move
		// forward to another step and/or close the dialog.
		expect( wrapper.vm.canMoveToAnotherStep() ).toEqual( true );
		expect( wrapper.vm.shouldWarnBeforeClosingDialog() ).toEqual( false );
	} );

	it( 'Only submits one preference change on race condition', async () => {
		mockIPInfoPreferenceCheckedSessionStorageValue( '' );
		mockUserOptions( '0' );
		mockJSConfig( true );
		// Mock the api.saveOption() method to only resolve when we want it to
		// so that we can test race-condition handling.
		const apiSaveOptionMock = jest.fn();
		const promisesToResolve = [];
		apiSaveOptionMock.mockReturnValue( new Promise( ( resolve ) => {
			promisesToResolve.push( resolve );
		} ) );
		jest.spyOn( mw, 'Api' ).mockImplementation( () => ( {
			saveOption: apiSaveOptionMock
		} ) );

		const { rootElement } = commonTestRendersCorrectly();

		const ipInfoPreferenceCheckbox = getIPInfoPreferenceCheckbox( rootElement );
		const ipInfoSavePreferenceButton = getIPInfoSavePreferenceButton( rootElement );
		const ipInfoPreference = rootElement.find(
			'.ext-checkuser-temp-account-onboarding-dialog-ip-info-preference'
		);

		// Turn on the preference and click the "Save preference" button a couple of times
		ipInfoPreferenceCheckbox.setChecked();
		ipInfoSavePreferenceButton.trigger( 'click' );
		ipInfoSavePreferenceButton.trigger( 'click' );
		ipInfoSavePreferenceButton.trigger( 'click' );
		ipInfoSavePreferenceButton.trigger( 'click' );

		// Expect that api.saveOption has only been called once, as the first call is still
		// not been resolved.
		expect( apiSaveOptionMock ).toHaveBeenLastCalledWith( 'ipinfo-use-agreement', 1 );
		expect( promisesToResolve ).toHaveLength( 1 );

		promisesToResolve.forEach( ( promiseResolver ) => {
			promiseResolver( { options: 'success' } );
		} );

		// Now that the promise is resolved, expect it the success message to appear.
		await waitForAndExpectTextToExistInElement(
			ipInfoPreference, '(checkuser-temporary-accounts-onboarding-dialog-ip-info-preference-success)'
		);
	} );
} );
