'use strict';

/*!
 * VisualEditor MediaWiki Cite initialisation code for the citeation tools.
 *
 * @copyright 2026 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

( function () {
	/**
	 * Add reference insertion tools from on-wiki data.
	 *
	 * By adding a definition in JSON to MediaWiki:Cite-tool-definition.json, the cite menu can
	 * be populated with tools that create references containing specific templates. The
	 * content of the definition should be an array containing a series of
	 * objects, one for each tool. Each object must contain a `name`, `icon` and
	 * `template` property. An optional `title` property can also be used to
	 * define the tool title in plain text. The `name` property is a unique
	 * identifier for the tool, and also provides a fallback title for the tool by
	 * being transformed into a message key. The name is prefixed with
	 * `visualeditor-cite-tool-name-`, and messages can be defined on Wiki. Some
	 * common messages are pre-defined for tool names such as `web`, `book`,
	 * `news`, `map` and `journal`.
	 *
	 * Example:
	 * [ { "name": "web", "icon": "browser", "template": "Cite web" }, ... ]
	 */

	// Virtual file declared via extension.json, actual source is MWCitationToolsDefinition.php
	const citationTools = require( './ve.ui.MWCitationTools.json' );
	// Expose globally, used by Citoid and ContentTranslation as well as gadgets
	ve.ui.mwCitationTools = citationTools;

	const MWCitationContextItem = require( './ve.ui.MWCitationContextItem.js' );
	const MWCitationDialogTool = require( './ve.ui.MWCitationDialogTool.js' );
	const MWCitationAction = require( './ve.ui.MWCitationAction.js' );

	citationTools.forEach( ( toolDefinition ) => {
		// Generate citation tool
		const name = MWCitationDialogTool.static.namePrefix + toolDefinition.name;
		if ( !ve.ui.toolFactory.lookup( name ) ) {
			ve.ui.toolFactory.register(
				MWCitationDialogTool.static.newFromCitationToolsDefinition( toolDefinition )
			);
			ve.ui.commandRegistry.register(
				createCitationToolsCommand( toolDefinition )
			);
		}

		if ( !ve.ui.contextItemFactory.lookup( name ) ) {
			ve.ui.contextItemFactory.register(
				MWCitationContextItem.static.newFromCitationToolsDefinition( toolDefinition )
			);
		}
	} );

	/**
	 * Create a VE command from a MediaWiki:Cite-tool-definition.json entry
	 *
	 * @param {Object} toolDefinition
	 * @param {string} toolDefinition.name
	 * @param {string|string[]} toolDefinition.template
	 * @param {string} toolDefinition.title
	 * @return {ve.ui.Command}
	 */
	function createCitationToolsCommand( toolDefinition ) {
		return new ve.ui.Command(
			MWCitationDialogTool.static.namePrefix + toolDefinition.name,
			MWCitationAction.static.name,
			'open',
			{ args: [ toolDefinition ], supportedSelections: [ 'linear' ] }
		);
	}
}() );
