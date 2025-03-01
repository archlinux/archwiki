/*!
 * VisualEditor ContentEditable MWTransclusionTableCellNode class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable MediaWiki transclusion table cell node.
 *
 * @class
 * @extends ve.ce.MWTransclusionNode
 * @constructor
 * @mixes ve.ce.TableCellableNode
 * @param {ve.dm.MWTransclusionTableCellNode} model Model to observe
 */
ve.ce.MWTransclusionTableCellNode = function VeCeMWTransclusionTableCellNode( model ) {
	// Parent constructor
	ve.ce.MWTransclusionTableCellNode.super.call( this, model );

	// Mixin constructors
	ve.ce.TableCellableNode.call( this );
};

/* Inheritance */

OO.inheritClass( ve.ce.MWTransclusionTableCellNode, ve.ce.MWTransclusionNode );

OO.mixinClass( ve.ce.MWTransclusionTableCellNode, ve.ce.TableCellableNode );

/* Static Properties */

ve.ce.MWTransclusionTableCellNode.static.name = 'mwTransclusionTableCell';

/* Methods */

ve.ce.MWTransclusionTableCellNode.prototype.getTagName = function () {
	// mwTransclusionTableCells have no style attribute. Give them a table
	// cell to start with, although it will get overwritten with
	// originalDomElements.
	return 'td';
};

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWTransclusionTableCellNode );
