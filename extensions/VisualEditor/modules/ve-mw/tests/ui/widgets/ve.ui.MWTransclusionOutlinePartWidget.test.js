QUnit.module( 've.ui.MWTransclusionOutlinePartWidget' );

QUnit.test( 'Constructor', ( assert ) => {
	const transclusion = new ve.dm.MWTransclusionModel(),
		part = new ve.dm.MWTransclusionPartModel( transclusion ),
		widget = new ve.ui.MWTransclusionOutlinePartWidget( part, { label: 'Example' } );

	assert.strictEqual( widget.getData(), 'part_0' );
	assert.strictEqual(
		widget.$element.find( '.ve-ui-mwTransclusionOutlineButtonWidget' ).text(),
		'Example'
	);
} );
