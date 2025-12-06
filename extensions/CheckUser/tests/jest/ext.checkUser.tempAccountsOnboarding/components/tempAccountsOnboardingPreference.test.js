'use strict';

const TempAccountsOnboardingPreference = require( '../../../../modules/ext.checkUser.tempAccountsOnboarding/components/TempAccountsOnboardingPreference.vue' ),
	utils = require( '@vue/test-utils' ),
	{ mockApiSaveOptions, waitFor, waitForAndExpectTextToExistInElement, getSaveGlobalPreferenceButton, mockJSConfig } = require( '../../utils.js' );

const renderComponent = ( props ) => {
	const defaultProps = {
		sectionTitle: '',
		checkboxes: []
	};
	return utils.mount( TempAccountsOnboardingPreference, {
		props: Object.assign( {}, defaultProps, props )
	} );
};

/**
 * Gets the preference checkbox element after checking that it exists.
 *
 * @param {*} rootElement The root element
 * @param {string} [expectedCheckboxLabel] The text expected to be used for the checkbox label
 * @return {*} The checkbox element
 */
function getPreferenceCheckbox( rootElement, expectedCheckboxLabel ) {
	const preferenceCheckboxFieldset = rootElement.find(
		'.ext-checkuser-temp-account-onboarding-dialog-preference'
	);
	expect( preferenceCheckboxFieldset.exists() ).toEqual( true );
	if ( expectedCheckboxLabel ) {
		expect( preferenceCheckboxFieldset.text() ).toContain( expectedCheckboxLabel );
	}
	const preferenceCheckbox = preferenceCheckboxFieldset.find( 'input[type="checkbox"]' );
	expect( preferenceCheckbox.exists() ).toEqual( true );
	return preferenceCheckbox;
}

/**
 * Mocks mw.storage.session and returns a jest.fn() that is used as the set() method.
 *
 * @return {jest.fn}
 */
function mockStorageSession() {
	const mockStorageSessionSet = jest.fn();
	mw.storage.session.set = mockStorageSessionSet;
	return mockStorageSessionSet;
}

