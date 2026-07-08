'use strict';

const utils = require( '@vue/test-utils' ),
	{ nextTick } = require( 'vue' ),
	{ mockJSConfig } = require( '../../utils.js' );

const mockUpdateFiltersOnPage = jest.fn();
const mockCaseStatusToChipStatus = jest.fn();
jest.mock(
	'../../../../modules/ext.checkUser.suggestedInvestigations/utils.js',
	() => ( {
		updateFiltersOnPage: mockUpdateFiltersOnPage,
		caseStatusToChipStatus: mockCaseStatusToChipStatus
	} )
);

const FilterDialog = require( '../../../../modules/ext.checkUser.suggestedInvestigations/components/FilterDialog.vue' );

const renderComponent = ( initialFilters ) => utils.mount( FilterDialog, {
	props: { initialFilters: Object.assign(
		{},
		{ status: [], username: [], hideCasesWithNoUserEdits: true, hideCasesWithNoBlockedUsers: false, signal: [] },
		initialFilters
	) }
} );

/**
 * Perform tests common to all tests of the suggested investigations filter dialog
 * and then return the dialog component
 *
 * @param {Object} [props] Passed through to {@link renderComponent}
 * @param {string[]} [props.username] Username filter
 * @param {string[]} [props.status] Status filter
 * @param {boolean} [props.hideCasesWithNoUserEdits] Hide cases with no account edits filter
 * @param {string[]} [props.signal] Signal filter
 * @param {boolean} [globalEditCountsUsed] The value of
 *    wgCheckUserSuggestedInvestigationsGlobalEditCountsUsed from mw.config.get to use
 *    for the test
 * @param {string[]|Object[]} signals The value of wgCheckUserSuggestedInvestigationsSignals
 *    from mw.config.get for the test
 * @return {{ wrapper, dialog }} The dialog component and wrapper
 */
const commonComponentTest = async ( props = {}, globalEditCountsUsed = false, signals = [] ) => {
	mockJSConfig( {
		wgCheckUserSuggestedInvestigationsGlobalEditCountsUsed: globalEditCountsUsed,
		wgCheckUserSuggestedInvestigationsSignals: signals
	} );

	// Render the component and wait for CdxDialog to run some code
	const wrapper = renderComponent( props );
	await nextTick();

	expect( wrapper.exists() ).toEqual( true );

	// Check the dialog element exists.
	const dialog = wrapper.find(
		'.ext-checkuser-suggestedinvestigations-filter-dialog'
	);
	expect( dialog.exists() ).toEqual( true );

	// Expect that the username multi-select component exists (separate tests
	// will check other parts of the component)
	const statusCheckboxesField = dialog.find(
		'.ext-checkuser-suggestedinvestigations-filter-dialog-username-filter'
	);
	expect( statusCheckboxesField.exists() ).toEqual( true );
	expect( statusCheckboxesField.text() ).toContain(
		'(checkuser-suggestedinvestigations-filter-dialog-username-filter-header)'
	);
	expect( statusCheckboxesField.html() ).toContain(
		'(checkuser-suggestedinvestigations-filter-dialog-username-filter-placeholder)'
	);

	// Check that the dialog footer exists and that it has the expected buttons
	const footer = dialog.find(
		'.cdx-dialog__footer'
	);
	expect( footer.exists() ).toEqual( true );

	const closeButton = footer.find(
		'.cdx-dialog__footer__default-action'
	);
	expect( closeButton.exists() ).toEqual( true );
	expect( closeButton.text() ).toEqual(
		'(checkuser-suggestedinvestigations-filter-dialog-close-button)'
	);

	const showResultsButton = footer.find(
		'.cdx-dialog__footer__primary-action'
	);
	expect( showResultsButton.exists() ).toEqual( true );
	expect( showResultsButton.text() ).toEqual(
		'(checkuser-suggestedinvestigations-filter-dialog-show-results-button)'
	);

	return { dialog, wrapper };
};

