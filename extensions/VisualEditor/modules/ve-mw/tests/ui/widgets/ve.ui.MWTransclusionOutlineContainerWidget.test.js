QUnit.module( 've.ui.MWTransclusionOutlineContainerWidget' );

QUnit.test( 'Constructor', ( assert ) => {
	const widget = new ve.ui.MWTransclusionOutlineContainerWidget();

	// eslint-disable-next-line no-jquery/no-class-state
	assert.ok( widget.$element.hasClass( 've-ui-mwTransclusionOutlineContainerWidget' ) );
	assert.deepEqual( widget.partWidgets, {} );
} );

QUnit.test( 'Supports all ve.dm.MWTransclusionPartModel subclasses', ( assert ) => {
	const transclusion = new ve.dm.MWTransclusionModel(),
		widget = new ve.ui.MWTransclusionOutlineContainerWidget();

	widget.onReplacePart( null, new ve.dm.MWTemplateModel( transclusion, {} ) );
	widget.onReplacePart( null, new ve.dm.MWTemplatePlaceholderModel( transclusion ) );
	widget.onReplacePart( null, new ve.dm.MWTransclusionContentModel( transclusion ) );

	assert.ok( widget.partWidgets.part_0 instanceof ve.ui.MWTransclusionOutlineTemplateWidget );
	assert.ok( widget.partWidgets.part_1 instanceof ve.ui.MWTransclusionOutlinePlaceholderWidget );
	assert.ok( widget.partWidgets.part_2 instanceof ve.ui.MWTransclusionOutlineWikitextWidget );
} );

QUnit.test( 'Basic functionality', ( assert ) => {
	const transclusion = new ve.dm.MWTransclusionModel(),
		part0 = new ve.dm.MWTransclusionContentModel( transclusion ),
		part1 = new ve.dm.MWTransclusionContentModel( transclusion ),
		widget = new ve.ui.MWTransclusionOutlineContainerWidget();

	widget.onReplacePart();
	assert.deepEqual( widget.partWidgets, {} );

	widget.onReplacePart( null, part0 );
	widget.onReplacePart( null, part1 );
	assert.deepEqual( Object.keys( widget.partWidgets ), [ 'part_0', 'part_1' ] );

	widget.onReplacePart( part0 );
	assert.deepEqual( Object.keys( widget.partWidgets ), [ 'part_1' ] );

	widget.clear();
	assert.deepEqual( widget.partWidgets, {} );
} );

QUnit.test( 'Adding and moving parts to specific positions', ( assert ) => {
	const transclusion = new ve.dm.MWTransclusionModel(),
		part0 = new ve.dm.MWTransclusionContentModel( transclusion ),
		part1 = new ve.dm.MWTransclusionContentModel( transclusion ),
		part2 = new ve.dm.MWTransclusionContentModel( transclusion ),
		widget = new ve.ui.MWTransclusionOutlineContainerWidget();

	// This adds the parts at an invalid position, at the start, and in the middle
	widget.onReplacePart( null, part0, 666 );
	widget.onReplacePart( null, part1, 0 );
	widget.onReplacePart( null, part2, 1 );

	// Note this is just a map and doesn't reflect the order in the UI
	assert.deepEqual( Object.keys( widget.partWidgets ), [ 'part_0', 'part_1', 'part_2' ] );

	let $items = widget.$element.children();
	assert.ok( $items.eq( 0 ).is( widget.partWidgets.part_1.$element ) );
	assert.ok( $items.eq( 1 ).is( widget.partWidgets.part_2.$element ) );
	assert.ok( $items.eq( 2 ).is( widget.partWidgets.part_0.$element ) );

	// This bypasses all logic in ve.dm.MWTransclusionModel, effectively making it a mock.
	transclusion.parts = [ part2, part0, part1 ];
	widget.onTransclusionModelChange( transclusion );

	$items = widget.$element.children();
	assert.ok( $items.eq( 0 ).is( widget.partWidgets.part_2.$element ) );
	assert.ok( $items.eq( 1 ).is( widget.partWidgets.part_0.$element ) );
	assert.ok( $items.eq( 2 ).is( widget.partWidgets.part_1.$element ) );
} );

[
	[ '', null ],
	[ 'part_0', null ],
	[ 'part_0/', '' ],
	[ 'part_0/foo', 'foo' ],
	[ 'part_1/foo', null ],
	[ 'part_0/foo/bar', 'foo/bar' ]
].forEach( ( [ pageName, expected ] ) =>
	QUnit.test( 'highlightSubItemByPageName: ' + pageName, ( assert ) => {
		const transclusion = new ve.dm.MWTransclusionModel(),
			template = new ve.dm.MWTemplateModel( transclusion, {} ),
			partWidget = new ve.ui.MWTransclusionOutlineTemplateWidget( template ),
			widget = new ve.ui.MWTransclusionOutlineContainerWidget();

		// eslint-disable-next-line camelcase
		widget.partWidgets.part_0 = partWidget;

		let actual = null;
		partWidget.highlightParameter = ( paramName ) => {
			actual = paramName;
		};

		widget.highlightSubItemByPageName( pageName );
		assert.strictEqual( actual, expected );
	} )
);
