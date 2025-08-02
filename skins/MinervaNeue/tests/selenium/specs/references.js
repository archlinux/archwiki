'use strict';

const {
		pageExistsWithText,
		iAmUsingTheMobileSite,
		iAmOnPage
	} = require( '../features/step_definitions/common_steps' ),
	{
		iClickOnAReference,
		iClickOnTheMask,
		iShouldSeeNotTheReferenceDrawer,
		iClickOnANestedReference,
		iShouldSeeDrawerWithText
	} = require( '../features/step_definitions/reference_steps' );

// @chrome @en.m.wikipedia.beta.wmflabs.org @firefox @test2.m.wikipedia.org @vagrant
describe( 'Opening and closing the reference drawer', () => {

	before( async () => {
		await pageExistsWithText( 'Selenium References test page', `MinervaNeue is a MediaWiki skin.
{{#tag:ref|This is a note.<ref>This is a nested ref.</ref>|group=note}}
==Notes==
<references group=note />
==References==
<references/>
		` );
	} );

	beforeEach( async () => {
		await iAmUsingTheMobileSite();
	} );

	it( 'Opening a reference', async () => {
		await iAmOnPage( 'Selenium References test page' );
		await iClickOnAReference();
		await iShouldSeeDrawerWithText( 'This is a note.' );
		await iClickOnTheMask();
		await iShouldSeeNotTheReferenceDrawer();
	} );

	it( 'Opening a nested reference', async () => {
		await iAmOnPage( 'Selenium References test page' );
		await iClickOnAReference();
		await iShouldSeeDrawerWithText( 'This is a note.' );
		await iClickOnANestedReference();
		await iShouldSeeDrawerWithText( 'This is a nested ref.' );
	} );
} );
