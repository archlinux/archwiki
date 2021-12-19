( function () {

	QUnit.module( 've.dm.MWTemplateModel' );

	/**
	 * @return {ve.dm.MWTransclusionModel} but it's a mock
	 */
	function createTransclusionModel() {
		return {
			getUniquePartId: () => 0
		};
	}

	[
		[ {}, false, 'no parameters' ],
		[ { p1: {}, p2: { wt: 'foo' } }, true, 'multiple parameters' ],
		[ { p1: {} }, false, 'undefined' ],
		[ { p1: { wt: null } }, false, 'null' ],
		[ { p1: { wt: '' } }, false, 'empty string' ],
		[ { p1: { wt: ' ' } }, true, 'space' ],
		[ { p1: { wt: '0' } }, true, '0' ],
		[ { p1: { wt: '\nfoo' } }, true, 'newline' ]
	].forEach( ( [ params, expected, message ] ) =>
		QUnit.test( 'containsValuableData: ' + message, ( assert ) => {
			const data = { target: {}, params },
				model = ve.dm.MWTemplateModel.newFromData( createTransclusionModel(), data );

			assert.strictEqual( model.containsValuableData(), expected );
		} )
	);

}() );
