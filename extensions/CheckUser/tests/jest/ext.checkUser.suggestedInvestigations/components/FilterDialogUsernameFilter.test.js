'use strict';

const utils = require( '@vue/test-utils' ),
	{ mockApiGet } = require( '../../utils.js' );

const FilterDialogUsernameFilter = require( '../../../../modules/ext.checkUser.suggestedInvestigations/components/FilterDialogUsernameFilter.vue' );

const renderComponent = ( props ) => utils.mount( FilterDialogUsernameFilter, {
	props: Object.assign( {}, { selectedUsernames: [] }, props )
} );

/**
 * Wait until the debounce performed by loadSuggestedUsernames
 * is complete by waiting 120ms (longer than the 100ms delay
 * in that function).
 *
 * @return {Promise}
 */
const waitUntilDebounceComplete = () => new Promise( ( resolve ) => {
	setTimeout( () => {
		resolve();
	}, 120 );
} );

/**
 * Mocks mw.log.error() and returns a jest.fn() for error()
 *
 * @return {jest.fn}
 */
function mockErrorLogger() {
	const mwLogError = jest.fn();
	mw.log.error = mwLogError;
	return mwLogError;
}

describe( 'Suggested Investigations change status dialog', () => {
	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'Should update menu config on change in window height', () => {
		const wrapper = renderComponent();

		// Set the window height to 1 to test that the minimum visibleItemLimit will be 2.
		wrapper.vm.windowHeight = 1;
		expect( wrapper.vm.menuConfig.visibleItemLimit ).toBe( 2 );

		// Set the window height to 1000 to test that the maximum visibleItemLimit is 5.
		wrapper.vm.windowHeight = 1;
		expect( wrapper.vm.menuConfig.visibleItemLimit ).toBe( 2 );

		// Set the window height to 500 to test the x / 150 calculation
		wrapper.vm.windowHeight = 500;
		// The floor division of 500 by 150 is 3.
		expect( wrapper.vm.menuConfig.visibleItemLimit ).toBe( 3 );
	} );

	it( 'Should query allusers API on inputValue update', async () => {
		const apiGet = mockApiGet(
			Promise.resolve(
				{ query: { allusers: [
					{ userid: 1, name: 'testing' },
					{ userid: 2, name: 'testing1' },
					{ userid: 3, name: 'testing2' }
				] } }
			)
		);
		const wrapper = renderComponent();

		// Update the input value
		const inputField = wrapper.find( 'input[name=filter-username]' );
		inputField.setValue( 'testing' );

		// Wait until the debounce time has expired and add around 20ms to be sure it has run.
		await waitUntilDebounceComplete();

		// The suggestions should now be set.
		expect( wrapper.vm.suggestedUsernames ).toStrictEqual( [
			{ value: 'testing' },
			{ value: 'testing1' },
			{ value: 'testing2' }
		] );
		expect( apiGet ).toHaveBeenCalledWith( {
			action: 'query',
			list: 'allusers',
			auprefix: 'testing',
			limit: '10'
		} );
	} );

	it( 'inputValue update but allusers API request errors', async () => {
		const rejectedPromise = Promise.reject( 'error' );
		// Catch the rejected promise in a function that does nothing to
		// allow the tests to run (otherwise they fail with an
		// ERR_UNHANDLED_REJECTION error).
		rejectedPromise.catch( () => {} );

		const apiGet = mockApiGet( rejectedPromise );
		const mwLogError = mockErrorLogger();

		const wrapper = renderComponent();

		// Set suggestedUsernames so that the test can verify it empties on a failed request
		wrapper.vm.suggestedUsernames.value = [ { value: 'test123123123123123' } ];

		// Update the input value
		const inputField = wrapper.find( 'input[name=filter-username]' );
		inputField.setValue( 'testing' );

		// Wait until the debounce time has expired and add around 20ms to be sure it has run
		await waitUntilDebounceComplete();

		// The suggestions should now be set
		expect( wrapper.vm.suggestedUsernames ).toStrictEqual( [] );
		expect( mwLogError ).toHaveBeenCalledWith( 'error' );
		expect( apiGet ).toHaveBeenCalledWith( {
			action: 'query',
			list: 'allusers',
			auprefix: 'testing',
			limit: '10'
		} );
	} );

	it( 'inputValue updated but allusers API returns unparsable response', async () => {
		const apiGet = mockApiGet( Promise.resolve( { test: 'test' } ) );

		const wrapper = renderComponent();

		// Set suggestedUsernames so that the test can verify it empties on a failed request
		wrapper.vm.suggestedUsernames.value = [ { value: 'test123123123123123' } ];

		// Update the input value
		const inputField = wrapper.find( 'input[name=filter-username]' );
		inputField.setValue( 'testing123' );

		// Wait until the debounce time has expired and add around 20ms to be sure it has run
		await waitUntilDebounceComplete();

		// The suggestions should now be set
		expect( wrapper.vm.suggestedUsernames ).toStrictEqual( [] );
		expect( apiGet ).toHaveBeenCalledWith( {
			action: 'query',
			list: 'allusers',
			auprefix: 'testing123',
			limit: '10'
		} );
	} );

	it( 'inputValue updated to empty string', () => {
		const wrapper = renderComponent();

		const inputField = wrapper.find( 'input[name=filter-username]' );
		inputField.setValue( '' );

		// The suggestions should be empty for an empty input
		expect( wrapper.vm.suggestedUsernames ).toStrictEqual( [] );
	} );

	it( 'inputValue updated twice within the debounce period', async () => {
		const apiGet = mockApiGet(
			Promise.resolve(
				{ query: { allusers: [
					{ userid: 1, name: 'testing123' },
					{ userid: 2, name: 'testing1234' }
				] } }
			)
		);
		const wrapper = renderComponent();

		// Update the input value twice to test debouncing
		const inputField = wrapper.find( 'input[name=filter-username]' );
		inputField.setValue( 'testing12' );
		inputField.setValue( 'testing123' );

		// Wait until the debounce time has expired and add around 20ms to be sure it has run
		await waitUntilDebounceComplete();

		// The suggestions should now be set.
		expect( wrapper.vm.suggestedUsernames ).toStrictEqual( [
			{ value: 'testing123' },
			{ value: 'testing1234' }
		] );
		expect( apiGet ).toHaveBeenCalledWith( {
			action: 'query',
			list: 'allusers',
			auprefix: 'testing123',
			limit: '10'
		} );
	} );
} );
