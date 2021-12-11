/*!
 * VisualEditor DataModel MWTemplateModel tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

( function () {
	var transclusionData = {
		params: {
			foo: { wt: 'Foo value' },
			bar: { wt: 'Bar value' },
			empty: { wt: '' }
		},
		target: {
			href: './Template:Test',
			wt: 'Test'
		}
	};

	QUnit.module( 've.dm.MWTemplateModel', ve.test.utils.mwEnvironment );

	/**
	 * Create a new MWTemplateModel initialized with a static transclusion data fixture.
	 *
	 * @return {ve.dm.MWTemplateModel}
	 */
	function newTemplateModel() {
		var doc = ve.dm.Document.static.newBlankDocument(),
			transclusion = new ve.dm.MWTransclusionModel( doc ),
			clonedTransclusionData = ve.extendObject( {}, transclusionData );

		return ve.dm.MWTemplateModel.newFromData( transclusion, clonedTransclusionData );
	}

	/* Tests */

	QUnit.test( 'serialize input parameters', function ( assert ) {
		var templateModel = newTemplateModel(),
			serializedTransclusionData = templateModel.serialize();

		assert.deepEqual( serializedTransclusionData, { template: transclusionData } );
	} );

	QUnit.test( 'serialize changed input parameters', function ( assert ) {
		var templateModel = newTemplateModel(),
			newParameterModel = new ve.dm.MWParameterModel( templateModel, 'baz', 'Baz value' ),
			serializedTransclusionData;

		templateModel.addParameter( newParameterModel );

		serializedTransclusionData = templateModel.serialize();

		assert.deepEqual( serializedTransclusionData.template.params.baz, { wt: 'Baz value' } );
	} );

	// T75134
	QUnit.test( 'serialize after parameter was removed', function ( assert ) {
		var templateModel = newTemplateModel(),
			barParam = templateModel.getParameter( 'bar' ),
			serializedTransclusionData;

		templateModel.removeParameter( barParam );

		serializedTransclusionData = templateModel.serialize();

		assert.deepEqual( serializedTransclusionData.template.params, { foo: { wt: 'Foo value' }, empty: { wt: '' } } );
	} );

	// T101075
	QUnit.test( 'serialize without empty parameter not present in original parameter set', function ( assert ) {
		var templateModel = newTemplateModel(),
			newEmptyParam = new ve.dm.MWParameterModel( templateModel, 'new_empty', '' ),
			serializedTransclusionData;

		templateModel.addParameter( newEmptyParam );

		serializedTransclusionData = templateModel.serialize();

		assert.deepEqual( serializedTransclusionData, { template: transclusionData } );
	} );
}() );
