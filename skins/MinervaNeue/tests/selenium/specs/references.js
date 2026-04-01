import {
	pageExistsWithText,
	iAmUsingTheMobileSite,
	iAmOnPage
} from '../features/step_definitions/common_steps.js';
import {
	iClickOnAReference,
	iClickOnTheMask,
	iShouldSeeNotTheReferenceDrawer,
	iClickOnANestedReference,
	iShouldSeeDrawerWithText
} from '../features/step_definitions/reference_steps.js';
import { mwbot } from 'wdio-mediawiki/Api.js';

// @chrome @en.m.wikipedia.beta.wmflabs.org @firefox @test2.m.wikipedia.org @vagrant
describe( 'Opening and closing the reference drawer', () => {

	before( async function () {
		// References are provided by the Cite extension which might not be available
		await mwbot().then(
			( bot ) => bot.request( {
				action: 'query',
				format: 'json',
				meta: 'siteinfo',
				siprop: 'extensions'
			} ).then( ( response ) => {
				const installedExtensions = response.query.extensions.map(
					( extension ) => extension.name
				);
				if ( !installedExtensions.includes( 'Cite' ) ) {
					this.skip();
				}
			} )
		);

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
