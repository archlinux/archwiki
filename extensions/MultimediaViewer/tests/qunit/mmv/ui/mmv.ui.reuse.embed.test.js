/*
 * This file is part of the MediaWiki extension MultimediaViewer.
 *
 * MultimediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MultimediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MultimediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

const { Embed, Utils } = require( 'mmv.ui.reuse' );

( function () {
	const $qf = $( '#qunit-fixture' );

	QUnit.module( 'mmv.ui.reuse.Embed', QUnit.newMwEnvironment() );

	QUnit.test( 'Sense test, object creation and UI construction', ( assert ) => {
		const embed = new Embed( $qf );

		assert.true( embed instanceof Embed, 'Embed UI element is created.' );
		assert.true( embed.$embedTextHtml[ 0 ] instanceof HTMLElement, 'Html snipped text area created.' );
		assert.true( embed.$embedTextWikitext[ 0 ] instanceof HTMLElement, 'Wikitext snipped text area created.' );
		assert.true( embed.$embedSizeSwitchWikitext[ 0 ] instanceof HTMLElement, 'Size selection menu for wikitext created.' );
		assert.true( embed.$embedSizeSwitchHtml[ 0 ] instanceof HTMLElement, 'Size selection menu for html created.' );
	} );

	QUnit.test( 'handleSizeSwitch(): Skip if no item selected.', ( assert ) => {
		const embed = new Embed( $qf );

		assert.expect( 0 );

		embed.$embedSizeSwitchHtml.val( undefined );
		embed.$embedSizeSwitchWikitext.val( undefined );

		embed.updateEmbedHtml = function () {
			assert.true( false, 'No item selected, this should not have been called.' );
		};
		embed.updateEmbedWikitext = function () {
			assert.true( false, 'No item selected, this should not have been called.' );
		};

		embed.handleSizeSwitch();
	} );

	QUnit.test( 'handleSizeSwitch(): HTML/Wikitext size menu item selected.', ( assert ) => {
		const embed = new Embed( $qf );
		const width = '10';
		const height = '20';

		const $option = embed.$embedSizeSwitchHtml.children().first();
		$option.data( 'width', width );
		$option.data( 'height', height );
		embed.$embedSizeSwitchHtml.val( $option.val() );

		embed.updateEmbedHtml = function ( thumb, w, h ) {
			assert.strictEqual( thumb.url, undefined, 'Empty thumbnail passed.' );
			assert.strictEqual( w, width, 'Correct width passed.' );
			assert.strictEqual( h, height, 'Correct height passed.' );
		};
		embed.updateEmbedWikitext = function () {
			// ignore
		};
		embed.select = function () {
			assert.true( true, 'Item selected after update.' );
		};

		embed.handleSizeSwitch();
	} );

	QUnit.test( 'handleSizeSwitch(): Wikitext size menu item selected.', ( assert ) => {
		const embed = new Embed( $qf );
		const width = '10';
		const height = '20';

		const $option = embed.$embedSizeSwitchWikitext.children().first();
		$option.data( 'width', width );
		$option.data( 'height', height );
		embed.$embedSizeSwitchWikitext.val( $option.val() );

		embed.updateEmbedHtml = function () {
			// ignore
		};
		embed.updateEmbedWikitext = function ( w ) {
			assert.strictEqual( w, width, 'Correct width passed.' );
		};
		embed.select = function () {
			assert.true( true, 'Item selected after update.' );
		};

		embed.handleSizeSwitch();
	} );

	QUnit.test( 'updateEmbedHtml(): Do nothing if set() not called before.', ( assert ) => {
		const embed = new Embed( $qf );
		const width = 10;
		const height = 20;

		assert.expect( 0 );

		embed.formatter.getThumbnailHtml = function () {
			assert.true( false, 'formatter.getThumbnailHtml() should not have been called.' );
		};
		embed.updateEmbedHtml( {}, width, height );
	} );

	QUnit.test( 'updateEmbedHtml():', ( assert ) => {
		const embed = new Embed( $qf );
		const title = mw.Title.newFromText( 'File:Foobar.jpg' );
		const url = 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg';
		const thumbUrl = 'https://upload.wikimedia.org/wikipedia/thumb/Foobar.jpg';
		const imageInfo = { url, title };
		const caption = '-';
		const alt = undefined;
		const info = {
			imageInfo,
			caption,
			alt
		};
		let width = 10;
		const height = 20;

		embed.resetCurrentSizeMenuToDefault = () => {};

		embed.set( imageInfo, caption );

		// Small image, no thumbnail info is passed
		embed.formatter.getThumbnailHtml = function ( i, u, w, h ) {
			assert.deepEqual( i, info, 'Info passed correctly.' );
			assert.strictEqual( u, url, 'Image URL passed correctly.' );
			assert.strictEqual( w, width, 'Correct width passed.' );
			assert.strictEqual( h, height, 'Correct height passed.' );
		};
		embed.updateEmbedHtml( {}, width, height );

		// Small image, thumbnail info present
		embed.formatter.getThumbnailHtml = function ( i, u ) {
			assert.strictEqual( u, thumbUrl, 'Image src passed correctly.' );
		};
		embed.updateEmbedHtml( { url: thumbUrl }, width, height );

		// Big image, thumbnail info present
		embed.formatter.getThumbnailHtml = function ( i, u ) {
			assert.strictEqual( u, url, 'Image src passed correctly.' );
		};
		width = 1300;
		embed.updateEmbedHtml( { url: thumbUrl }, width, height );
	} );

	QUnit.test( 'updateEmbedWikitext(): Do nothing if set() not called before.', ( assert ) => {
		const embed = new Embed( $qf );
		const width = 10;

		assert.expect( 0 );

		embed.formatter.getThumbnailWikitext = function () {
			assert.true( false, 'formatter.getThumbnailWikitext() should not have been called.' );
		};
		embed.updateEmbedWikitext( width );
	} );

	QUnit.test( 'updateEmbedWikitext():', ( assert ) => {
		const embed = new Embed( $qf );
		const title = mw.Title.newFromText( 'File:Foobar.jpg' );
		const url = 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg';

		const imageInfo = { url, title };
		const caption = '-';
		const alt = undefined;
		const info = {
			imageInfo,
			caption,
			alt
		};
		const width = 10;

		embed.resetCurrentSizeMenuToDefault = () => {};

		embed.set( imageInfo, caption );

		embed.formatter.getThumbnailWikitextFromEmbedFileInfo = function ( i, w ) {
			assert.deepEqual( i, info, 'EmbedFileInfo passed correctly.' );
			assert.strictEqual( w, width, 'Width passed correctly.' );
		};
		embed.updateEmbedWikitext( width );
	} );

	QUnit.test( 'getPossibleImageSizesForWikitext()', ( assert ) => {
		const embed = new Embed( $qf );
		const exampleSizes = [
			// Big wide image
			{
				width: 2048, height: 1536,
				expected: {
					small: { width: 300, height: 225 },
					medium: { width: 400, height: 300 },
					large: { width: 500, height: 375 },
					default: { width: null, height: null }
				}
			},

			// Big tall image
			{
				width: 201, height: 1536,
				expected: {
					default: { width: null, height: null }
				}
			},

			// Very small image
			{
				width: 15, height: 20,
				expected: {
					default: { width: null, height: null }
				}
			}
		];
		for ( let i = 0; i < exampleSizes.length; i++ ) {
			const cursize = exampleSizes[ i ];
			const opts = embed.getPossibleImageSizesForWikitext( cursize.width, cursize.height );
			assert.deepEqual( opts, cursize.expected, 'We got the expected results out of the size calculation function.' );
		}
	} );

	QUnit.test( 'set():', ( assert ) => {
		const embed = new Embed( $qf );
		const width = 15;
		const height = 20;

		const updateMenuOptions = Utils.updateMenuOptions;
		Utils.updateMenuOptions = function ( sizes, options ) {
			assert.strictEqual( options.length, 4, 'Options passed correctly.' );
		};
		embed.resetCurrentSizeMenuToDefault = function () {
			assert.true( true, 'resetCurrentSizeMenuToDefault() is called.' );
		};
		const getThumbnailUrlPromise = Utils.getThumbnailUrlPromise;
		Utils.getThumbnailUrlPromise = function () {
			return $.Deferred().resolve().promise();
		};
		embed.updateEmbedHtml = function () {
			assert.true( true, 'updateEmbedHtml() is called after data is collected.' );
		};

		assert.false( $.isPlainObject( embed.embedFileInfo ), 'embedFileInfo not set yet.' );

		embed.set( { width, height }, 'caption' );

		assert.true( $.isPlainObject( embed.embedFileInfo ), 'embedFileInfo set.' );

		Utils.updateMenuOptions = updateMenuOptions;
		Utils.getThumbnailUrlPromise = getThumbnailUrlPromise;
	} );

	QUnit.test( 'empty():', ( assert ) => {
		const embed = new Embed( $qf );
		const width = 15;
		const height = 20;

		embed.formatter = {
			getThumbnailWikitextFromEmbedFileInfo: function () {
				return 'wikitext';
			},
			getThumbnailHtml: function () {
				return 'html';
			}
		};

		embed.set( {}, {} );
		embed.updateEmbedHtml( { url: 'x' }, width, height );
		embed.updateEmbedWikitext( width );

		assert.notStrictEqual( embed.$embedTextHtml.val(), '', 'embedTextHtml is not empty.' );
		assert.notStrictEqual( embed.$embedTextWikitext.val(), '', 'embedTextWikitext is not empty.' );

		embed.empty();

		assert.strictEqual( embed.$embedTextHtml.val(), '', 'embedTextHtml is empty.' );
		assert.strictEqual( embed.$embedTextWikitext.val(), '', 'embedTextWikitext is empty.' );
		assert.strictEqual( embed.$embedSizeSwitchHtml.css( 'display' ), 'none', 'Html size menu should be hidden.' );
		assert.strictEqual( embed.$embedSizeSwitchWikitext.css( 'display' ), 'none', 'Wikitext size menu should be hidden.' );
	} );

	QUnit.test( 'attach()/unattach():', ( assert ) => {
		const embed = new Embed( $qf );
		const width = 15;
		const height = 20;

		embed.resetCurrentSizeMenuToDefault = () => {};

		embed.set( { width, height }, 'caption' );

		embed.handleTypeSwitch = function () {
			assert.true( false, 'handleTypeSwitch should not have been called.' );
		};
		embed.handleSizeSwitch = function () {
			assert.true( false, 'handleTypeSwitch should not have been called.' );
		};

		// Triggering action events before attaching should do nothing
		embed.$embedSizeSwitchHtml.trigger( 'change' );
		embed.$embedSizeSwitchWikitext.trigger( 'change' );

		embed.handleTypeSwitch = function () {
			assert.true( true, 'handleTypeSwitch was called.' );
		};
		embed.handleSizeSwitch = function () {
			assert.true( true, 'handleTypeSwitch was called.' );
		};

		embed.attach();

		// Action events should be handled now
		embed.$embedSizeSwitchHtml.trigger( 'change' );
		embed.$embedSizeSwitchWikitext.trigger( 'change' );

		// Test the unattach part
		embed.handleTypeSwitch = function () {
			assert.true( false, 'handleTypeSwitch should not have been called.' );
		};
		embed.handleSizeSwitch = function () {
			assert.true( false, 'handleTypeSwitch should not have been called.' );
		};

		embed.unattach();

		// Triggering action events now that we are unattached should do nothing
		embed.$embedSizeSwitchHtml.trigger( 'change' );
		embed.$embedSizeSwitchWikitext.trigger( 'change' );
	} );

	QUnit.test( 'handleTypeSwitch():', ( assert ) => {
		const embed = new Embed( $qf );

		embed.resetCurrentSizeMenuToDefault = function () {
			assert.true( true, 'resetCurrentSizeMenuToDefault() called.' );
		};

		// HTML selected
		embed.handleTypeSwitch( 'html' );

		assert.strictEqual( embed.$embedSizeSwitchWikitext.css( 'display' ), 'none', 'Wikitext size menu should be hidden.' );

		embed.resetCurrentSizeMenuToDefault = function () {
			assert.true( false, 'resetCurrentSizeMenuToDefault() should not have been called.' );
		};

		// Wikitext selected, we are done resetting defaults
		embed.handleTypeSwitch( 'wikitext' );

		assert.strictEqual( embed.$embedSizeSwitchHtml.css( 'display' ), 'none', 'HTML size menu should be hidden.' );
	} );

	QUnit.test( 'Logged out', ( assert ) => {
		const oldUserIsAnon = mw.user.isAnon;

		mw.user.isAnon = () => true;

		const embed = new Embed( $qf );

		embed.attach();

		assert.strictEqual( embed.$embedSizeSwitchWikitext.css( 'display' ), 'none', 'Wikitext widget should be hidden.' );
		assert.strictEqual( embed.$embedSizeSwitchHtml.css( 'display' ), '', 'HTML widget should be visible.' );
		assert.strictEqual( embed.$embedTextWikitextDiv.css( 'display' ), 'none', 'Wikitext input should be hidden.' );
		assert.strictEqual( embed.$embedTextHtmlDiv.css( 'display' ), '', 'HTML input should be visible.' );

		mw.user.isAnon = oldUserIsAnon;
	} );

}() );
