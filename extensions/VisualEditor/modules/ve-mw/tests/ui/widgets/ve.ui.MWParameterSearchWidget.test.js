{
	/**
	 * @param {string[]} [knownParameters]
	 * @return {ve.dm.MWTemplateModel} but it's a mock
	 */
	const makeTemplateMock = function ( knownParameters ) {
		const spec = {
			getKnownParameterNames: () => knownParameters || [],
			getParameterLabel: () => '',
			getParameterAliases: () => [],
			getParameterDescription: () => '',
			isParameterDeprecated: () => false
		};
		return {
			connect: () => {},
			getSpec: () => spec,
			hasParameter: () => false
		};
	};

	QUnit.module( 've.ui.MWParameterSearchWidget' );

	QUnit.test( 'Forbidden characters in parameter names', ( assert ) => {
		const template = makeTemplateMock(),
			widget = new ve.ui.MWParameterSearchWidget( template );

		widget.query.setValue( '{{|p=}}' );
		widget.addResults();
		const items = widget.results.getItems();

		assert.strictEqual( items.length, 1 );
		assert.strictEqual( items[ 0 ].getData().name, 'p' );
	} );

	QUnit.test( 'Unknown parameter partly matches a known parameter', ( assert ) => {
		const template = makeTemplateMock( [ 'abbreviation' ] ),
			widget = new ve.ui.MWParameterSearchWidget( template );

		widget.query.setValue( 'abbr' );
		widget.addResults();
		const items = widget.results.getItems();

		assert.strictEqual( items.length, 2 );
		assert.strictEqual( items[ 0 ].getData().name, 'abbr' );
		assert.true( items[ 0 ].getData().isUnknown );
		assert.strictEqual( items[ 1 ].getData().name, 'abbreviation' );
	} );

}
