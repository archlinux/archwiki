import LoginPage from 'wdio-mediawiki/LoginPage';
import { viewEditPage as ViewEditPage } from '../pageobjects/viewedit.page.js';
import { viewListPage as ViewListPage } from '../pageobjects/viewlist.page.js';

describe( 'Filter editing', () => {
	before( async () => {
		await LoginPage.loginAdmin();
	} );

	describe( 'The editing interface', () => {
		it( 'is visible to logged-in admins', async () => {
			await ViewEditPage.open( 'new' );
			await expect( ViewEditPage.name ).toBeDisplayed();
		} );
	} );

	describe( 'Trying to open a non-existing filter', () => {
		it( 'I should receive an error', async () => {
			await ViewEditPage.open( 1234567 );
			await expect( ViewEditPage.error ).toBeDisplayed();
			expect( await ViewEditPage.error.getText() ).toBe( 'The filter you specified does not exist' );
		} );
	} );

	const filterSpecs = {
		name: 'My test filter',
		rules: '"confirmed" in user_groups & true === false',
		comments: 'Some notes',
		warnMsg: 'abusefilter-warning-foobar'
	};
	let filterID, historyID;

	async function assertFirstVersionSaved() {
		expect( await ViewEditPage.name.getValue() ).toBe( filterSpecs.name );
		expect( await ViewEditPage.rules.getValue() ).toBe( filterSpecs.rules + '\n' );
		expect( await ViewEditPage.comments.getValue() ).toBe( filterSpecs.comments + '\n' );
		await ViewEditPage.warnCheckbox.isSelected();
		expect( await ViewEditPage.warnOtherMessage.getValue() ).toBe( filterSpecs.warnMsg );
	}

	describe( 'Creating a new filter', () => {
		before( async () => {
			await ViewEditPage.open( 'new' );
		} );

		it( 'edit can be saved (1)', async () => {
			await ViewEditPage.switchEditor();

			await ViewEditPage.name.setValue( filterSpecs.name );
			await ViewEditPage.rules.setValue( filterSpecs.rules );
			await ViewEditPage.comments.setValue( filterSpecs.comments );
			await ViewEditPage.warnCheckbox.click();
			await ViewEditPage.setWarningMessage( filterSpecs.warnMsg );
			await ViewEditPage.submit();

			await expect( ViewListPage.filterSavedNotice ).toBeDisplayed();

			filterID = await ViewListPage.savedFilterID();
			expect( filterID ).toBeTruthy();
			historyID = await ViewListPage.savedFilterHistoryID();
			expect( historyID ).toBeTruthy();
		} );

		it( 'saved data is retained (1)', async () => {
			await ViewEditPage.open( filterID );
			await assertFirstVersionSaved();
		} );
	} );

	describe( 'Editing an existing filter', () => {
		before( async () => {
			await ViewEditPage.open( filterID );
		} );

		const newName = 'New filter name',
			newNotes = 'More filter notes';

		it( 'edit can be saved (2)', async () => {
			await ViewEditPage.name.setValue( newName );
			await ViewEditPage.comments.addValue( newNotes );
			await ViewEditPage.submit();
			await expect( ViewListPage.filterSavedNotice ).toBeDisplayed();
		} );

		it( 'saved data is retained (2)', async () => {
			await ViewEditPage.open( filterID );
			expect( await ViewEditPage.name.getValue() ).toBe( newName );
			expect( await ViewEditPage.comments.getValue() ).toBe( filterSpecs.comments + '\n' + newNotes + '\n' );
		} );
	} );

	describe( 'Restoring an old version of a filter', () => {
		it( 'edit can be saved (3)', async () => {
			await ViewEditPage.open( 'history/' + filterID + '/item/' + historyID );
			await ViewEditPage.submit();
			await expect( ViewListPage.filterSavedNotice ).toBeDisplayed();
		} );

		it( 'saved data is retained (3)', async () => {
			await ViewEditPage.open( filterID );
			await assertFirstVersionSaved();
		} );
	} );

	describe( 'CSRF protection', () => {
		before( async () => {
			await ViewEditPage.open( 'new' );
		} );

		const filterName = 'Testing CSRF';

		it( 'a CSRF token is required to save the filter', async () => {
			await ViewEditPage.invalidateToken();
			await ViewEditPage.name.setValue( filterName );
			await ViewEditPage.submit();
			await expect( ViewEditPage.warning ).toBeDisplayed();
		} );
		it( 'even if the token is invalid, the ongoing edit is not lost', async () => {
			expect( await ViewEditPage.name.getValue() ).toBe( filterName );
		} );
	} );

	describe( 'Trying to save a filter with bad data', () => {
		before( async () => {
			await ViewEditPage.open( 'new' );
		} );

		it( 'cannot save an empty filter', async () => {
			await ViewEditPage.submit();
			await expect( ViewEditPage.error ).toBeDisplayed();
		} );

		const rules = 'action === "edit"';

		it( 'cannot save a filter with rules but no name', async () => {
			await ViewEditPage.switchEditor();
			await ViewEditPage.rules.setValue( rules );
			await ViewEditPage.submit();
			await expect( ViewEditPage.error ).toBeDisplayed();
		} );
	} );
} );
