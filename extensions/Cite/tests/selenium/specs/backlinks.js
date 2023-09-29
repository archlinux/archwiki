'use strict';

const assert = require( 'assert' ),
	Api = require( 'wdio-mediawiki/Api' ),
	CitePage = require( '../pageobjects/cite.page' ),
	Util = require( 'wdio-mediawiki/Util' );

describe( 'Cite backlinks', function () {
	let title;

	before( async function () {
		title = Util.getTestString( 'CiteTest-title-' );

		const bot = await Api.bot();
		await bot.edit(
			title,
			'This is reference #1: <ref name="a">This is citation #1 for reference #1 and #2</ref>\n\n' +
				'This is reference #2: <ref name="a" />\n\n' +
				'This is reference #3: <ref>This is citation #2</ref>\n\n' +
				'<references />'
		);
	} );

	beforeEach( async function () {
		await CitePage.openTitle( title );
		await CitePage.scriptsReady();
	} );

	it( 'are highlighted in the reference list when there are multiple used references', async function () {
		await CitePage.getReference( 2 ).click();
		assert(
			( await CitePage.getCiteSubBacklink( 2 ).getAttribute( 'class' ) )
				.includes( 'mw-cite-targeted-backlink' ),
			'the jump mark symbol of the backlink is highlighted'
		);
	} );

	it( 'clickable up arrow is hidden by default when there are multiple backlinks', async function () {
		assert(
			!( await CitePage.getCiteMultiBacklink( 1 ).isDisplayed() ),
			'the up-pointing arrow in the reference line is not linked'
		);
	} );

	it( 'clickable up arrow shows when jumping to multiple used references', async function () {
		await CitePage.getReference( 2 ).click();
		assert(
			await CitePage.getCiteMultiBacklink( 1 ).isDisplayed(),
			'the up-pointing arrow in the reference line is linked'
		);

		assert.strictEqual(
			await CitePage.getFragmentFromLink( await CitePage.getCiteMultiBacklink( 1 ) ),
			await CitePage.getReference( 2 ).getAttribute( 'id' ),
			'the up-pointing arrow in the reference line is linked to the clicked reference'
		);
	} );

	it( 'use the last clicked target for the clickable up arrow on multiple used references', async function () {
		await CitePage.getReference( 2 ).click();
		await CitePage.getReference( 1 ).click();

		assert.strictEqual(
			await CitePage.getFragmentFromLink( CitePage.getCiteMultiBacklink( 1 ) ),
			await CitePage.getReference( 1 ).getAttribute( 'id' ),
			'the up-pointing arrow in the reference line is linked to the last clicked reference'
		);
	} );

	it( 'clickable up arrow is hidden when jumping back from multiple used references', async function () {
		await CitePage.getReference( 2 ).click();
		await CitePage.getCiteMultiBacklink( 1 ).click();

		assert(
			!( await CitePage.getCiteMultiBacklink( 1 ).isDisplayed() ),
			'the up-pointing arrow in the reference line is not linked'
		);
	} );

	it( 'are not accidentally removed from unnamed references', async function () {
		await CitePage.getReference( 3 ).click();
		await CitePage.getCiteSingleBacklink( 2 ).waitForDisplayed();
		await CitePage.getCiteSingleBacklink( 2 ).click();
		// It doesn't matter what is focussed next, just needs to be something else
		await CitePage.getReference( 1 ).click();

		assert(
			await CitePage.getCiteSingleBacklink( 2 ).isDisplayed(),
			'the backlink on the unnamed reference is still visible'
		);
	} );
} );
