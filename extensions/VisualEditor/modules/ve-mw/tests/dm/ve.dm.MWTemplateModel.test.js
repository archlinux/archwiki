/*!
 * VisualEditor DataModel MWTemplateModel tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

( function () {
	const transclusionData = {
		params: {
			foo: { wt: 'Foo value' },
			bar: { wt: 'Bar value' },
			empty: { wt: '' },
			'': { wt: '' }
		},
		target: {
			href: './Template:Test',
			wt: 'Test'
		}
	};

	QUnit.module( 've.dm.MWTemplateModel' );

	/**
	 * Create a new MWTemplateModel initialized with a static transclusion data fixture.
	 *
	 * @return {ve.dm.MWTemplateModel}
	 */
	function newTemplateModel() {
		const doc = ve.dm.Document.static.newBlankDocument(),
			transclusion = new ve.dm.MWTransclusionModel( doc ),
			clonedTransclusionData = ve.extendObject( {}, transclusionData );

		return ve.dm.MWTemplateModel.newFromData( transclusion, clonedTransclusionData );
	}

	/* Tests */

	[
		[ undefined, null ],
		[ '', null ],
		[ 'no_prefix', 'no prefix' ],
		[ '/unexpected_prefix', '/unexpected prefix' ],
		[ './Template:%C3%9Cnicode%5Fexample/subpage', 'Template:Ünicode example/subpage' ],
		[ './Template:Possibly_invalid%5B%5D', 'Template:Possibly invalid[]' ]
	].forEach( ( [ href, expected ] ) =>
		QUnit.test( 'getTitle: ' + href, ( assert ) => {
			const transclusion = { getUniquePartId: () => 0 },
				template = new ve.dm.MWTemplateModel( transclusion, { href } );
			assert.strictEqual( template.getTitle(), expected );
		} )
	);

	QUnit.test( 'hasParameter', ( assert ) => {
		const template = newTemplateModel();

		// All parameters are primary as long as the TemplateData documentation isn't known
		assert.strictEqual( template.hasParameter( 'bar' ), true );
		assert.strictEqual( template.hasParameter( 'resolved-bar' ), false );
		assert.strictEqual( template.hasParameter( 'alternative-bar' ), false );

		template.getSpec().setTemplateData( { params: {
			'resolved-bar': { aliases: [ 'bar', 'alternative-bar' ] }
		} } );

		// Now "bar" and "alternative-bar" are aliases, and "resolved-bar" is the primary name
		assert.strictEqual( template.hasParameter( 'bar' ), true );
		assert.strictEqual( template.hasParameter( 'resolved-bar' ), true );
		assert.strictEqual( template.hasParameter( 'alternative-bar' ), true );
	} );

	QUnit.test( 'getOriginalParameterName', ( assert ) => {
		const template = newTemplateModel();
		template.addParameter( new ve.dm.MWParameterModel( template, 'p1' ) );
		template.addParameter( new ve.dm.MWParameterModel( template, 'p2-alias' ) );

		// These are all independent parameters as long as we don't know anything about aliases
		assert.strictEqual( template.getOriginalParameterName( 'p1' ), 'p1' );
		assert.strictEqual( template.getOriginalParameterName( 'p1-alias' ), 'p1-alias' );
		assert.strictEqual( template.getOriginalParameterName( 'p2' ), 'p2' );
		assert.strictEqual( template.getOriginalParameterName( 'p2-alias' ), 'p2-alias' );
		assert.strictEqual( template.getOriginalParameterName( 'p3' ), 'p3' );
		assert.strictEqual( template.getOriginalParameterName( 'p3-alias' ), 'p3-alias' );

		template.getSpec().setTemplateData( { params: {
			p1: { aliases: [ 'p1-alias' ] },
			p2: { aliases: [ 'p2-alias' ] },
			p3: { aliases: [ 'p3-alias' ] }
		} } );

		assert.strictEqual( template.getOriginalParameterName( 'p1' ), 'p1' );
		assert.strictEqual( template.getOriginalParameterName( 'p1-alias' ), 'p1' );
		assert.strictEqual( template.getOriginalParameterName( 'p2' ), 'p2-alias' );
		assert.strictEqual( template.getOriginalParameterName( 'p2-alias' ), 'p2-alias' );
		assert.strictEqual( template.getOriginalParameterName( 'p3' ), 'p3' );
		assert.strictEqual( template.getOriginalParameterName( 'p3-alias' ), 'p3' );
	} );

	QUnit.test( 'serialize input parameters', ( assert ) => {
		const template = newTemplateModel();

		const serialization = template.serialize();
		assert.deepEqual( serialization, { template: {
			params: {
				foo: { wt: 'Foo value' },
				bar: { wt: 'Bar value' },
				empty: { wt: '' }
			},
			target: { href: './Template:Test', wt: 'Test' }
		} } );
	} );

	QUnit.test( 'serialize changed input parameters', ( assert ) => {
		const template = newTemplateModel(),
			newParameter = new ve.dm.MWParameterModel( template, 'baz', 'Baz value' );

		template.addParameter( newParameter );

		const serialization = template.serialize();
		assert.deepEqual( serialization.template.params.baz, { wt: 'Baz value' } );
	} );

	// T75134
	QUnit.test( 'serialize after parameter was removed', ( assert ) => {
		const template = newTemplateModel(),
			existingParameter = template.getParameter( 'bar' );

		template.removeParameter( existingParameter );

		const serialization = template.serialize();
		assert.deepEqual( serialization.template.params, {
			foo: { wt: 'Foo value' },
			empty: { wt: '' }
		} );
	} );

	// T101075
	QUnit.test( 'serialize without empty parameter not present in original parameter set', ( assert ) => {
		const template = newTemplateModel(),
			parameterWithoutValue = new ve.dm.MWParameterModel( template, 'new_empty', '' );

		template.addParameter( parameterWithoutValue );

		const serialization = template.serialize();
		assert.deepEqual( serialization.template.params, {
			foo: { wt: 'Foo value' },
			bar: { wt: 'Bar value' },
			empty: { wt: '' }
		} );
	} );

	[
		{
			name: 'serialize with explicit parameter order',
			spec: {
				params: {
					foo: {},
					empty: {},
					bar: {}
				},
				paramOrder: [ 'bar', 'foo', 'empty' ]
			},
			expected: [ 'foo', 'bar', 'empty' ]
		},
		{
			name: 'serialize with no parameter order',
			spec: {
				params: {
					foo: {},
					empty: {},
					bar: {}
				}
			},
			expected: [ 'foo', 'bar', 'empty' ]
		},
		{
			name: 'serialize with aliases',
			spec: {
				params: {
					foo: {},
					empty: {},
					hasaliases: { aliases: [ 'bar', 'baz' ] }
				}
			},
			expected: [ 'foo', 'bar', 'empty' ]
		},
		{
			name: 'serialize with unknown params',
			spec: {
				params: {
					bar: {}
				}
			},
			expected: [ 'foo', 'bar', 'empty' ]
		}
	].forEach( ( { name, spec, expected } ) =>
		QUnit.test( name, ( assert ) => {
			const template = newTemplateModel();

			template.getSpec().setTemplateData( spec );

			const serialization = template.serialize();
			assert.deepEqual( Object.keys( serialization.template.params ), expected );
		} )
	);

	[
		{
			name: 'no spec retrieved',
			spec: null,
			expected: [
				'bar',
				'empty',
				'foo',
				''
			]
		},
		{
			name: 'empty spec',
			spec: {},
			expected: [
				'bar',
				'empty',
				'foo',
				''
			]
		},
		{
			name: 'spec with explicit paramOrder and all known params',
			spec: {
				params: {
					bar: {},
					empty: {},
					unused: {},
					foo: {}
				},
				paramOrder: [ 'foo', 'empty', 'bar', 'unused' ]
			},
			expected: [
				'foo',
				'empty',
				'bar',
				''
			]
		},
		{
			name: 'spec with explicit paramOrder and some unknown params',
			spec: {
				params: {
					empty: {},
					unused: {},
					foo: {}
				},
				paramOrder: [ 'foo', 'empty', 'unused' ]
			},
			expected: [
				'foo',
				'empty',
				'bar',
				''
			]
		},
		{
			name: 'spec with explicit paramOrder but all unknown params',
			spec: {
				params: {},
				paramOrder: []
			},
			expected: [
				'bar',
				'empty',
				'foo',
				''
			]
		},
		{
			name: 'spec with no paramOrder, all known params',
			spec: {
				params: {
					bar: {},
					foo: {},
					unused: {},
					empty: {}
				}
			},
			expected: [
				'bar',
				'foo',
				'empty',
				''
			]
		},
		{
			name: 'spec with no paramOrder and some unknown params',
			spec: {
				params: {
					empty: {},
					unused: {},
					foo: {}
				}
			},
			expected: [
				'empty',
				'foo',
				'bar',
				''
			]
		}
	].forEach( ( { name, spec, expected } ) =>
		QUnit.test( 'getOrderedParameterNames: ' + name, ( assert ) => {
			const template = newTemplateModel();

			if ( spec ) {
				template.getSpec().setTemplateData( spec );
			}

			assert.deepEqual( template.getOrderedParameterNames(), expected );
		} )
	);

	[
		{
			name: 'no spec retrieved',
			spec: null,
			expected: [
				'bar',
				'empty',
				'foo',
				''
			]
		},
		{
			name: 'spec with explicit paramOrder and all known params',
			spec: {
				params: {
					bar: {},
					empty: {},
					unused: {},
					foo: {}
				},
				paramOrder: [ 'foo', 'empty', 'unused', 'bar' ]
			},
			expected: [
				'foo',
				'empty',
				'unused',
				'bar',
				''
			]
		},
		{
			name: 'spec with explicit paramOrder and some unknown params',
			spec: {
				params: {
					empty: {},
					unused: {},
					foo: {}
				},
				paramOrder: [ 'foo', 'empty', 'unused' ]
			},
			expected: [
				'foo',
				'empty',
				'unused',
				'bar',
				''
			]
		},
		{
			name: 'spec with explicit paramOrder but all unknown params',
			spec: {
				params: {},
				paramOrder: []
			},
			expected: [
				'bar',
				'empty',
				'foo',
				''
			]
		},
		{
			name: 'spec with no paramOrder, all known params',
			spec: {
				params: {
					bar: {},
					foo: {},
					unused: {},
					empty: {}
				}
			},
			expected: [
				'bar',
				'foo',
				'unused',
				'empty',
				''
			]
		},
		{
			name: 'spec with no paramOrder and some unknown params',
			spec: {
				params: {
					empty: {},
					unused: {},
					foo: {}
				}
			},
			expected: [
				'empty',
				'unused',
				'foo',
				'bar',
				''
			]
		},
		{
			name: 'spec with explicit paramOrder and aliases',
			spec: {
				params: {
					empty: {},
					unused: {},
					hasalias: {
						aliases: [ 'bar', 'baz' ]
					}
				},
				paramOrder: [ 'hasalias', 'empty', 'unused' ]
			},
			expected: [
				'bar',
				'empty',
				'unused',
				'foo',
				''
			]
		}
	].forEach( ( { name, spec, expected } ) =>
		QUnit.test( 'getAllParametersOrdered: ' + name, ( assert ) => {
			const template = newTemplateModel();

			if ( spec ) {
				template.getSpec().setTemplateData( spec );
			}

			assert.deepEqual( template.getAllParametersOrdered(), expected );
		} )
	);

}() );