/**
 * Checks the status filter checkboxes exist and have the expected checked state
 *
 * @param {*} dialog The dialog component
 * @param {{open: boolean, resolved: boolean, invalid: boolean}} expectedCheckedState
 */
const commonStatusFilterCheckboxTest = async ( dialog, expectedCheckedState ) => {
	// Expect that the status checkboxes exist
	const statusCheckboxesField = dialog.find(
		'.ext-checkuser-suggestedinvestigations-filter-dialog-status-filter'
	);
	expect( statusCheckboxesField.exists() ).toEqual( true );
	expect( statusCheckboxesField.text() ).toContain(
		'(checkuser-suggestedinvestigations-filter-dialog-status-filter-header)'
	);

	for ( const status of [ 'open', 'resolved', 'invalid' ] ) {
		expect( statusCheckboxesField.text() ).toContain(
			'(checkuser-suggestedinvestigations-status-' + status + ')'
		);

		const checkboxField = statusCheckboxesField.find( 'input[name=filter-status-' + status + ']' );
		expect( checkboxField.exists() ).toEqual( true );
		expect( checkboxField.element.checked ).toEqual( expectedCheckedState[ status ] );
	}
};

/**
 * Checks whether the "Show cases where no accounts have edits" checkbox exists and
 * that it has the correct checked status
 *
 * @param {*} dialog The dialog component
 * @param {boolean} expectedCheckedState
 * @param {boolean} globalEditCountsUsed
 * @return {Promise<void>}
 */
const commonShowCasesWithNoUserEditsCheckboxTest = async (
	dialog, expectedCheckedState, globalEditCountsUsed
) => {
	const hideCaseWithNoUserEditsField = dialog.find(
		'.cdx-checkbox:has(input[name=filter-show-cases-with-no-user-edits])'
	);

	let expectedMessageKey = '(checkuser-suggestedinvestigations-filter-dialog-show-cases-with-no-user-edits';
	if ( globalEditCountsUsed ) {
		expectedMessageKey += '-globally';
	}
	expectedMessageKey += ')';
	expect( hideCaseWithNoUserEditsField.text() ).toContain( expectedMessageKey );

	const hideCasesWithNoUserEditsCheckbox = hideCaseWithNoUserEditsField.find(
		'input[name=filter-show-cases-with-no-user-edits]'
	);
	expect( hideCasesWithNoUserEditsCheckbox.element.checked ).toEqual( expectedCheckedState );
};

/**
 * Checks whether the "Hide cases where no accounts are blocked" checkbox exists and
 * that it has the correct checked status
 *
 * @param {*} dialog The dialog component
 * @param {boolean} expectedCheckedState
 * @return {Promise<void>}
 */
const commonHideCasesWithNoBlockedUsersCheckboxTest = async ( dialog, expectedCheckedState ) => {
	const hideCasesWithNoBlockedUsersField = dialog.find(
		'.cdx-checkbox:has(input[name=filter-hide-cases-with-no-blocked-users])'
	);

	expect( hideCasesWithNoBlockedUsersField.text() ).toContain(
		'(checkuser-suggestedinvestigations-filter-dialog-hide-cases-with-no-blocked-users)'
	);

	const hideCasesWithNoBlockedUsersCheckbox = hideCasesWithNoBlockedUsersField.find(
		'input[name=filter-hide-cases-with-no-blocked-users]'
	);
	expect( hideCasesWithNoBlockedUsersCheckbox.element.checked ).toEqual( expectedCheckedState );
};

/**
 * Checks the signal filter checkboxes exist and have the expected checked state
 *
 * @param {*} dialog The dialog component
 * @param {string[]} expectedDisplayNames
 * @param {Object} expectedCheckedState
 */
const commonSignalFilterCheckboxTest = async (
	dialog, expectedDisplayNames, expectedCheckedState
) => {
	// Expect that the status checkboxes exist
	const statusCheckboxesField = dialog.find(
		'.ext-checkuser-suggestedinvestigations-filter-dialog-signal-filter'
	);
	expect( statusCheckboxesField.exists() ).toEqual( true );
	expect( statusCheckboxesField.text() ).toContain(
		'(checkuser-suggestedinvestigations-filter-dialog-signal-filter-header)'
	);

	for ( const expectedDisplayName of expectedDisplayNames ) {
		expect( statusCheckboxesField.text() ).toContain( expectedDisplayName );
	}

	for ( const [ urlName, shouldBeChecked ] of Object.entries( expectedCheckedState ) ) {
		const checkboxField = statusCheckboxesField.find( 'input[name=filter-signal-' + urlName + ']' );
		expect( checkboxField.exists() ).toEqual( true );
		expect( checkboxField.element.checked ).toEqual( shouldBeChecked );
	}
};

/**
 * Checks the case age filter radios exist and have the expected selected state
 *
 * @param {*} dialog The dialog component
 * @param {string} expectedSelectedValue The expected selected radio value
 */
const commonLastUpdatedFilterRadioTest = async ( dialog, expectedSelectedValue ) => {
	const lastUpdatedField = dialog.find(
		'.ext-checkuser-suggestedinvestigations-filter-dialog-last-updated-filter'
	);
	expect( lastUpdatedField.exists() ).toEqual( true );
	expect( lastUpdatedField.text() ).toContain(
		'(checkuser-suggestedinvestigations-filter-dialog-last-updated-header)'
	);

	for ( const optionValue of [ '1', '3', '7', '90', '' ] ) {
		const radio = lastUpdatedField.find( 'input[name=filter-last-updated][value="' + optionValue + '"]' );
		expect( radio.exists() ).toEqual( true );
		expect( radio.element.checked ).toEqual( optionValue === expectedSelectedValue );
	}
};

