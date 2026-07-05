import LoginPage from 'wdio-mediawiki/LoginPage';
import { viewEditPage as ViewEditPage } from '../pageobjects/viewedit.page.js';
import { viewListPage as ViewListPage } from '../pageobjects/viewlist.page.js';
import { viewImportPage as ViewImportPage } from '../pageobjects/viewimport.page.js';

describe( 'When importing a filter', () => {
	const filterSpecs = {
		name: 'My filter name',
		comments: 'Notes go here.',
		rules: 'true === false',
		enabled: true,
		hidden: true,
		deleted: false,
		warnMessage: 'abusefilter-warning-foobar'
	};
	let importData;

	before( async () => {
		await LoginPage.loginAdmin();

		await ViewEditPage.open( 'new' );
		await ViewEditPage.switchEditor();
		await ViewEditPage.name.setValue( filterSpecs.name );
		await ViewEditPage.rules.setValue( filterSpecs.rules );
		await ViewEditPage.comments.setValue( filterSpecs.comments );
		if ( !filterSpecs.enabled ) {
			await ViewEditPage.enabled.click();
		}
		if ( filterSpecs.hidden ) {
			await ViewEditPage.hidden.click();
		}
		if ( filterSpecs.deleted ) {
			await ViewEditPage.deleted.click();
		}
		await ViewEditPage.warnCheckbox.click();
		await ViewEditPage.setWarningMessage( filterSpecs.warnMessage );
		await ViewEditPage.submit();

		await expect( ViewListPage.filterSavedNotice ).toBeDisplayed();
		const filterID = await ViewListPage.savedFilterID();
		await ViewEditPage.open( filterID );
		importData = await ViewEditPage.exportData;
	} );

	it( 'the interface should be visible', async () => {
		await ViewImportPage.open();
		await expect( ViewImportPage.importData ).toBeDisplayed();
	} );

	it( 'it should redirect to ViewEdit after submission', async () => {
		await ViewImportPage.importText( 'SOME INVALID GIBBERISH' );
		expect( await browser.getUrl() ).toMatch( /\/new$/ );
	} );

	it( 'bad data results in an error', async () => {
		await expect( ViewEditPage.error ).toBeDisplayed();
	} );

	it( 'valid data shows the editing interface', async () => {
		await ViewImportPage.open();
		await ViewImportPage.importText( importData );
		await expect( ViewEditPage.name ).toBeDisplayed();
	} );

	describe( 'Data on the editing interface is correct', () => {
		it( 'filter specs are copied', async () => {
			expect( await ViewEditPage.name.getValue() ).toBe( filterSpecs.name );
			expect( await ViewEditPage.comments.getValue() ).toBe( filterSpecs.comments + '\n' );
			expect( await ViewEditPage.rules.getValue() ).toBe( filterSpecs.rules + '\n' );
		} );
		it( 'filter flags are copied', async () => {
			expect( await ViewEditPage.enabled.isSelected() ).toBe( !!filterSpecs.enabled );
			expect( await ViewEditPage.hidden.isSelected() ).toBe( !!filterSpecs.hidden );
			expect( await ViewEditPage.deleted.isSelected() ).toBe( !!filterSpecs.deleted );
		} );
		it( 'filter actions are copied', async () => {
			expect( await ViewEditPage.warnCheckbox.isSelected() ).toBe( true );
			expect( await ViewEditPage.warnOtherMessage.getValue() )
				.toBe( filterSpecs.warnMessage );
		} );

		it( 'the imported data can be saved', async () => {
			await ViewEditPage.submit();
			await expect( ViewListPage.filterSavedNotice ).toBeDisplayed();
		} );
	} );
} );
