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

const { Api } = require( 'mmv' );

QUnit.module( 'mmv.provider.Api', QUnit.newMwEnvironment() );

QUnit.test( 'Api constructor sense check', ( assert ) => {
	const api = { get: function () {} };
	const options = {};
	const apiProvider = new Api( api, options );
	const ApiProviderWithNoOptions = new Api( api );

	assert.true( apiProvider instanceof Api );
	assert.true( ApiProviderWithNoOptions instanceof Api );
} );

QUnit.test( 'apiGetWithMaxAge()', function ( assert ) {
	const api = {};
	let options = {};
	let apiProvider = new Api( api, options );

	api.get = this.sandbox.stub();
	apiProvider.apiGetWithMaxAge( {} );
	assert.false( 'maxage' in api.get.getCall( 0 ).args[ 0 ], 'maxage is not set by default' );
	assert.false( 'smaxage' in api.get.getCall( 0 ).args[ 0 ], 'smaxage is not set by default' );

	options = { maxage: 123 };
	apiProvider = new Api( api, options );

	api.get = this.sandbox.stub();
	apiProvider.apiGetWithMaxAge( {} );
	assert.strictEqual( api.get.getCall( 0 ).args[ 0 ].maxage, 123, 'maxage falls back to provider default' );
	assert.strictEqual( api.get.getCall( 0 ).args[ 0 ].smaxage, 123, 'smaxage falls back to provider default' );

	api.get = this.sandbox.stub();
	apiProvider.apiGetWithMaxAge( {}, null, 456 );
	assert.strictEqual( api.get.getCall( 0 ).args[ 0 ].maxage, 456, 'maxage can be overridden' );
	assert.strictEqual( api.get.getCall( 0 ).args[ 0 ].smaxage, 456, 'smaxage can be overridden' );

	api.get = this.sandbox.stub();
	apiProvider.apiGetWithMaxAge( {}, null, null );
	assert.false( 'maxage' in api.get.getCall( 0 ).args[ 0 ], 'maxage can be overridden to unset' );
	assert.false( 'smaxage' in api.get.getCall( 0 ).args[ 0 ], 'smaxage can be overridden to unset' );
} );

QUnit.test( 'getCachedPromise success', async ( assert ) => {
	const api = { get: function () {} };
	const apiProvider = new Api( api );
	const logSpy = sinon.spy( mw, 'log' );

	let sourceCalled = 0;
	function promiseSource( result ) {
		return function () {
			sourceCalled++;
			return $.Deferred().resolve( result );
		};
	}

	assert.strictEqual(
		await apiProvider.getCachedPromise( 'foo', promiseSource( 1 ) ),
		1,
		'fresh foo result'
	);
	assert.strictEqual(
		await apiProvider.getCachedPromise( 'bar', promiseSource( 2 ) ),
		2,
		'fresh bar result'
	);

	sourceCalled = 0;
	assert.strictEqual(
		await apiProvider.getCachedPromise( 'foo', promiseSource( 3 ) ),
		1,
		'cached foo result'
	);
	assert.strictEqual( sourceCalled, 0, 'uncached calls' );

	assert.strictEqual( logSpy.callCount, 0, 'mw.log should not have been called' );
} );

QUnit.test( 'getCachedPromise failure', async ( assert ) => {
	const api = { get: function () {} };
	const apiProvider = new Api( api );
	const logSpy = sinon.spy( mw, 'log' );

	let sourceCalled = 0;
	function promiseSource( result ) {
		return function () {
			sourceCalled++;
			return $.Deferred().reject( result );
		};
	}

	await assert.rejects(
		apiProvider.getCachedPromise( 'foo', promiseSource( 1 ) ),
		( result ) => result === 1,
		'fresh foo rejection'
	);
	await assert.rejects(
		apiProvider.getCachedPromise( 'bar', promiseSource( 2 ) ),
		( result ) => result === 2,
		'fresh bar rejection'
	);

	sourceCalled = 0;
	await assert.rejects(
		apiProvider.getCachedPromise( 'foo', promiseSource( 3 ) ),
		( result ) => result === 1,
		'cached foo rejection'
	);
	assert.strictEqual( sourceCalled, 0, 'uncached calls' );

	assert.strictEqual( logSpy.called, true, 'mw.log was called' );
} );

QUnit.test( 'getErrorMessage', ( assert ) => {
	const api = { get: function () {} };
	const apiProvider = new Api( api );

	const errorMessage = apiProvider.getErrorMessage( {
		servedby: 'mw1194',
		error: {
			code: 'unknown_action',
			info: 'Unrecognized value for parameter \'action\': FOO'
		}
	} );
	assert.strictEqual( errorMessage,
		'unknown_action: Unrecognized value for parameter \'action\': FOO',
		'error message is parsed correctly' );

	assert.strictEqual( apiProvider.getErrorMessage( {} ), 'unknown error', 'missing error message is handled' );
} );

QUnit.test( 'getQueryPage', async ( assert ) => {
	const api = { get: function () {} };
	const apiProvider = new Api( api );
	const data = {
		query: {
			pages: [
				{
					title: 'File:Stuff.jpg'
				}
			]
		}
	};

	const field = await apiProvider.getQueryPage( data );
	assert.strictEqual( field, data.query.pages[ 0 ], 'specified page is found' );

	await assert.rejects( apiProvider.getQueryPage( {} ), 'data is missing' );
	await assert.rejects( apiProvider.getQueryPage( { data: { query: {} } } ), 'pages are missing' );
	await assert.rejects(
		apiProvider.getQueryPage( { data: { query: { pages: [] } } } ),
		'pages are empty'
	);
	await assert.rejects(
		apiProvider.getQueryPage( {
			query: {
				pages: [
					{
						title: 'File:Stuff.jpg'
					},
					{
						title: 'File:OtherStuff.jpg'
					}
				]
			}
		} ),
		'promise rejected when data contains two entries'
	);
} );
