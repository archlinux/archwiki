'use strict';

const assert = require( 'assert' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage' ),
	ViewEditPage = require( '../pageobjects/viewedit.page' ),
	ViewListPage = require( '../pageobjects/viewlist.page' ),
	ViewImportPage = require( '../pageobjects/viewimport.page' );

describe( 'When importing a filter', function () {
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

	before( function () {
		LoginPage.loginAdmin();

		ViewEditPage.open( 'new' );
		ViewEditPage.switchEditor();
		ViewEditPage.name.setValue( filterSpecs.name );
		ViewEditPage.rules.setValue( filterSpecs.rules );
		ViewEditPage.comments.setValue( filterSpecs.comments );
		if ( !filterSpecs.enabled ) {
			ViewEditPage.enabled.click();
		}
		if ( filterSpecs.hidden ) {
			ViewEditPage.hidden.click();
		}
		if ( filterSpecs.deleted ) {
			ViewEditPage.deleted.click();
		}
		ViewEditPage.warnCheckbox.click();
		ViewEditPage.setWarningMessage( filterSpecs.warnMessage );
		ViewEditPage.submit();

		assert( ViewListPage.filterSavedNotice.isDisplayed() );
		const filterID = ViewListPage.savedFilterID;
		ViewEditPage.open( filterID );
		importData = ViewEditPage.exportData;
	} );

	it( 'the interface should be visible', function () {
		ViewImportPage.open();
		assert( ViewImportPage.importData.isDisplayed() );
	} );

	it( 'it should redirect to ViewEdit after submission', function () {
		ViewImportPage.importText( 'SOME INVALID GIBBERISH' );
		assert( /\/new$/.test( browser.getUrl() ) );
	} );

	it( 'bad data results in an error', function () {
		assert( ViewEditPage.error.isDisplayed() );
	} );

	it( 'valid data shows the editing interface', function () {
		ViewImportPage.open();
		ViewImportPage.importText( importData );
		assert( ViewEditPage.name.isDisplayed() );
	} );

	describe( 'Data on the editing interface is correct', function () {
		it( 'filter specs are copied', function () {
			assert.strictEqual( ViewEditPage.name.getValue(), filterSpecs.name );
			assert.strictEqual( ViewEditPage.comments.getValue(), filterSpecs.comments + '\n' );
			assert.strictEqual( ViewEditPage.rules.getValue(), filterSpecs.rules + '\n' );
		} );
		it( 'filter flags are copied', function () {
			assert.strictEqual( ViewEditPage.enabled.isSelected(), !!filterSpecs.enabled );
			assert.strictEqual( ViewEditPage.hidden.isSelected(), !!filterSpecs.hidden );
			assert.strictEqual( ViewEditPage.deleted.isSelected(), !!filterSpecs.deleted );
		} );
		it( 'filter actions are copied', function () {
			assert.strictEqual( ViewEditPage.warnCheckbox.isSelected(), true );
			assert.strictEqual( ViewEditPage.warnOtherMessage.getValue(), filterSpecs.warnMessage );
		} );

		it( 'the imported data can be saved', function () {
			ViewEditPage.submit();
			assert( ViewListPage.filterSavedNotice.isDisplayed() );
		} );
	} );
} );
