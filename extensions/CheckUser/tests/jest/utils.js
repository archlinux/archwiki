'use strict';

/**
 * Mocks mw.Api().saveOption() and returns a jest.fn()
 * that is used as the saveOption() method. This can
 * be used to expect that the saveOption() method is
 * called with the correct arguments.
 *
 * @param {boolean} shouldBeSuccessful If the call to mw.Api().saveOption() should be a success
 * @param {Object} [mockResponse] Optional. The response from the API.
 * @param {string} [mockErrorCode] Optional. If the request is mocked to fail, this defines
 *   the error code returned to the reject handler.
 * @return {jest.fn}
 */
function mockApiSaveOption( shouldBeSuccessful, mockResponse, mockErrorCode ) {
	const apiSaveOption = jest.fn();
	if ( shouldBeSuccessful ) {
		apiSaveOption.mockResolvedValue( mockResponse || { options: 'success' } );
	} else {
		// Create a mock that acts like $.Deferred(), which means that the reject
		// handler can be passed two arguments.
		apiSaveOption.mockReturnValue( {
			then: ( _, rejectHandler ) => rejectHandler(
				mockErrorCode || 'errorcode', mockResponse || { error: { info: 'Error' } }
			)
		} );
	}
	jest.spyOn( mw, 'Api' ).mockImplementation( () => ( {
		saveOption: apiSaveOption
	} ) );
	return apiSaveOption;
}

/**
 * Mocks mw.Api().saveOptions() and returns a jest.fn()
 * that is used as the saveOptions() method. This can
 * be used to expect that the saveOptions() method is
 * called with the correct arguments.
 *
 * @param {boolean} shouldBeSuccessful If the call to mw.Api().saveOptions() should be a success
 * @param {Object} [mockResponse] Optional. The response from the API.
 * @param {string} [mockErrorCode] Optional. If the request is mocked to fail, this defines
 *   the error code returned to the reject handler.
 * @return {jest.fn}
 */
function mockApiSaveOptions( shouldBeSuccessful, mockResponse, mockErrorCode ) {
	const apiSaveOptions = jest.fn();
	if ( shouldBeSuccessful ) {
		apiSaveOptions.mockResolvedValue( mockResponse || { options: 'success' } );
	} else {
		// Create a mock that acts like $.Deferred(), which means that the reject
		// handler can be passed two arguments.
		apiSaveOptions.mockReturnValue( {
			then: ( _, rejectHandler ) => rejectHandler(
				mockErrorCode || 'errorcode', mockResponse || { error: { info: 'Error' } }
			)
		} );
	}
	jest.spyOn( mw, 'Api' ).mockImplementation( () => ( {
		saveOptions: apiSaveOptions
	} ) );
	return apiSaveOptions;
}

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

/**
 * Mocks mw.config.get to return the values for the specified config and throw if
 * a configuration value not provided is asked for by the code under test.
 *
 * @param {Object.<string, any>} mockConfigValues
 */
function mockJSConfig( mockConfigValues ) {
	jest.spyOn( mw.config, 'get' ).mockImplementation( ( actualConfigName ) => {
		if ( actualConfigName in mockConfigValues ) {
			return mockConfigValues[ actualConfigName ];
		} else {
			throw new Error( 'Did not expect a call to get the value of ' + actualConfigName );
		}
	} );
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

/**
 * Mocks mw.storage.session.get to return a specific value when asked for a given key.
 *
 * @param {string} key The storage key that is expected to be provided
 * @param {false|'checked'|''|null} value null when no value was set, false when storage is not
 *   available, empty string when the preference was not checked, string 'checked' when the
 *   preference was checked.
 */
function mockStorageSessionGetValue( key, value ) {
	jest.spyOn( mw.storage.session, 'get' ).mockImplementation( ( actualStorageKey ) => {
		if ( actualStorageKey === key ) {
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
 * Mocks mw.storage.session.get to return a specific value when asked for a given key
 * and supports multiple key:value pairs.
 *
 * @param {Object} values An object of storage key:storage value pairs. Values should
 *                        only be false|null|'checked'|''
 */
function mockStorageSessionGetValues( values ) {
	jest.spyOn( mw.storage.session, 'get' ).mockImplementation( ( actualKey ) => {
		if ( values[ actualKey ] !== undefined ) {
			return values[ actualKey ];
		}
		throw new Error(
			'mockStorageSessionGetValues: Did not expect a call to get the value of ' + actualKey +
			' for mw.storage.session.get'
		);
	} );
}

/**
 * Returns the button element which is wrapped in an element that
 * has the class 'ext-checkuser-temp-account-onboarding-dialog-save-preference'.
 *
 * Used to test the IPInfo and IP reveal steps for the onboarding dialog.
 *
 * @param {*} rootElement The element to search through to find the button
 * @param {boolean} globalPreferencesInstalled If GlobalPreferences is installed, as
 *   determined by the value of mocked JS config
 *   wgCheckUserGlobalPreferencesExtensionLoaded
 * @return {*} The IP reveal "Save preference" button
 */
function getSaveGlobalPreferenceButton( rootElement, globalPreferencesInstalled ) {
	const saveGlobalPreferenceField = rootElement.find(
		'.ext-checkuser-temp-account-onboarding-dialog-save-preference'
	);
	expect( saveGlobalPreferenceField.exists() ).toEqual( true );
	if ( globalPreferencesInstalled ) {
		expect( saveGlobalPreferenceField.text() ).toContain(
			'(checkuser-temporary-accounts-onboarding-dialog-save-global-preference)'
		);
	} else {
		expect( saveGlobalPreferenceField.text() ).toContain(
			'(checkuser-temporary-accounts-onboarding-dialog-save-preference)'
		);
	}
	const saveGlobalPreferenceButton = saveGlobalPreferenceField.find( 'button' );
	expect( saveGlobalPreferenceButton.exists() ).toEqual( true );
	return saveGlobalPreferenceButton;
}

/**
 * Mocks mediawiki.String so that require calls work.
 * Returns a jest.fn() for the byteLength function.
 *
 * @return {jest.fn}
 */
function mockByteLength() {
	const byteLength = jest.fn();
	jest.mock( 'mediawiki.String', () => ( {
		byteLength: byteLength
	} ), { virtual: true } );
	return byteLength;
}

module.exports = {
	mockApiSaveOption,
	mockApiSaveOptions,
	mockByteLength,
	waitFor,
	mockJSConfig,
	waitForAndExpectTextToExistInElement,
	mockStorageSessionGetValue,
	mockStorageSessionGetValues,
	getSaveGlobalPreferenceButton
};
