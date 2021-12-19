QUnit.module( 've.ui.MWTransclusionOutlineParameterWidget' );

QUnit.test( 'interprets param with no attributes', ( assert ) => {
	const widget = new ve.ui.MWTransclusionOutlineParameterWidget( {} );

	assert.notOk( widget.isSelected() );
	assert.notOk( widget.checkbox.isSelected() );
	assert.notOk( widget.checkbox.isDisabled() );
	assert.strictEqual( widget.checkbox.getTitle(), null );

	widget.setSelected( true );
	assert.ok( widget.isSelected(), 'can select item' );
	assert.ok( widget.checkbox.isSelected(), 'can select checkbox' );
} );

QUnit.test( 'interprets required param', ( assert ) => {
	const widget = new ve.ui.MWTransclusionOutlineParameterWidget( { required: true } );

	assert.ok( widget.isSelected() );
	assert.ok( widget.checkbox.isSelected() );
	assert.ok( widget.checkbox.isDisabled() );
	assert.strictEqual(
		widget.checkbox.getTitle(),
		'visualeditor-dialog-transclusion-required-parameter'
	);

	widget.setSelected( false );
	assert.ok( widget.isSelected(), 'cannot unselect required item' );
	assert.ok( widget.checkbox.isSelected(), 'cannot unselect required checkbox' );
} );

QUnit.test( 'interprets selected param', ( assert ) => {
	const widget = new ve.ui.MWTransclusionOutlineParameterWidget( { selected: true } );

	assert.ok( widget.isSelected() );
	assert.ok( widget.checkbox.isSelected() );
	assert.notOk( widget.checkbox.isDisabled() );
	assert.strictEqual( widget.checkbox.getTitle(), null );

	widget.setSelected( false );
	assert.notOk( widget.isSelected(), 'can unselect item' );
	assert.notOk( widget.checkbox.isSelected(), 'can unselect checkbox' );
} );
