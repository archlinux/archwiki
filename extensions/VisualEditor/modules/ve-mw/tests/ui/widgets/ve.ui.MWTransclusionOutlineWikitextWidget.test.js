QUnit.module( 've.ui.MWTransclusionOutlineWikitextWidget' );

QUnit.test( 'Constructor', ( assert ) => {
	const transclusion = new ve.dm.MWTransclusionModel(),
		content = new ve.dm.MWTransclusionContentModel( transclusion ),
		widget = new ve.ui.MWTransclusionOutlineWikitextWidget( content );

	assert.strictEqual( widget.getData(), 'part_0' );
	assert.strictEqual(
		widget.$element.find( '.ve-ui-mwTransclusionOutlineButtonWidget' ).text(),
		'visualeditor-dialog-transclusion-wikitext'
	);
} );
