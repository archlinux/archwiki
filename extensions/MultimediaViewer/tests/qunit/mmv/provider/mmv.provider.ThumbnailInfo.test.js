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

const { ThumbnailInfo } = require( 'mmv' );

( function () {
	QUnit.module( 'mmv.provider.ThumbnailInfo', QUnit.newMwEnvironment( {
		// mw.Title relies on these three config vars
		// Restore them after each test run
		config: {
			wgFormattedNamespaces: {
				'-2': 'Media',
				'-1': 'Special',
				0: '',
				1: 'Talk',
				2: 'User',
				3: 'User talk',
				4: 'Wikipedia',
				5: 'Wikipedia talk',
				6: 'File',
				7: 'File talk',
				8: 'MediaWiki',
				9: 'MediaWiki talk',
				10: 'Template',
				11: 'Template talk',
				12: 'Help',
				13: 'Help talk',
				14: 'Category',
				15: 'Category talk',
				// testing custom / localized namespace
				100: 'Penguins'
			},
			wgNamespaceIds: {
				/* eslint-disable camelcase */
				media: -2,
				special: -1,
				'': 0,
				talk: 1,
				user: 2,
				user_talk: 3,
				wikipedia: 4,
				wikipedia_talk: 5,
				file: 6,
				file_talk: 7,
				mediawiki: 8,
				mediawiki_talk: 9,
				template: 10,
				template_talk: 11,
				help: 12,
				help_talk: 13,
				category: 14,
				category_talk: 15,
				image: 6,
				image_talk: 7,
				project: 4,
				project_talk: 5,
				// Testing custom namespaces and aliases
				penguins: 100,
				antarctic_waterfowl: 100
				/* eslint-enable camelcase */
			},
			wgCaseSensitiveNamespaces: []
		}
	} ) );

	QUnit.test( 'ThumbnailInfo constructor sense check', ( assert ) => {
		const api = { get: function () {} };
		const thumbnailInfoProvider = new ThumbnailInfo( api );

		assert.true( thumbnailInfoProvider instanceof ThumbnailInfo );
	} );

	QUnit.test( 'ThumbnailInfo get test', function ( assert ) {
		const api = { get: this.sandbox.stub().returns( $.Deferred().resolve( {
			query: {
				pages: [
					{
						ns: 6,
						title: 'File:Stuff.jpg',
						missing: true,
						imagerepository: 'shared',
						imageinfo: [
							{
								thumburl: 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/19/Stuff.jpg/51px-Stuff.jpg',
								thumbwidth: 95,
								thumbheight: 200,
								url: 'https://upload.wikimedia.org/wikipedia/commons/1/19/Stuff.jpg',
								descriptionurl: 'https://commons.wikimedia.org/wiki/File:Stuff.jpg'
							}
						]
					}
				]
			}
		} ) ) };
		const file = new mw.Title( 'File:Stuff.jpg' );
		const thumbnailInfoProvider = new ThumbnailInfo( api );

		return thumbnailInfoProvider.get( file, '', 100 ).then( ( thumbnail ) => {
			assert.strictEqual( thumbnail.url,
				'https://upload.wikimedia.org/wikipedia/commons/thumb/1/19/Stuff.jpg/51px-Stuff.jpg',
				'URL is set correctly' );
			assert.strictEqual( thumbnail.width, 95, 'actual width is set correctly' );
			assert.strictEqual( thumbnail.height, 200, 'actual height is set correctly' );
		} ).then( () => {
			assert.strictEqual( api.get.calledOnce, true );
			assert.deepEqual( api.get.getCall( 0 ).args[ 0 ], {
				formatversion: 2,
				action: 'query',
				prop: 'imageinfo',
				titles: 'File:Stuff.jpg',
				iiprop: 'url',
				iiurlheight: undefined,
				iiurlparam: undefined,
				iiurlwidth: 100
			} );
			// call the data provider a second time to check caching
			return thumbnailInfoProvider.get( file, '', 100 );
		} ).then( () => {
			assert.strictEqual( api.get.calledOnce, true );
			// call a third time with different size to check caching
			return thumbnailInfoProvider.get( file, '', 110 );
		} ).then( () => {
			assert.strictEqual( api.get.calledTwice, true );
			// call it again, with a height specified, to check caching
			return thumbnailInfoProvider.get( file, '', 110, 100 );
		} ).then( () => {
			assert.strictEqual( api.get.calledThrice, true );
			// call it again, with multi lingual SVG
			const file2 = new mw.Title( 'File:Multilingual_SVG_example.svg' );
			const sampleUrl = 'https://upload.wikimedia.org/wikipedia/commons/thumb/1/1e/Multilingual_SVG_example.svg/langit-120px-Multilingual_SVG_example.svg.png';
			return thumbnailInfoProvider.get( file2, sampleUrl, 100 );
		} ).then( () => {
			assert.deepEqual( api.get.getCall( api.get.callCount - 1 ).args[ 0 ], {
				action: 'query',
				formatversion: 2,
				iiprop: 'url',
				iiurlheight: undefined,
				iiurlparam: 'langit-100px',
				iiurlwidth: 100,
				prop: 'imageinfo',
				titles: 'File:Multilingual_SVG_example.svg'
			} );
		} );
	} );

	QUnit.test( 'ThumbnailInfo fail test', ( assert ) => {
		const api = { get: function () {
			return $.Deferred().resolve( {} );
		} };
		const file = new mw.Title( 'File:Stuff.jpg' );
		const done = assert.async();
		const thumbnailInfoProvider = new ThumbnailInfo( api );

		thumbnailInfoProvider.get( file, '', 100 ).fail( () => {
			assert.true( true, 'promise rejected when no data is returned' );
			done();
		} );
	} );

	QUnit.test( 'ThumbnailInfo fail test 2', ( assert ) => {
		const api = { get: function () {
			return $.Deferred().resolve( {
				query: {
					pages: [
						{
							title: 'File:Stuff.jpg'
						}
					]
				}
			} );
		} };
		const file = new mw.Title( 'File:Stuff.jpg' );
		const done = assert.async();
		const thumbnailInfoProvider = new ThumbnailInfo( api );

		thumbnailInfoProvider.get( file, '', 100 ).fail( () => {
			assert.true( true, 'promise rejected when imageinfo is missing' );
			done();
		} );
	} );

	QUnit.test( 'ThumbnailInfo missing page test', ( assert ) => {
		const api = { get: function () {
			return $.Deferred().resolve( {
				query: {
					pages: [
						{
							title: 'File:Stuff.jpg',
							missing: true,
							imagerepository: ''
						}
					]
				}
			} );
		} };
		const file = new mw.Title( 'File:Stuff.jpg' );
		const done = assert.async();
		const thumbnailInfoProvider = new ThumbnailInfo( api );

		thumbnailInfoProvider.get( file, '' ).fail( ( errorMessage ) => {
			assert.strictEqual( errorMessage, 'file does not exist: File:Stuff.jpg',
				'error message is set correctly for missing file' );
			done();
		} );
	} );

	QUnit.test( 'ThumbnailInfo fail test 3', ( assert ) => {
		const api = { get: function () {
			return $.Deferred().resolve( {
				query: {
					pages: [
						{
							title: 'File:Stuff.jpg',
							imageinfo: [
								{}
							]
						}
					]
				}
			} );
		} };
		const file = new mw.Title( 'File:Stuff.jpg' );
		const done = assert.async();
		const thumbnailInfoProvider = new ThumbnailInfo( api );

		thumbnailInfoProvider.get( file, '', 100 ).fail( () => {
			assert.true( true, 'promise rejected when thumbnail info is missing' );
			done();
		} );
	} );
}() );
