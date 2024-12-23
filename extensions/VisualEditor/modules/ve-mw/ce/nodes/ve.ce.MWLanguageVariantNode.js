/*!
 * VisualEditor ContentEditable MWLanguageVariantNode class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable MediaWiki language variant node, used for
 * LanguageConverter markup.
 *
 * @class
 * @abstract
 * @extends ve.ce.LeafNode
 * @mixes ve.ce.FocusableNode
 * @constructor
 * @param {ve.dm.MWLanguageVariantNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWLanguageVariantNode = function VeCeMWLanguageVariantNode( model, config ) {
	// Parent constructor
	ve.ce.MWLanguageVariantNode.super.call( this, model, config );

	// Mixin constructors
	ve.ce.FocusableNode.call( this, this.$element, config );

	// DOM changes
	this.$element.addClass( 've-ce-mwLanguageVariantNode' );
	this.$holder = this.appendHolder(); // null for a hidden node

	// Events
	this.model.connect( this, { update: 'onUpdate' } );

	// Initialization
	this.onUpdate();
};

/* Inheritance */

OO.inheritClass( ve.ce.MWLanguageVariantNode, ve.ce.LeafNode );

OO.mixinClass( ve.ce.MWLanguageVariantNode, ve.ce.FocusableNode );

/* Static Properties */

ve.ce.MWLanguageVariantNode.static.iconWhenInvisible = 'language';

/* Static Methods */

/**
 * @inheritdoc
 */
ve.ce.MWLanguageVariantNode.static.getDescription = function ( model ) {
	// This is shown when you hover over the node.
	const variantInfo = model.getVariantInfo(),
		messageKey = 'visualeditor-mwlanguagevariant-' + model.getRuleType();
	let languageCodes = [];
	if ( variantInfo.name ) {
		languageCodes = [ variantInfo.name.t ];
	} else if ( variantInfo.filter ) {
		languageCodes = variantInfo.filter.l;
	} else if ( variantInfo.twoway ) {
		languageCodes = variantInfo.twoway.map( ( item ) => item.l );
	} else if ( variantInfo.oneway ) {
		languageCodes = variantInfo.oneway.map( ( item ) => item.l );
	}
	const languageString = languageCodes.map( ( code ) => ve.init.platform.getLanguageName( code.toLowerCase() ) ).join( ve.msg( 'comma-separator' ) );
	// The following messages can be used here:
	// * visualeditor-mwlanguagevariant-disabled
	// * visualeditor-mwlanguagevariant-filter
	// * visualeditor-mwlanguagevariant-name
	// * visualeditor-mwlanguagevariant-oneway
	// * visualeditor-mwlanguagevariant-twoway
	// * visualeditor-mwlanguagevariant-unknown
	return ve.msg( messageKey, languageString );
};

/* Methods */

/**
 * Handle model update events.
 */
ve.ce.MWLanguageVariantNode.prototype.onUpdate = function () {
	if ( !this.model.isHidden() ) {
		this.model.constructor.static.insertPreviewElements(
			this.$holder[ 0 ], this.model.getVariantInfo()
		);
	}
	this.updateInvisibleIconLabel();
};

/**
 * @inheritdoc
 *
 * The text preview is a trimmed down version of the actual rule. This
 * means that we strip whitespace and newlines, and truncate to a
 * fairly short length. The goal is to provide a fair representation of
 * typical short rules, and enough context for long rules that the
 * user can tell whether they want to see the full view by focusing the
 * node / hovering.
 */
ve.ce.MWLanguageVariantNode.prototype.getInvisibleIconLabel = function () {
	const variantInfo = this.model.getVariantInfo();

	if ( this.model.isHidden() ) {
		const $element = $( '<div>' );
		this.model.constructor.static.insertPreviewElements(
			// For compactness, just annotate hidden rule w/ its
			// current variant output.
			$element[ 0 ], variantInfo
		);
		return $element.text().trim().replace( /\s+/, ' ' );
	}
	return null;
};

/**
 * Create a {jQuery} appropriate for holding the output of this
 * conversion rule.
 *
 * @return {jQuery}
 */
ve.ce.MWLanguageVariantNode.prototype.appendHolder = function () {
	const tagName = this.constructor.static.tagName,
		document = this.$element[ 0 ].ownerDocument,
		$holder = $( document.createElement( tagName ) );
	$holder.addClass( 've-ce-mwLanguageVariantNode-holder' );
	this.$element.append( $holder );
	return $holder;
};

/**
 * @inheritdoc
 */
ve.ce.MWLanguageVariantNode.prototype.hasRendering = function () {
	// Efficiency improvement: the superclass implementation does a bunch
	// of DOM measurement to determine if the node is empty.
	// Instead consult the model for a definitive answer.
	return !this.model.isHidden();
};
