QUnit.module( 've.ui.MWTransclusionOutlinePlaceholderWidget' );

QUnit.test( 'Constructor', ( assert ) => {
	const transclusion = new ve.dm.MWTransclusionModel(),
		placeholder = new ve.dm.MWTemplatePlaceholderModel( transclusion ),
		widget = new ve.ui.MWTransclusionOutlinePlaceholderWidget( placeholder );

	assert.strictEqual( widget.getData(), 'part_0' );
	assert.strictEqual(
		widget.$element.find( '.ve-ui-mwTransclusionOutlineButtonWidget' ).text(),
		'visualeditor-dialog-transclusion-add-template'
	);
} );
