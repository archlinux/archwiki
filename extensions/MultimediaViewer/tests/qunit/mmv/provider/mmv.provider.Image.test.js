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

const { ImageProvider } = require( 'mmv' );

QUnit.module( 'mmv.provider.Image', QUnit.newMwEnvironment() );

QUnit.test( 'Image constructor sense check', ( assert ) => {
	const imageProvider = new ImageProvider();

	assert.true( imageProvider instanceof ImageProvider );
} );

QUnit.test( 'Image load success', async ( assert ) => {
	const url = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQAQMAAAAlPW0' +
			'iAAAABlBMVEUAAAD///+l2Z/dAAAAM0lEQVR4nGP4/5/h/1+G/58ZDrAz3D/McH' +
			'8yw83NDDeNGe4Ug9C9zwz3gVLMDA/A6P9/AFGGFyjOXZtQAAAAAElFTkSuQmCC';
	const imageProvider = new ImageProvider();

	imageProvider.imagePreloadingSupported = () => false;

	const image = await imageProvider.get( url );
	assert.true( image instanceof HTMLImageElement,
		'success handler was called with the image element' );
	assert.strictEqual( image.src, url, 'image src is correct' );
} );

QUnit.test( 'Image caching', async ( assert ) => {
	const url = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQAQMAAAAlPW0' +
			'iAAAABlBMVEUAAAD///+l2Z/dAAAAM0lEQVR4nGP4/5/h/1+G/58ZDrAz3D/McH' +
			'8yw83NDDeNGe4Ug9C9zwz3gVLMDA/A6P9/AFGGFyjOXZtQAAAAAElFTkSuQmCC';
	const url2 = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
	const imageProvider = new ImageProvider();

	imageProvider.imagePreloadingSupported = () => false;

	let image = await imageProvider.get( url );
	const result = image;
	assert.true( image instanceof HTMLImageElement,
		'success handler was called with the image element' );
	assert.strictEqual( image.src, url, 'image src is correct' );

	image = await imageProvider.get( url );
	assert.strictEqual( image, result, 'image element is cached and not regenerated' );
	assert.strictEqual( image.src, url, 'image src is correct' );

	image = await imageProvider.get( url2 );
	assert.notStrictEqual( image, result, 'image element for different url is not cached' );
	assert.strictEqual( image.src, url2, 'image src is correct' );
} );

QUnit.test( 'Image load fail', async ( assert ) => {
	const imageProvider = new ImageProvider();
	const logSpy = sinon.spy( mw, 'log' );

	imageProvider.imagePreloadingSupported = () => false;

	await assert.rejects( imageProvider.get( 'doesntexist.png' ) );
	assert.true( logSpy.called, 'mw.log was called' );
} );

QUnit.test( 'Image load with preloading supported', async ( assert ) => {
	const url = mw.config.get( 'wgExtensionAssetsPath' ) + '/MultimediaViewer/resources/mmv.ui.restriction/img/restrict-personality.svg';
	const imageProvider = new ImageProvider();
	const endsWith = function ( a, b ) {
		return a.indexOf( b ) === a.length - b.length;
	};

	imageProvider.imagePreloadingSupported = () => true;
	imageProvider.performance = {
		record: function () {
			return $.Deferred().resolve();
		}
	};

	const image = await imageProvider.get( url );
	// can't test equality as browsers transform this to a full URL
	assert.true( endsWith( image.src, url ), 'local image loaded with correct source' );
} );

QUnit.test( 'Failed image load with preloading supported', async ( assert ) => {
	const url = 'nosuchimage.png';
	const imageProvider = new ImageProvider();

	imageProvider.imagePreloadingSupported = () => true;
	imageProvider.performance = {
		record: function () {
			return $.Deferred().resolve();
		}
	};

	await assert.rejects( imageProvider.get( url ) );
} );

QUnit.test( 'imageQueryParameter', async ( assert ) => {
	const imageProvider = new ImageProvider( 'foo' );
	imageProvider.imagePreloadingSupported = () => false;
	let givenUrl;
	imageProvider.rawGet = function ( url ) {
		givenUrl = url;
		return $.Deferred().resolve();
	};

	await imageProvider.get( 'http://www.wikipedia.org/' );
	assert.strictEqual( givenUrl, 'http://www.wikipedia.org/?foo=', 'Extra parameter added' );
} );
