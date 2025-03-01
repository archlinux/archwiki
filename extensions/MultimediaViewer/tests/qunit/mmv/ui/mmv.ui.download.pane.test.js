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

const { Download: DownloadPane, Utils } = require( 'mmv.ui.reuse' );

( function () {
	QUnit.module( 'mmv.ui.download.pane', QUnit.newMwEnvironment() );

	QUnit.test( 'Sense test, object creation and UI construction', ( assert ) => {
		const download = new DownloadPane( $( '#qunit-fixture' ) );

		assert.true( download instanceof DownloadPane, 'download UI element is created.' );
		assert.strictEqual( download.$downloadButton.length, 1, 'Download button created.' );
		assert.strictEqual( download.$downloadSizeMenu.length, 1, 'Image size pulldown menu created.' );
		assert.strictEqual( download.$downloadSizeMenu.children().length, 5, 'Image size pulldown menu created.' );
		assert.strictEqual( download.$previewLink.length, 1, 'Preview link created.' );

		assert.strictEqual( download.$downloadButton.attr( 'href' ), undefined, 'Button href is empty.' );
		assert.strictEqual( download.$previewLink.attr( 'href' ), undefined, 'Preview link href is empty.' );
	} );

	QUnit.test( 'set()/empty():', ( assert ) => {
		const download = new DownloadPane( $( '#qunit-fixture' ) );
		const src = 'https://upload.wikimedia.org/wikipedia/commons/3/3a/Foobar.jpg';
		const image = { // fake ImageModel
			title: new mw.Title( 'File:Foobar.jpg' ),
			url: src
		};

		assert.strictEqual( download.imageExtension, undefined, 'Image extension is not set.' );

		const updateMenuOptions = Utils.updateMenuOptions;
		Utils.updateMenuOptions = function () {
			assert.true( true, 'Menu options updated.' );
		};

		download.setAttributionText = () => {};
		download.set( image );

		assert.strictEqual( download.imageExtension, 'jpg', 'Image extension is set correctly.' );

		download.empty();

		assert.strictEqual( download.imageExtension, undefined, 'Image extension is not set.' );

		Utils.updateMenuOptions = updateMenuOptions;
	} );

	QUnit.test( 'handleSizeSwitch():', ( assert ) => {
		const download = new DownloadPane( $( '#qunit-fixture' ) );
		const newImageUrl = 'https://upload.wikimedia.org/wikipedia/commons/3/3a/NewFoobar.jpg';

		const getThumbnailUrlPromise = Utils.getThumbnailUrlPromise;
		Utils.getThumbnailUrlPromise = function () {
			return $.Deferred().resolve( { url: newImageUrl } ).promise();
		};

		download.setDownloadUrl = function ( url ) {
			assert.strictEqual( url, newImageUrl, 'URL passed to setDownloadUrl is correct' );
		};

		download.handleSizeSwitch();

		download.image = { url: newImageUrl };

		Utils.getThumbnailUrlPromise = function () {
			assert.true( false, 'Should not fetch the thumbnail if the image is original size.' );
		};

		download.handleSizeSwitch();

		Utils.getThumbnailUrlPromise = getThumbnailUrlPromise;
	} );

	QUnit.test( 'getExtensionFromUrl():', ( assert ) => {
		const download = new DownloadPane( $( '#qunit-fixture' ) );

		assert.strictEqual( download.getExtensionFromUrl( 'http://example.com/bing/foo.bar.png' ),
			'png', 'Extension is parsed correctly' );
	} );

	QUnit.test( 'setDownloadUrl', ( assert ) => {
		const download = new DownloadPane( $( '#qunit-fixture' ) );
		const imageUrl = 'https://upload.wikimedia.org/wikipedia/commons/3/3a/NewFoobar.jpg';

		download.setDownloadUrl( imageUrl );

		assert.strictEqual( download.$downloadButton.attr( 'href' ), imageUrl + '?download', 'Download link is set correctly.' );
		assert.strictEqual( download.$previewLink.attr( 'href' ), imageUrl, 'Preview link is set correctly.' );
		assert.strictEqual( download.$downloadButton.hasClass( 'disabledLink' ), false, 'Download link is enabled.' );
	} );
}() );
