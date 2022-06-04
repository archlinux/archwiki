'use strict';

const {
		iAmOnAPageThatDoesNotExist, iClickTheBrowserBackButton,
		iClickTheOverlayCloseButton, iDoNotSeeAnOverlay,
		iAmLoggedIntoTheMobileWebsite
	} = require( '../features/step_definitions/common_steps' ),
	{
		iClickTheEditButton, iSeeTheWikitextEditorOverlay
	} = require( '../features/step_definitions/editor_steps' );

// @chrome @en.m.wikipedia.beta.wmflabs.org @firefox @test2.m.wikipedia.org @vagrant @login
describe( 'Wikitext Editor', () => {

	beforeEach( () => {
		iAmLoggedIntoTheMobileWebsite();
		iAmOnAPageThatDoesNotExist();
		iClickTheEditButton();
		iSeeTheWikitextEditorOverlay();
	} );

	// @smoke
	it( 'Closing editor (overlay button)', () => {
		iClickTheOverlayCloseButton();
		iDoNotSeeAnOverlay();
	} );

	it( 'Closing editor (browser button)', () => {
		iClickTheBrowserBackButton();
		iDoNotSeeAnOverlay();
	} );

} );