describe( 'Suggested Investigations change status dialog', () => {
	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'Renders correctly for when opened with no filters set', async () => {
		const { dialog } = await commonComponentTest();

		await commonStatusFilterCheckboxTest(
			dialog, { open: false, resolved: false, invalid: false }
		);
	} );

	it( 'Renders correctly for when opened with status filter set to open', async () => {
		const { dialog } = await commonComponentTest(
			{ status: [ 'open' ] }
		);

		await commonStatusFilterCheckboxTest(
			dialog, { open: true, resolved: false, invalid: false }
		);
	} );

	it( 'Renders correctly for when opened with status filter set to resolved and invalid', async () => {
		const { dialog } = await commonComponentTest(
			{ status: [ 'resolved', 'invalid' ] }
		);

		await commonStatusFilterCheckboxTest(
			dialog, { open: false, resolved: true, invalid: true }
		);

		await commonShowCasesWithNoUserEditsCheckboxTest( dialog, false, false );
		await commonHideCasesWithNoBlockedUsersCheckboxTest( dialog, false );
	} );

	it( 'Renders correctly when opened with showCasesWithNoUserEdits pre-checked', async () => {
		const { dialog } = await commonComponentTest(
			{ status: [ 'resolved' ], hideCasesWithNoUserEdits: false },
			true,
			[ 'dev-signal-1', 'dev-signal-2' ]
		);

		await commonStatusFilterCheckboxTest(
			dialog, { open: false, resolved: true, invalid: false }
		);

		await commonShowCasesWithNoUserEditsCheckboxTest( dialog, true, true );

		await commonSignalFilterCheckboxTest(
			dialog,
			[
				'(checkuser-suggestedinvestigations-signal-dev-signal-1)',
				'(checkuser-suggestedinvestigations-signal-dev-signal-2)'
			],
			{ 'dev-signal-1': false, 'dev-signal-2': false }
		);
	} );

	it( 'Renders correctly when opened with dev-signal-1 pre-checked', async () => {
		const { dialog } = await commonComponentTest(
			{ signal: [ 'dev-signal-1', 'dev-signal-3' ] },
			true,
			[
				{ name: 'dev-signal-1' },
				{ name: 'dev-signal-2', urlName: 'signal-5g' },
				{ name: 'dev-signal-3', urlName: 'signal-4g', displayName: 'Dev signal 3' }
			]
		);

		await commonShowCasesWithNoUserEditsCheckboxTest( dialog, false, true );

		await commonSignalFilterCheckboxTest(
			dialog,
			[
				'(checkuser-suggestedinvestigations-signal-dev-signal-1)',
				'(checkuser-suggestedinvestigations-signal-dev-signal-2)',
				'Dev signal 3'
			],
			{ 'dev-signal-1': true, 'signal-5g': false, 'signal-4g': true }
		);
	} );

	it( 'Closes dialog if "Close" button pressed', async () => {
		const { dialog, wrapper } = await commonComponentTest();

		// Press the close button
		const closeButton = dialog.find(
			'.cdx-dialog__footer__default-action'
		);
		await closeButton.trigger( 'click' );

		// Expect the dialog has been closed
		expect( wrapper.vm.open ).toEqual( false );
	} );

	it( 'Redirects to filtered view when "Show results" button pressed', async () => {
		const { dialog, wrapper } = await commonComponentTest(
			{ status: [ 'resolved' ], username: [ 'TestUser1' ] }
		);

		// Check the "open" status checkbox and wait for the change to be propagated
		const openStatusCheckbox = dialog.find(
			'input[name=filter-status-open]'
		);
		await openStatusCheckbox.setChecked();
		await nextTick();

		// Press the "Show results" button
		const showResultsButton = dialog.find(
			'.cdx-dialog__footer__primary-action'
		);
		await showResultsButton.trigger( 'click' );

		// Expect that a call to redirect to another page has been made without closing the dialog
		// (the dialog is left open so that it's kept open until the page has reloaded)
		expect( wrapper.vm.open ).toEqual( true );
		expect( mockUpdateFiltersOnPage ).toHaveBeenCalledWith(
			{ status: [ 'open', 'resolved' ], username: [ 'TestUser1' ], signal: [] }, window
		);
	} );

	it( 'Show results button press when hideCasesWithNoUserEdits and signal filters set', async () => {
		const { dialog, wrapper } = await commonComponentTest(
			{ hideCasesWithNoUserEdits: true, signal: [ 'dev-signal-1' ] },
			false,
			[ { name: 'dev-signal-1', urlName: 'signal-1a' } ]
		);

		// Press the "Show results" button
		const showResultsButton = dialog.find(
			'.cdx-dialog__footer__primary-action'
		);
		await showResultsButton.trigger( 'click' );

		expect( wrapper.vm.open ).toEqual( true );
		expect( mockUpdateFiltersOnPage ).toHaveBeenCalledWith(
			{ status: [], username: [], signal: [ 'signal-1a' ] }, window
		);
	} );

	it( '`Show results` button press sends hideCasesWithNoUserEdits=0 when show-cases checkbox is checked', async () => {
		const { dialog, wrapper } = await commonComponentTest(
			{ hideCasesWithNoUserEdits: true }
		);

		// Check the showCasesWithNoUserEdits checkbox
		const checkbox = dialog.find( 'input[name=filter-show-cases-with-no-user-edits]' );
		await checkbox.setChecked( true );
		await nextTick();

		// Press the "Show results" button
		const showResultsButton = dialog.find( '.cdx-dialog__footer__primary-action' );
		await showResultsButton.trigger( 'click' );

		expect( wrapper.vm.open ).toEqual( true );
		expect( mockUpdateFiltersOnPage ).toHaveBeenCalledWith(
			{ hideCasesWithNoUserEdits: 0, status: [], username: [], signal: [] }, window
		);
	} );

	it( 'Renders correctly when opened with hideCasesWithNoBlockedUsers pre-checked', async () => {
		const { dialog } = await commonComponentTest(
			{ hideCasesWithNoBlockedUsers: true }
		);

		await commonShowCasesWithNoUserEditsCheckboxTest( dialog, false, false );
		await commonHideCasesWithNoBlockedUsersCheckboxTest( dialog, true );
	} );

	it( '`Show results` button press when hideCasesWithNoBlockedUsers checkbox is checked', async () => {
		const { dialog, wrapper } = await commonComponentTest(
			{ hideCasesWithNoUserEdits: true, hideCasesWithNoBlockedUsers: true }
		);

		const showResultsButton = dialog.find(
			'.cdx-dialog__footer__primary-action'
		);
		await showResultsButton.trigger( 'click' );

		expect( wrapper.vm.open ).toEqual( true );
		expect( mockUpdateFiltersOnPage ).toHaveBeenCalledWith(
			{ hideCasesWithNoBlockedUsers: 1, status: [], username: [], signal: [] },
			window
		);
	} );

	it( '`Show results` button press includes hideCasesWithNoBlockedUsers when checkbox is checked', async () => {
		const { dialog, wrapper } = await commonComponentTest(
			{ hideCasesWithNoBlockedUsers: true, signal: [ 'dev-signal-1' ] },
			false,
			[ { name: 'dev-signal-1', urlName: 'signal-1a' } ]
		);

		// Press the "Show results" button
		const showResultsButton = dialog.find(
			'.cdx-dialog__footer__primary-action'
		);
		await showResultsButton.trigger( 'click' );

		expect( wrapper.vm.open ).toEqual( true );
		expect( mockUpdateFiltersOnPage ).toHaveBeenCalledWith(
			{ hideCasesWithNoBlockedUsers: 1, status: [], username: [], signal: [ 'signal-1a' ] }, window
		);
	} );

	it.each( [
		{
			description: 'with "All time" selected when no lastUpdated set',
			filters: {},
			expectedSelectedValue: ''
		},
		{
			description: 'with correct radio selected when lastUpdated is 7 (string, as set by the URL)',
			filters: { lastUpdated: '7' },
			expectedSelectedValue: '7'
		},
		{
			description: 'with correct radio selected when lastUpdated is 7 (number, as sent by PHP config var)',
			filters: { lastUpdated: 7 },
			expectedSelectedValue: '7'
		}
	] )( 'Renders user activity filter $description', async ( { filters, expectedSelectedValue } ) => {
		const { dialog } = await commonComponentTest( filters );

		await commonLastUpdatedFilterRadioTest( dialog, expectedSelectedValue );
	} );

	it.each( [
		{
			description: 'includes lastUpdated when a non-default radio is selected',
			radioValueToSelect: '7',
			expectedFilters: { lastUpdated: '7', status: [], username: [], signal: [] }
		},
		{
			description: 'does not include lastUpdated when no filter is set (default state)',
			radioValueToSelect: null,
			expectedFilters: { status: [], username: [], signal: [] }
		},
		{
			description: 'does not include lastUpdated when "All time" radio is explicitly selected',
			radioValueToSelect: '',
			expectedFilters: { status: [], username: [], signal: [] }
		}
	] )( '`Show results` button press $description', async ( { radioValueToSelect, expectedFilters } ) => {
		const { dialog, wrapper } = await commonComponentTest();

		if ( radioValueToSelect !== null ) {
			const radio = dialog.find( 'input[name=filter-last-updated][value="' + radioValueToSelect + '"]' );
			await radio.setChecked();
			await nextTick();
		}

		const showResultsButton = dialog.find(
			'.cdx-dialog__footer__primary-action'
		);
		await showResultsButton.trigger( 'click' );

		expect( wrapper.vm.open ).toEqual( true );
		expect( mockUpdateFiltersOnPage ).toHaveBeenCalledWith( expectedFilters, window );
	} );
} );
