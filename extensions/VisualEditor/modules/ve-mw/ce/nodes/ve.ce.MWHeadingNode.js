/*!
 * VisualEditor ContentEditable MWHeadingNode class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable MW heading node.
 *
 * @class
 * @extends ve.ce.HeadingNode
 * @constructor
 * @param {ve.dm.MWHeadingNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWHeadingNode = function VeCeMWHeadingNode() {
	// Parent constructor
	ve.ce.MWHeadingNode.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ce.MWHeadingNode, ve.ce.HeadingNode );

/* Static Properties */

ve.ce.MWHeadingNode.static.name = 'mwHeading';

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWHeadingNode );
