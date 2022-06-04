'use strict';

const assert = require( 'assert' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage' ),
	ViewListPage = require( '../pageobjects/viewlist.page' );

describe( 'Special:AbuseFilter', function () {
	it( 'page should exist on installation', function () {
		ViewListPage.open();
		assert.equal( ViewListPage.title.getText(), 'Abuse filter management' );
	} );
	it( 'page should have the button for creating a new filter', function () {
		LoginPage.loginAdmin();
		ViewListPage.open();
		assert.equal( ViewListPage.newFilterButton.getText(), 'Create a new filter' );
		assert.notEqual(
			ViewListPage.newFilterButton.getAttribute( 'href' ).indexOf( 'Special:AbuseFilter/new' ),
			-1
		);
	} );
} );