describe( 'Temporary accounts preference component', () => {

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'Renders correctly when checkbox not initially checked', () => {
		mockJSConfig( { wgCheckUserGlobalPreferencesExtensionLoaded: false } );
		const wrapper = renderComponent( {
			sectionTitle: 'Test section title',
			checkboxDescriptionMessageKey: 'test-description-message-key',
			checkboxes: [
				{
					initialIsChecked: false,
					checkboxMessageKey: 'test-message-key',
					name: 'test-preference',
					setValue: {
						checked: 1,
						unchecked: 0
					}
				}
			]
		} );

		// Expect that the structure of the component is correct along with the text
		const preferenceSectionTitle = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference-title'
		);
		expect( preferenceSectionTitle.exists() ).toEqual( true );
		expect( preferenceSectionTitle.text() ).toEqual(
			'Test section title'
		);
		getPreferenceCheckbox( wrapper, '(test-message-key)' );
		getSaveGlobalPreferenceButton( wrapper, false );
	} );

	it( 'Does nothing if preference checkbox is checked but not saved', async () => {
		mockJSConfig( { wgCheckUserGlobalPreferencesExtensionLoaded: true } );
		const apiSaveOptionsMock = mockApiSaveOptions( true );
		const mockStorageSessionSet = mockStorageSession();

		const wrapper = renderComponent( { checkboxes: [ { initialIsChecked: false } ] } );

		const preferenceCheckbox = getPreferenceCheckbox( wrapper );

		// Check the preference, but check that no API call is made by doing that. The user
		// would need to press save preference button to make the change
		preferenceCheckbox.setChecked();
		expect( apiSaveOptionsMock ).toHaveBeenCalledTimes( 0, { global: 'create' } );
		expect( mockStorageSessionSet ).toHaveBeenCalledTimes( 0 );
	} );

	it( 'Updates preference value after checkbox and submit pressed', async () => {
		mockJSConfig( { wgCheckUserGlobalPreferencesExtensionLoaded: true } );
		const apiSaveOptionsMock = mockApiSaveOptions( true );
		const mockStorageSessionSet = mockStorageSession();

		const wrapper = renderComponent( {
			checkboxes: [
				{
					initialIsChecked: false,
					checkedStatusStorageKey: 'test-preference-storage-key',
					name: 'test-preference',
					setValue: {
						checked: 1,
						unchecked: 0
					}
				}
			]
		} );

		const preferenceCheckbox = getPreferenceCheckbox( wrapper );
		const savePreferenceButton = getSaveGlobalPreferenceButton( wrapper, true );
		const preferenceFieldset = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference'
		);

		// Check the preference checkbox and then press the save preference button
		// and check that an API call is made to set the preference.
		preferenceCheckbox.setChecked();
		await savePreferenceButton.trigger( 'click' );
		expect( mockStorageSessionSet ).toHaveBeenLastCalledWith( 'test-preference-storage-key', 'checked' );
		expect( apiSaveOptionsMock ).toHaveBeenLastCalledWith( { 'test-preference': 1 }, { global: 'create' } );

		// Expect that the preference checkbox has a success message shown to indicate the
		// preference was updated successfully.
		await waitForAndExpectTextToExistInElement(
			preferenceFieldset, '(checkuser-temporary-accounts-onboarding-dialog-preference-success)'
		);

		// Check that if the preference saved, the user can move forward to another
		// step and/or close the dialog.
		expect( wrapper.vm.canMoveToAnotherStep() ).toEqual( true );
		expect( wrapper.vm.shouldWarnBeforeClosingDialog() ).toEqual( false );

		// Uncheck the preference
		preferenceCheckbox.setChecked( false );

		// Check that the success message disappears when the preference is unchecked
		await waitFor( () => !preferenceFieldset.text().includes( '(checkuser-temporary-accounts-onboarding-dialog-preference-success)' ) );
		expect( preferenceFieldset.text() ).not.toContain(
			'(checkuser-temporary-accounts-onboarding-dialog-preference-success)'
		);

		// Save the change to the preference and then check that this has caused
		// the API to be called.
		await savePreferenceButton.trigger( 'click' );

		// Expect that the preference checkbox has a success message shown to indicate the
		// preference was updated successfully.
		await waitForAndExpectTextToExistInElement(
			preferenceFieldset, '(checkuser-temporary-accounts-onboarding-dialog-preference-success)'
		);
		expect( mockStorageSessionSet ).toHaveBeenLastCalledWith( 'test-preference-storage-key', '' );
		expect( apiSaveOptionsMock ).toHaveBeenLastCalledWith( { 'test-preference': 0 }, { global: 'create' } );
	} );

	it( 'Prevents step move if preference checked but not saved', async () => {
		mockJSConfig( { wgCheckUserGlobalPreferencesExtensionLoaded: true } );
		const wrapper = renderComponent( { checkboxes: [ { initialIsChecked: false } ] } );

		const preferenceCheckbox = getPreferenceCheckbox( wrapper );
		const preferenceFieldset = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference'
		);

		// Check the preference checkbox, but don't save the preference using the button
		preferenceCheckbox.setChecked();

		// Call the canMoveToAnotherStep() method and expect that it returns false, to
		// indicate that moving to another step is not allowed yet.
		expect( wrapper.vm.canMoveToAnotherStep() ).toEqual( false );

		// Expect that the preference checkbox has a warning in the UI indicating to the
		// user to save the preference before proceeding to the next step.
		await waitForAndExpectTextToExistInElement(
			preferenceFieldset,
			'(checkuser-temporary-accounts-onboarding-dialog-preference-warning, (checkuser-temporary-accounts-onboarding-dialog-save-global-preference))'
		);
	} );

	it( 'Prevents dialog close if preference checked but not saved', async () => {
		mockJSConfig( { wgCheckUserGlobalPreferencesExtensionLoaded: true } );
		const wrapper = renderComponent( { checkboxes: [ { initialIsChecked: false } ] } );

		const preferenceCheckbox = getPreferenceCheckbox( wrapper );
		const preferenceFieldset = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference'
		);

		// Check the preference checkbox, but don't save the preference using the button
		preferenceCheckbox.setChecked();

		// Call the shouldWarnBeforeClosingDialog() method and expect that it returns true, to
		// indicate that closing the dialog is not allowed at the moment.
		expect( wrapper.vm.shouldWarnBeforeClosingDialog() ).toEqual( true );

		// Expect that the preference checkbox has a warning in the UI indicating to the
		// user to save the preference before closing the dialog
		await waitForAndExpectTextToExistInElement(
			preferenceFieldset, '(checkuser-temporary-accounts-onboarding-dialog-preference-warning'
		);
	} );

	it( 'Displays error message if preference check failed', async () => {
		mockJSConfig( { wgCheckUserGlobalPreferencesExtensionLoaded: true } );
		const apiSaveOptionsMock = mockApiSaveOptions(
			false, { error: { info: 'Wiki is in read only mode' } }
		);

		const wrapper = renderComponent( {
			checkboxes: [
				{
					initialIsChecked: false,
					checkedStatusStorageKey: 'test-preference-storage-key',
					name: 'test-preference',
					setValue: {
						checked: 1,
						unchecked: 0
					}
				}
			]
		} );

		const preferenceCheckbox = getPreferenceCheckbox( wrapper );
		const savePreferenceButton = getSaveGlobalPreferenceButton( wrapper, true );
		const preferenceFieldset = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference'
		);

		// Turn on the preference and click the save preference button
		preferenceCheckbox.setChecked();
		savePreferenceButton.trigger( 'click' );

		// Expect that an error appears indicating the preference update failed.
		await waitForAndExpectTextToExistInElement(
			preferenceFieldset,
			'(checkuser-temporary-accounts-onboarding-dialog-preference-error' +
			', Wiki is in read only mode)'
		);
		expect( apiSaveOptionsMock ).toHaveBeenLastCalledWith( { 'test-preference': 1 }, { global: 'create' } );

		// Check that if the preference failed to save, the user can still move
		// forward to another step and/or close the dialog.
		expect( wrapper.vm.canMoveToAnotherStep() ).toEqual( true );
		expect( wrapper.vm.shouldWarnBeforeClosingDialog() ).toEqual( false );
	} );

	it( 'Displays error code if preference check failed and no message returned', async () => {
		mockJSConfig( { wgCheckUserGlobalPreferencesExtensionLoaded: true } );
		const apiSaveOptionsMock = mockApiSaveOptions( false, {}, 'error-code' );

		const wrapper = renderComponent( {
			checkboxes: [
				{
					initialIsChecked: false,
					checkedStatusStorageKey: 'test-preference-storage-key',
					name: 'test-preference',
					setValue: {
						checked: 1,
						unchecked: 0
					}
				}
			]
		} );

		const preferenceCheckbox = getPreferenceCheckbox( wrapper );
		const savePreferenceButton = getSaveGlobalPreferenceButton( wrapper, true );
		const preferenceFieldset = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference'
		);

		// Turn on the preference and click the save preference button
		preferenceCheckbox.setChecked();
		savePreferenceButton.trigger( 'click' );

		// Expect that an error appears indicating the preference update failed.
		await waitForAndExpectTextToExistInElement( preferenceFieldset, 'error-code' );
		expect( apiSaveOptionsMock ).toHaveBeenLastCalledWith( { 'test-preference': 1 }, { global: 'create' } );
	} );

	it( 'Only submits one preference change on race condition', async () => {
		mockJSConfig( { wgCheckUserGlobalPreferencesExtensionLoaded: true } );
		// Mock the api.saveOptions() method to only resolve when we want it to
		// so that we can test race-condition handling.
		const apiSaveOptionsMock = jest.fn();
		const promisesToResolve = [];
		apiSaveOptionsMock.mockReturnValue( new Promise( ( resolve ) => {
			promisesToResolve.push( resolve );
		} ) );
		jest.spyOn( mw, 'Api' ).mockImplementation( () => ( {
			saveOptions: apiSaveOptionsMock
		} ) );

		const wrapper = renderComponent( {
			checkboxes: [
				{
					initialIsChecked: false,
					checkedStatusStorageKey: 'test-preference-storage-key',
					name: 'test-preference',
					setValue: {
						checked: 1,
						unchecked: 0
					}
				}
			]
		} );

		const preferenceCheckbox = getPreferenceCheckbox( wrapper );
		const savePreferenceButton = getSaveGlobalPreferenceButton( wrapper, true );
		const preferenceFieldset = wrapper.find(
			'.ext-checkuser-temp-account-onboarding-dialog-preference'
		);

		// Turn on the preference and click the save preference button a couple of times
		preferenceCheckbox.setChecked();
		savePreferenceButton.trigger( 'click' );
		savePreferenceButton.trigger( 'click' );
		savePreferenceButton.trigger( 'click' );
		savePreferenceButton.trigger( 'click' );

		// Expect that api.saveOptions has only been called once, as the first call is still
		// not been resolved.
		expect( apiSaveOptionsMock ).toHaveBeenLastCalledWith( { 'test-preference': 1 }, { global: 'create' } );
		expect( promisesToResolve ).toHaveLength( 1 );

		promisesToResolve.forEach( ( promiseResolver ) => {
			promiseResolver( { options: 'success' } );
		} );

		// Now that the promise is resolved, expect it the success message to appear.
		await waitForAndExpectTextToExistInElement(
			preferenceFieldset, '(checkuser-temporary-accounts-onboarding-dialog-preference-success)'
		);
	} );
} );
