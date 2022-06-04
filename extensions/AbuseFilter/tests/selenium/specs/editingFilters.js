'use strict';

const assert = require( 'assert' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage' ),
	ViewEditPage = require( '../pageobjects/viewedit.page' ),
	ViewListPage = require( '../pageobjects/viewlist.page' );

describe( 'Filter editing', function () {
	describe( 'The editing interface', function () {
		it( 'is not visible to logged-out users', function () {
			ViewEditPage.open( 'new' );
			assert( ViewEditPage.error.isDisplayed() );
		} );

		it( 'is visible to logged-in admins', function () {
			LoginPage.loginAdmin();
			ViewEditPage.open( 'new' );
			assert( ViewEditPage.name.isDisplayed() );
		} );
	} );

	describe( 'Trying to open a non-existing filter', function () {
		it( 'I should receive an error', function () {
			ViewEditPage.open( 1234567 );
			assert( ViewEditPage.error.isDisplayed() );
		} );
	} );

	const filterSpecs = {
		name: 'My test filter',
		rules: '"confirmed" in user_groups & true === false',
		comments: 'Some notes',
		warnMsg: 'abusefilter-warning-foobar'
	};
	let filterID, historyID;

	function assertFirstVersionSaved() {
		assert.strictEqual( ViewEditPage.name.getValue(), filterSpecs.name );
		assert.strictEqual( ViewEditPage.rules.getValue(), filterSpecs.rules + '\n' );
		assert.strictEqual( ViewEditPage.comments.getValue(), filterSpecs.comments + '\n' );
		ViewEditPage.warnCheckbox.isSelected();
		assert.strictEqual( ViewEditPage.warnOtherMessage.getValue(), filterSpecs.warnMsg );
	}

	describe( 'Creating a new filter', function () {
		before( function () {
			ViewEditPage.open( 'new' );
		} );

		it( 'edit can be saved (1)', function () {
			ViewEditPage.switchEditor();

			ViewEditPage.name.setValue( filterSpecs.name );
			ViewEditPage.rules.setValue( filterSpecs.rules );
			ViewEditPage.comments.setValue( filterSpecs.comments );
			ViewEditPage.warnCheckbox.click();
			ViewEditPage.setWarningMessage( filterSpecs.warnMsg );

			ViewEditPage.submit();
			assert( ViewListPage.filterSavedNotice.isDisplayed() );
			filterID = ViewListPage.savedFilterID;
			assert.ok( filterID );
			historyID = ViewListPage.savedFilterHistoryID;
			assert.ok( historyID );
		} );

		it( 'saved data is retained (1)', function () {
			ViewEditPage.open( filterID );
			assertFirstVersionSaved();
		} );
	} );

	describe( 'Editing an existing filter', function () {
		const newName = 'New filter name',
			newNotes = 'More filter notes';

		it( 'edit can be saved (2)', function () {
			ViewEditPage.name.setValue( newName );
			ViewEditPage.comments.addValue( newNotes );
			ViewEditPage.submit();
			assert( ViewListPage.filterSavedNotice.isDisplayed() );
		} );

		it( 'saved data is retained (2)', function () {
			ViewEditPage.open( filterID );
			assert.strictEqual( ViewEditPage.name.getValue(), newName );
			assert.strictEqual( ViewEditPage.comments.getValue(), newNotes + filterSpecs.comments + '\n' );
		} );
	} );

	describe( 'Restoring an old version of a filter', function () {
		it( 'edit can be saved (3)', function () {
			ViewEditPage.open( 'history/' + filterID + '/item/' + historyID );
			ViewEditPage.submit();
			assert( ViewListPage.filterSavedNotice.isDisplayed() );
		} );

		it( 'saved data is retained (3)', function () {
			ViewEditPage.open( filterID );
			assertFirstVersionSaved();
		} );
	} );

	describe( 'CSRF protection', function () {
		const filterName = 'Testing CSRF';

		it( 'a CSRF token is required to save the filter', function () {
			ViewEditPage.invalidateToken();
			ViewEditPage.name.setValue( filterName );
			ViewEditPage.submit();
			assert( ViewEditPage.warning.isDisplayed() );
		} );
		it( 'even if the token is invalid, the ongoing edit is not lost', function () {
			assert.strictEqual( ViewEditPage.name.getValue(), filterName );
		} );
	} );

	describe( 'Trying to save a filter with bad data', function () {
		before( function () {
			ViewEditPage.open( 'new' );
		} );

		it( 'cannot save an empty filter', function () {
			ViewEditPage.submit();
			assert( ViewEditPage.error.isDisplayed() );
		} );

		const rules = 'null';

		it( 'cannot save a filter with rules but no name', function () {
			ViewEditPage.switchEditor();
			ViewEditPage.rules.setValue( rules );
			ViewEditPage.submit();
			assert( ViewEditPage.error.isDisplayed() );
		} );

		it( 'data is retained if saving fails', function () {
			assert.strictEqual( ViewEditPage.rules.getValue(), rules + '\n' );
		} );
	} );
} );
