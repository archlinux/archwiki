( function () {
	var i, j, jLen, toolGroups, linkIndex, target, label, group;

	// HACK: Find the position of the current citation toolbar definition
	// and manipulate it.

	targetLoop:
	for ( i in ve.init.mw ) {
		target = ve.init.mw[ i ];
		if ( !target || !( target.prototype instanceof ve.init.Target ) ) {
			continue;
		}
		toolGroups = target.static.toolbarGroups;
		linkIndex = toolGroups.length;
		for ( j = 0, jLen = toolGroups.length; j < jLen; j++ ) {
			if ( ve.getProp( toolGroups[ j ], 'include', 0, 'group' ) === 'cite' ) {
				// Skip if the cite group exists already
				linkIndex = -1;
				continue targetLoop;
			}
		}
		// Looking through the object to find what we actually need
		// to replace. This way, if toolbarGroups are changed in VE code
		// we won't have to manually change the index here.
		for ( j = 0, jLen = toolGroups.length; j < jLen; j++ ) {
			if ( ve.getProp( toolGroups[ j ], 'include', 0 ) === 'link' ) {
				linkIndex = j;
				break;
			}
		}

		label = OO.ui.deferMsg( 'cite-ve-toolbar-group-label' );
		group = {
			classes: [ 've-test-toolbar-cite' ],
			type: 'list',
			indicator: 'down',
			include: [ { group: 'cite' }, 'reference', 'reference/existing' ],
			demote: [ 'reference', 'reference/existing' ]
		};

		// Treat mobile targets differently
		if ( ve.init.mw.MobileArticleTarget && target.prototype instanceof ve.init.mw.MobileArticleTarget ) {
			group.header = label;
			group.title = label;
			group.icon = 'reference';
		} else {
			group.label = label;
		}

		// Insert a new group for references after the link group (or at the end).
		toolGroups.splice( linkIndex + 1, 0, group );
	}

	/**
	 * Add reference insertion tools from on-wiki data.
	 *
	 * By adding a definition in JSON to MediaWiki:Visualeditor-cite-tool-definition, the cite menu can
	 * be populated with tools that create refrences containing a specific templates. The content of the
	 * definition should be an array containing a series of objects, one for each tool. Each object must
	 * contain a `name`, `icon` and `template` property. An optional `title` property can also be used
	 * to define the tool title in plain text. The `name` property is a unique identifier for the tool,
	 * and also provides a fallback title for the tool by being transformed into a message key. The name
	 * is prefixed with `visualeditor-cite-tool-name-`, and messages can be defined on Wiki. Some common
	 * messages are pre-defined for tool names such as `web`, `book`, `news` and `journal`.
	 *
	 * Example:
	 * [ { "name": "web", "icon": "cite-web", "template": "Cite web" }, ... ]
	 *
	 */
	( function () {
		var i, len, item, name, data, tool, tools, dialog, contextItem,
			limit = 5;

		/*jshint loopfunc:true */

		try {
			// Must use mw.message to avoid JSON being parsed as Wikitext
			tools = JSON.parse( mw.message( 'visualeditor-cite-tool-definition.json' ).plain() );
		} catch ( e ) {}

		if ( Array.isArray( tools ) ) {
			for ( i = 0, len = Math.min( limit, tools.length ); i < len; i++ ) {
				item = tools[ i ];
				data = { template: item.template };

				// Generate citation tool
				name = 'cite-' + item.name;
				if ( !ve.ui.toolFactory.lookup( name ) ) {
					tool = function GeneratedMWCitationDialogTool( toolbar, config ) {
						ve.ui.MWCitationDialogTool.call( this, toolbar, config );
					};
					OO.inheritClass( tool, ve.ui.MWCitationDialogTool );
					tool.static.group = 'cite';
					tool.static.name = name;
					tool.static.icon = item.icon;
					tool.static.title = item.title;
					tool.static.commandName = name;
					tool.static.template = item.template;
					tool.static.autoAddToCatchall = false;
					tool.static.autoAddToGroup = true;
					tool.static.associatedWindows = [ name ];
					ve.ui.toolFactory.register( tool );
					ve.ui.commandRegistry.register(
						new ve.ui.Command(
							name, 'mwcite', 'open', { args: [ name, data ], supportedSelections: [ 'linear' ] }
						)
					);
				}

				// Generate citation context item
				if ( !ve.ui.contextItemFactory.lookup( name ) ) {
					contextItem = function GeneratedMWCitationContextItem( toolbar, config ) {
						ve.ui.MWCitationContextItem.call( this, toolbar, config );
					};
					OO.inheritClass( contextItem, ve.ui.MWCitationContextItem );
					contextItem.static.name = name;
					contextItem.static.icon = item.icon;
					contextItem.static.label = item.title;
					contextItem.static.commandName = name;
					contextItem.static.template = item.template;
					ve.ui.contextItemFactory.register( contextItem );
				}

				// Generate dialog
				if ( !ve.ui.windowFactory.lookup( name ) ) {
					dialog = function GeneratedMWCitationDialog( config ) {
						ve.ui.MWCitationDialog.call( this, config );
					};
					OO.inheritClass( dialog, ve.ui.MWCitationDialog );
					dialog.static.name = name;
					dialog.static.title = item.title;
					ve.ui.windowFactory.register( dialog );
				}
			}
		}
	} )();

}() );
