QUnit.module( 've.ui.MWTransclusionOutlineParameterSelectWidget' );

QUnit.test( 'static item constructor', ( assert ) => {
	const item = ve.ui.MWTransclusionOutlineParameterSelectWidget.static.createItem( { data: 'p1' } );

	assert.ok( item instanceof OO.ui.OptionWidget, 'items use correct base class' );
	assert.strictEqual( item.getData(), 'p1', 'config is forwarded to base class' );
	assert.notOk( item.isSelected() );
} );

QUnit.test( 'interaction with items', ( assert ) => {
	const item = ve.ui.MWTransclusionOutlineParameterSelectWidget.static.createItem( {} ),
		widget = new ve.ui.MWTransclusionOutlineParameterSelectWidget( { items: [ item ] } );

	assert.strictEqual( widget.getItemCount(), 1, 'item is added' );

	// Note this triggers a chain of events that bubbles up to the SelectWidget. The individual
	// OptionWidgets just store their state, but the outer SelectWidget manages it!
	item.checkbox.setSelected( true );
	assert.ok( item.isSelected(), 'clicking the checkbox selects the item' );

	let eventsFired = 0;
	widget.connect( this, { templateParameterClick: () => eventsFired++ } );

	widget.onMouseDown( {
		which: OO.ui.MouseButtons.LEFT,
		target: item.$element[ 0 ]
	} );

	widget.highlightItem( item );
	widget.onDocumentKeyDown( {
		keyCode: OO.ui.Keys.ENTER,
		preventDefault: () => undefined
	} );

	assert.strictEqual( eventsFired, 2 );
} );

QUnit.test( 'interaction with required parameter', ( assert ) => {
	const item = ve.ui.MWTransclusionOutlineParameterSelectWidget.static.createItem( { required: true } ),
		widget = new ve.ui.MWTransclusionOutlineParameterSelectWidget( { items: [ item ] } );

	assert.strictEqual( widget.getItems()[ 0 ], item, 'item is used as is' );
	assert.ok( item.isSelected(), 'selected by default' );

	item.setSelected( false );
	assert.ok( item.isSelected(), 'can not unselect' );

	item.checkbox.setSelected( false );
	assert.ok( item.isSelected(), 'can not unselect via the checkbox as well' );
} );
