/*!
 * VisualEditor DataModel MWTransclusionModel tests.
 *
 * @copyright See AUTHORS.txt
 */

{
	QUnit.module( 've.dm.MWTransclusionModel', ve.test.utils.newMwEnvironment( {
		beforeEach() {
			// Mock XHR for mw.Api()
			this.server = this.sandbox.useFakeServer();
			this.server.respondImmediately = true;

		}
	} ) );

	const runAddPartTest = function ( assert, name, response, server, callback ) {
		const doc = ve.dm.Document.static.newBlankDocument(),
			transclusion = new ve.dm.MWTransclusionModel( doc ),
			part = ve.dm.MWTemplateModel.newFromName( transclusion, name ),
			done = assert.async();

		server.respondWith( [ 200, { 'Content-Type': 'application/json' }, JSON.stringify( response ) ] );

		transclusion.addPart( part )
			.then( () => {
				callback( transclusion );
			} )
			.always( () => {
				done();
			} );
	};

	const runBatchTest = function ( assert, names, response, server, callback ) {
		const transclusion = new ve.dm.MWTransclusionModel( ve.dm.Document.static.newBlankDocument() ),
			done = assert.async();

		server.respondWith( [ 200, { 'Content-Type': 'application/json' }, JSON.stringify( response ) ] );

		// The parts are added in the same tick, so they share one API request
		const promises = names.map(
			( name ) => transclusion.addPart( ve.dm.MWTemplateModel.newFromName( transclusion, name ) )
		);

		ve.promiseAll( promises )
			.then( () => {
				callback( transclusion );
			} )
			.always( () => {
				done();
			} );
	};

	QUnit.test( 'nextUniquePartId', ( assert ) => {
		const transclusion = new ve.dm.MWTransclusionModel();
		assert.strictEqual( transclusion.nextUniquePartId(), 'part_0' );
		assert.strictEqual( transclusion.nextUniquePartId(), 'part_1' );
		assert.strictEqual( transclusion.nextUniquePartId(), 'part_2' );
	} );

	QUnit.test( 'fetch template part data', function ( assert ) {
		const response = {
			batchcomplete: '',
			pages: {
				1331311: {
					title: 'Template:Test',
					description: { en: 'MWTransclusionModel template test' },
					params: {
						test: {
							label: { en: 'Test param' },
							type: 'string',
							description: { en: 'This is a test param' },
							required: false,
							suggested: false,
							example: null,
							deprecated: false,
							aliases: [],
							autovalue: null,
							default: null
						}
					},
					paramOrder: [ 'test' ],
					format: 'inline',
					sets: [],
					maps: {}
				}
			}
		};

		runAddPartTest( assert, 'Test', response, this.server, ( transclusion ) => {
			const parts = transclusion.getParts(),
				spec = parts[ 0 ].getSpec();

			assert.strictEqual( parts.length, 1 );
			assert.strictEqual( spec.getDescription( 'en' ), 'MWTransclusionModel template test' );
			assert.strictEqual( spec.getParameterLabel( 'test', 'en' ), 'Test param' );
			// ve.ui.MWTemplatePage needs this for the TemplateDiscovery favorite button
			assert.strictEqual( spec.templateData.pageId, '1331311' );
			// The "missing" and "notemplatedata" flags are falsy in formatversion=1
			assert.true( this.server.requests[ 0 ].url.includes( 'formatversion=2' ) );
		} );
	} );

	// T243868
	QUnit.test( 'fetch part data for parameterized template with no TemplateData', function ( assert ) {
		const response = {
			batchcomplete: '',
			pages: {
				1331311: {
					title: 'Template:NoData',
					notemplatedata: true,
					params: {
						foo: [],
						bar: []
					}
				}
			}
		};

		runAddPartTest( assert, 'NoData', response, this.server, ( transclusion ) => {
			const parts = transclusion.getParts(),
				spec = parts[ 0 ].getSpec();

			assert.strictEqual( parts.length, 1 );
			assert.deepEqual( spec.getKnownParameterNames(), [ 'foo', 'bar' ] );
		} );
	} );

	QUnit.test( 'fetch part data for template with no TemplateData and no params', function ( assert ) {
		const response = {
			batchcomplete: '',
			pages: {
				1331311: {
					title: 'Template:NoParams',
					notemplatedata: true,
					params: []
				}
			}
		};

		runAddPartTest( assert, 'NoParams', response, this.server, ( transclusion ) => {
			const parts = transclusion.getParts(),
				spec = parts[ 0 ].getSpec();

			assert.strictEqual( parts.length, 1 );
			assert.deepEqual( spec.getKnownParameterNames(), [] );
		} );
	} );

	// T419323
	QUnit.test( 'fetch part data for a redirected template', function ( assert ) {
		const response = {
			batchcomplete: '',
			// This API reports redirects at the top level, not below "query"
			redirects: [ { from: 'Template:Redirect', to: 'Template:Test' } ],
			pages: {
				1331311: {
					title: 'Template:Test',
					description: { en: 'MWTransclusionModel template test' },
					params: {}
				}
			}
		};

		runAddPartTest( assert, 'Redirect', response, this.server, ( transclusion ) => {
			const spec = transclusion.getParts()[ 0 ].getSpec();

			assert.strictEqual( spec.getDescription( 'en' ), 'MWTransclusionModel template test' );
		} );
	} );

	// T162694
	QUnit.test( 'fetch part data for a missing template', function ( assert ) {
		const response = {
			batchcomplete: '',
			pages: {
				'-1': {
					title: 'Template:Missing',
					missing: true
				}
			}
		};

		runAddPartTest( assert, 'Missing', response, this.server, ( transclusion ) => {
			const spec = transclusion.getParts()[ 0 ].getSpec();

			assert.deepEqual(
				ve.init.platform.linkCache.getCached( '_missing/Template:Missing' ),
				{ missing: true },
				'the transclusion dialog can report the template as missing'
			);
			// A missing page has no usable page id
			assert.strictEqual( spec.templateData.pageId, undefined );
		} );
	} );

	QUnit.test( 'fetch part data for two redirects to the same template', function ( assert ) {
		const response = {
			batchcomplete: '',
			redirects: [
				{ from: 'Template:Redirect', to: 'Template:Test' },
				{ from: 'Template:OtherRedirect', to: 'Template:Test' }
			],
			pages: {
				1331311: {
					title: 'Template:Test',
					description: { en: 'MWTransclusionModel template test' },
					params: {}
				}
			}
		};

		runBatchTest( assert, [ 'Redirect', 'OtherRedirect' ], response, this.server, ( transclusion ) => {
			const parts = transclusion.getParts();

			assert.strictEqual( parts.length, 2 );
			parts.forEach( ( part, i ) => {
				assert.strictEqual(
					part.getSpec().getDescription( 'en' ),
					'MWTransclusionModel template test',
					'redirect ' + i + ' has TemplateData'
				);
			} );
		} );
	} );

	QUnit.test( 'a title the API does not answer does not hold back the batch', function ( assert ) {
		const response = {
			batchcomplete: '',
			pages: {
				1331311: {
					title: 'Template:Test',
					description: { en: 'MWTransclusionModel template test' },
					params: {}
				}
			}
		};

		runBatchTest( assert, [ 'Test', 'Unanswered' ], response, this.server, ( transclusion ) => {
			const parts = transclusion.getParts();

			assert.strictEqual( parts.length, 2 );
			assert.strictEqual(
				parts[ 0 ].getSpec().getDescription( 'en' ),
				'MWTransclusionModel template test'
			);
			assert.false( parts[ 1 ].getSpec().isDocumented() );
		} );
	} );
}
