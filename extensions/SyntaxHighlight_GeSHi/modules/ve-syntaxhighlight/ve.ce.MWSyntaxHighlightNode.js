/*!
 * VisualEditor ContentEditable MWSyntaxHighlightNode class.
 *
 * @copyright VisualEditor Team and others
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable MediaWiki syntax highlight node.
 *
 * @class
 * @abstract
 *
 * @constructor
 */
ve.ce.MWSyntaxHighlightNode = function VeCeMWSyntaxHighlightNode() {
};

/* Inheritance */

OO.initClass( ve.ce.MWSyntaxHighlightNode );

/* Static Properties */

ve.ce.MWSyntaxHighlightNode.static.name = 'mwSyntaxHighlight';

/* Methods */

// Inherits from ve.ce.GeneratedContentNode
ve.ce.MWSyntaxHighlightNode.prototype.generateContents = function () {
	return mw.loader.using( 'ext.pygments' ).then(
		// Parent method
		() => ve.ce.MWExtensionNode.prototype.generateContents.apply( this, arguments )
	);
};

// Inherits from ve.ce.BranchNode
ve.ce.MWSyntaxHighlightNode.prototype.onSetup = function () {
	// Parent method
	ve.ce.MWExtensionNode.prototype.onSetup.call( this );

	// DOM changes
	this.$element.addClass( 've-ce-mwSyntaxHighlightNode' );
};

// Inherits from ve.ce.FocusableNode
ve.ce.MWSyntaxHighlightNode.prototype.getBoundingRect = function () {
	// HACK: Because nodes can overflow due to the pre tag, just use the
	// first rect (of the wrapper div) for placing the context.
	return this.rects[ 0 ];
};
