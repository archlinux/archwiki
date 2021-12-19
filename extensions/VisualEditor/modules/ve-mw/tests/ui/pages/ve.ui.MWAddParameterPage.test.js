QUnit.module( 've.ui.MWAddParameterPage', ve.test.utils.mwEnvironment );

QUnit.test( 'Input event handler', ( assert ) => {
	const transclusion = new ve.dm.MWTransclusionModel(),
		template = new ve.dm.MWTemplateModel( transclusion, {} ),
		parameter = new ve.dm.MWParameterModel( template ),
		page = new ve.ui.MWAddParameterPage( parameter );

	page.paramInputField.setValue( ' ' );
	page.onParameterNameSubmitted();
	assert.deepEqual( template.getParameters(), {}, 'empty input is ignored' );

	page.paramInputField.setValue( ' p1 ' );
	page.onParameterNameSubmitted();
	assert.ok( template.hasParameter( 'p1' ), 'input is trimmed and parameter added' );

	template.getParameter( 'p1' ).setValue( 'not empty' );
	page.paramInputField.setValue( 'p1' );
	page.onParameterNameSubmitted();
	assert.ok( template.getParameter( 'p1' ).getValue(), 'existing parameter is not replaced' );

	template.getSpec().setTemplateData( { params: { documented: {} } } );
	page.paramInputField.setValue( 'documented' );
	page.onParameterNameSubmitted();
	assert.notOk( template.hasParameter( 'documented' ), 'documented parameter is not added' );

} );

QUnit.test( 'Outline item initialization', ( assert ) => {
	const transclusion = new ve.dm.MWTransclusionModel(),
		template = new ve.dm.MWTemplateModel( transclusion, {} ),
		parameter = new ve.dm.MWParameterModel( template ),
		page = new ve.ui.MWAddParameterPage( parameter );

	page.setOutlineItem( new OO.ui.OutlineOptionWidget() );
	const outlineItem = page.getOutlineItem();

	assert.notOk( outlineItem.$element.children().length,
		'Outline item should be empty' );
	// eslint-disable-next-line no-jquery/no-class-state
	assert.notOk( outlineItem.$element.hasClass( 'oo-ui-outlineOptionWidget' ),
		'Outline item should not be styled' );
} );

[
	[ '', 0 ],
	[ 'a', 0 ],
	[ 'a=b', '(visualeditor-dialog-transclusion-add-param-error-forbidden-char: =)' ],
	[ 'x|a=b', '(visualeditor-dialog-transclusion-add-param-error-forbidden-char: |)' ],
	[ 'used', '(visualeditor-dialog-transclusion-add-param-error-exists-selected: used, used)' ],
	[ 'unused', '(visualeditor-dialog-transclusion-add-param-error-exists-unselected: unused, unused)' ],
	[ 'usedAlias', '(visualeditor-dialog-transclusion-add-param-error-alias: usedAlias, xLabel)' ],
	[ 'unusedAlias', '(visualeditor-dialog-transclusion-add-param-error-alias: unusedAlias, y)' ],
	[ 'usedAliasNoLabel', '(visualeditor-dialog-transclusion-add-param-error-alias: usedAliasNoLabel, usedAliasNoLabel)' ],
	[ 'usedDeprecated', '(visualeditor-dialog-transclusion-add-param-error-exists-selected: usedDeprecated, usedDeprecated)' ],
	[ 'unusedDeprecated', '(visualeditor-dialog-transclusion-add-param-error-deprecated: unusedDeprecated, unusedDeprecated)' ]
].forEach( ( [ input, expected ] ) =>
	QUnit.test( 'getValidationErrors: ' + input, ( assert ) => {
		const data = {
			target: {},
			params: {
				used: {},
				usedAlias: {},
				usedAliasNoLabel: {},
				usedDeprecated: {}
			}
		};

		const transclusion = new ve.dm.MWTransclusionModel(),
			template = ve.dm.MWTemplateModel.newFromData( transclusion, data ),
			parameter = new ve.dm.MWParameterModel( template ),
			page = new ve.ui.MWAddParameterPage( parameter );

		template.getSpec().setTemplateData( { params: {
				used: {},
				unused: {},
				x: { aliases: [ 'usedAlias' ], label: 'xLabel' },
				y: { aliases: [ 'unusedAlias' ] },
				z: { aliases: [ 'usedAliasNoLabel' ] },
				usedDeprecated: { deprecated: true },
				unusedDeprecated: { deprecated: true }
			} } );
		template.addParameter( parameter );

		const errors = page.getValidationErrors( input );
		assert.strictEqual( errors.length, expected ? 1 : 0 );
		if ( expected ) {
			assert.strictEqual( errors[ 0 ].text(), expected );
		}
	} )
);
