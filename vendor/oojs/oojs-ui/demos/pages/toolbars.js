Demo.static.pages.toolbars = function ( demo ) {
	var i, toolGroups, saveButton, deleteButton, actionButton, actionGroup, actionButtonDisabled, PopupTool, ToolGroupTool,
		setDisabled = function () { this.setDisabled( true ); },
		$demo = demo.$element,
		$containers = $(),
		toolFactories = [],
		toolGroupFactories = [],
		toolbars = [],
		configs = [
			{},
			{ actions: true },
			{},
			{ actions: true },
			{ position: 'bottom' },
			{ actions: true, position: 'bottom' },
			{},
			{ actions: true }
		];

	// Show some random accelerator keys that don't actually work
	function getToolAccelerator( name ) {
		return {
			listTool1: 'Ctrl+Shift+1',
			listTool2: 'Ctrl+Alt+2',
			listTool3: 'Cmd+Enter',
			listTool5: 'Shift+Down',
			menuTool: 'Ctrl+M'
		}[ name ];
	}

	for ( i = 0; i <= 7; i++ ) {
		toolFactories.push( new OO.ui.ToolFactory() );
		toolGroupFactories.push( new OO.ui.ToolGroupFactory() );
		toolbars.push( new OO.ui.Toolbar( toolFactories[ i ], toolGroupFactories[ i ], configs[ i ] ) );
		toolbars[ i ].getToolAccelerator = getToolAccelerator;
	}

	function createTool( toolbar, group, name, icon, title, init, onSelect, displayBothIconAndLabel ) {
		var Tool = function () {
			Tool.parent.apply( this, arguments );
			this.toggled = false;
			if ( init ) {
				init.call( this );
			}
		};

		OO.inheritClass( Tool, OO.ui.Tool );

		Tool.prototype.onSelect = function () {
			if ( onSelect ) {
				onSelect.call( this );
			} else {
				this.toggled = !this.toggled;
				this.setActive( this.toggled );
			}
			toolbars[ toolbar ].emit( 'updateState' );
		};
		Tool.prototype.onUpdateState = function () {};

		Tool.static.name = name;
		Tool.static.group = group;
		Tool.static.icon = icon;
		Tool.static.title = title;
		Tool.static.displayBothIconAndLabel = !!displayBothIconAndLabel;
		return Tool;
	}

	function createToolGroup( toolbar, group ) {
		$.each( toolGroups[ group ], function ( i, tool ) {
			var args = tool.slice();
			args.splice( 0, 0, toolbar, group );
			toolFactories[ toolbar ].register( createTool.apply( null, args ) );
		} );
	}

	function createDisabledToolGroup( parent, name ) {
		var DisabledToolGroup = function () {
			DisabledToolGroup.parent.apply( this, arguments );
			this.setDisabled( true );
		};

		OO.inheritClass( DisabledToolGroup, parent );

		DisabledToolGroup.static.name = name;

		DisabledToolGroup.prototype.onUpdateState = function () {
			this.setLabel( 'Disabled' );
		};

		return DisabledToolGroup;
	}

	toolGroupFactories[ 0 ].register( createDisabledToolGroup( OO.ui.BarToolGroup, 'disabledBar' ) );
	toolGroupFactories[ 0 ].register( createDisabledToolGroup( OO.ui.ListToolGroup, 'disabledList' ) );
	toolGroupFactories[ 1 ].register( createDisabledToolGroup( OO.ui.MenuToolGroup, 'disabledMenu' ) );

	PopupTool = function ( toolGroup, config ) {
		// Parent constructor
		OO.ui.PopupTool.call( this, toolGroup, $.extend( { popup: {
			padded: true,
			label: 'Popup head',
			head: true
		} }, config ) );

		this.popup.$body.append( '<p>Popup contents</p>' );
	};

	OO.inheritClass( PopupTool, OO.ui.PopupTool );

	PopupTool.static.name = 'popupTool';
	PopupTool.static.group = 'popupTools';
	PopupTool.static.icon = 'help';

	toolFactories[ 2 ].register( PopupTool );
	toolFactories[ 4 ].register( PopupTool );

	ToolGroupTool = function ( toolGroup, config ) {
		// Parent constructor
		OO.ui.ToolGroupTool.call( this, toolGroup, config );
	};

	OO.inheritClass( ToolGroupTool, OO.ui.ToolGroupTool );

	ToolGroupTool.static.name = 'toolGroupTool';
	ToolGroupTool.static.group = 'barTools';
	ToolGroupTool.static.groupConfig = {
		label: 'More',
		include: [ { group: 'moreListTools' } ]
	};

	toolFactories[ 0 ].register( ToolGroupTool );
	toolFactories[ 3 ].register( ToolGroupTool );
	toolFactories[ 5 ].register( ToolGroupTool );

	// Toolbar
	toolbars[ 0 ].setup( [
		{
			type: 'bar',
			include: [ { group: 'barTools' } ],
			demote: [ 'toolGroupTool' ]
		},
		{
			type: 'disabledBar',
			include: [ { group: 'disabledBarTools' } ]
		},
		{
			type: 'list',
			label: 'List',
			icon: 'image',
			include: [ { group: 'listTools' } ],
			allowCollapse: [ 'listTool1', 'listTool6' ]
		},
		{
			type: 'disabledList',
			label: 'List',
			icon: 'image',
			include: [ { group: 'disabledListTools' } ]
		},
		{
			type: 'list',
			label: 'Auto-disabling list',
			icon: 'image',
			include: [ { group: 'autoDisableListTools' } ]
		},
		{
			label: 'Catch-all',
			include: '*'
		}
	] );
	// Toolbar with action buttons
	toolbars[ 1 ].setup( [
		{
			type: 'menu',
			icon: 'image',
			include: [ { group: 'menuTools' } ]
		},
		{
			type: 'disabledMenu',
			icon: 'image',
			include: [ { group: 'disabledMenuTools' } ]
		}
	] );
	// Action toolbar for toolbars[3]
	toolbars[ 2 ].setup( [
		{
			include: [ { group: 'popupTools' } ]
		},
		{
			type: 'list',
			icon: 'menu',
			indicator: '',
			include: [ { group: 'listTools' } ]
		}
	] );
	toolbars[ 3 ].setup( [
		{
			type: 'bar',
			include: [ { group: 'history' } ]
		},
		{
			type: 'menu',
			include: [ { group: 'menuTools' } ]
		},
		{
			type: 'list',
			icon: 'comment',
			include: [ { group: 'listTools' } ],
			allowCollapse: [ 'listTool1', 'listTool6' ]
		},
		{
			type: 'bar',
			include: [ { group: 'link' } ]
		},
		{
			type: 'bar',
			include: [ { group: 'cite' } ]
		},
		{
			type: 'bar',
			include: [ { group: 'citeDisabled' } ]
		},
		{
			type: 'list',
			label: 'Insert',
			include: [ { group: 'autoDisableListTools' }, { group: 'unusedStuff' } ]
		}
	] );
	// Action toolbar for toolbars[5]
	toolbars[ 4 ].setup( [
		{
			include: [ { group: 'popupTools' } ]
		},
		{
			type: 'list',
			icon: 'menu',
			indicator: '',
			include: [ { group: 'listTools' } ]
		}
	] );
	toolbars[ 5 ].setup( [
		{
			type: 'bar',
			include: [ { group: 'history' } ]
		},
		{
			type: 'menu',
			include: [ { group: 'menuTools' } ]
		},
		{
			type: 'list',
			icon: 'comment',
			include: [ { group: 'listTools' } ],
			allowCollapse: [ 'listTool1', 'listTool6' ]
		},
		{
			type: 'bar',
			include: [ { group: 'link' } ]
		},
		{
			type: 'bar',
			include: [ { group: 'cite' } ]
		},
		{
			type: 'bar',
			include: [ { group: 'citeDisabled' } ]
		},
		{
			type: 'list',
			label: 'Insert',
			include: [ { group: 'autoDisableListTools' }, { group: 'unusedStuff' } ]
		}
	] );
	// Action toolbar for toolbars[7]
	toolbars[ 6 ].setup( [
		{
			type: 'list',
			indicator: 'down',
			flags: [ 'primary', 'progressive' ],
			include: [ { group: 'listTools' } ]
		}
	] );
	// Toolbar with action buttons, in a buttongroup
	toolbars[ 7 ].setup( [
		{
			type: 'menu',
			icon: 'image',
			include: [ { group: 'menuTools' } ]
		},
		{
			type: 'disabledMenu',
			icon: 'image',
			include: [ { group: 'disabledMenuTools' } ]
		}
	] );

	actionButton = new OO.ui.ButtonWidget( { label: 'Action' } );
	actionButtonDisabled = new OO.ui.ButtonWidget( { label: 'Disabled', disabled: true } );
	toolbars[ 1 ].$actions.append( actionButton.$element, actionButtonDisabled.$element );

	for ( i = 3; i <= 5; i += 2 ) {
		deleteButton = new OO.ui.ButtonWidget( { label: 'Delete', flags: [ 'destructive' ] } );
		saveButton = new OO.ui.ButtonWidget( { label: 'Save', flags: [ 'progressive', 'primary' ] } );
		toolbars[ i ].$actions.append( toolbars[ i - 1 ].$element, deleteButton.$element, saveButton.$element );
	}

	saveButton = new OO.ui.ButtonWidget( { label: 'Save', flags: [ 'progressive', 'primary' ] } );
	actionGroup = new OO.ui.ButtonGroupWidget( {
		items: [ saveButton, toolbars[ 6 ].items[ 0 ] ]
	} );
	toolbars[ 7 ].$actions.append( actionGroup.$element );

	for ( i = 0; i < toolbars.length; i++ ) {
		toolbars[ i ].emit( 'updateState' );
	}

	toolGroups = {
		barTools: [
			[ 'barTool', 'image', 'Basic tool in bar' ],
			[ 'disabledBarTool', 'image', 'Basic tool in bar disabled', setDisabled ]
		],

		disabledBarTools: [
			[ 'barToolInDisabled', 'image', 'Basic tool in disabled bar' ]
		],

		listTools: [
			[ 'listTool', 'image', 'First basic tool in list' ],
			[ 'listTool1', 'image', 'Basic tool in list' ],
			[ 'listTool3', 'image', 'Basic disabled tool in list', setDisabled ],
			[ 'listTool6', 'image', 'A final tool' ]
		],

		moreListTools: [
			[ 'listTool2', 'code', 'Another basic tool' ],
			[ 'listTool4', 'image', 'More basic tools' ],
			[ 'listTool5', 'ellipsis', 'And even more' ]
		],

		popupTools: [
			[ 'popupTool' ]
		],

		disabledListTools: [
			[ 'listToolInDisabled', 'image', 'Basic tool in disabled list' ]
		],

		autoDisableListTools: [
			[ 'autoDisableListTool', 'image', 'Click to disable this tool', null, setDisabled ]
		],

		menuTools: [
			[ 'menuTool', 'image', 'Basic tool' ],
			[ 'iconlessMenuTool', null, 'Tool without an icon' ],
			[ 'disabledMenuTool', 'image', 'Basic tool disabled', setDisabled ]
		],

		disabledMenuTools: [
			[ 'menuToolInDisabled', 'image', 'Basic tool' ]
		],

		unusedStuff: [
			[ 'unusedTool', 'help', 'This tool is not explicitly used anywhere' ],
			[ 'unusedTool1', 'help', 'And neither is this one' ]
		],

		history: [
			[ 'undoTool', 'undo', 'Undo' ],
			[ 'redoTool', 'redo', 'Redo' ]
		],

		link: [
			[ 'linkTool', 'link', 'Link' ]
		],

		cite: [
			[ 'citeTool', 'citeArticle', 'Cite', null, null, true ]
		],

		citeDisabled: [
			[ 'citeToolDisabled', 'citeArticle', 'Cite', setDisabled, null, true ]
		]
	};

	createToolGroup( 0, 'unusedStuff' );
	createToolGroup( 0, 'barTools' );
	createToolGroup( 0, 'disabledBarTools' );
	createToolGroup( 0, 'listTools' );
	createToolGroup( 0, 'moreListTools' );
	createToolGroup( 0, 'disabledListTools' );
	createToolGroup( 0, 'autoDisableListTools' );
	createToolGroup( 1, 'menuTools' );
	createToolGroup( 1, 'disabledMenuTools' );
	createToolGroup( 6, 'listTools' );
	createToolGroup( 7, 'menuTools' );
	createToolGroup( 7, 'disabledMenuTools' );
	for ( i = 3; i <= 5; i += 2 ) {
		createToolGroup( i - 1, 'listTools' );
		createToolGroup( i, 'history' );
		createToolGroup( i, 'link' );
		createToolGroup( i, 'cite' );
		createToolGroup( i, 'citeDisabled' );
		createToolGroup( i, 'menuTools' );
		createToolGroup( i, 'listTools' );
		createToolGroup( i, 'moreListTools' );
		createToolGroup( i, 'autoDisableListTools' );
		createToolGroup( i, 'unusedStuff' );
	}

	for ( i = 0; i < toolbars.length; i++ ) {
		if ( i === 2 || i === 4 || i === 6 ) {
			// Action toolbars
			continue;
		}
		$containers = $containers.add(
			new OO.ui.PanelLayout( {
				expanded: false,
				framed: true
			} ).$element
				.addClass( 'demo-container demo-toolbars' )
		);

		$containers.last().append( toolbars[ i ].$element );
	}
	$containers.append( '' );
	$demo.append(
		$containers.eq( 0 ).append( '<div class="demo-toolbars-contents">Toolbar</div>' ),
		$containers.eq( 1 ).append( '<div class="demo-toolbars-contents">Toolbar with action buttons</div>' ),
		$containers.eq( 2 ).append( '<div class="demo-toolbars-contents">Word processor toolbar</div>' ),
		$containers.eq( 3 ).prepend( '<div class="demo-toolbars-contents">Position bottom</div>' ),
		$containers.eq( 4 ).append( '<div class="demo-toolbars-contents">Toolbar with action buttons in a group</div>' )
	);
	for ( i = 0; i < toolbars.length; i++ ) {
		toolbars[ i ].initialize();
	}
};
