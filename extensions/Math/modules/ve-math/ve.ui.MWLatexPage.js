/*!
 * VisualEditor user interface MWLatexPage class.
 *
 * @copyright 2015 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */

/**
 * Latex dialog symbols page
 *
 * @class
 * @extends OO.ui.PageLayout
 *
 * @constructor
 * @param {string} name Unique symbolic name of page
 * @param {Object} [config] Configuration options
 */
ve.ui.MWLatexPage = function VeUiMWLatexPage( name, config ) {
	// Parent constructor
	ve.ui.MWLatexPage.super.call( this, name, config );

	this.label = config.label;

	var symbols = config.symbols;
	var $symbols = $( '<div>' ).addClass( 've-ui-specialCharacterPage-characters' );
	var symbolsNode = $symbols[ 0 ];

	// Avoiding jQuery wrappers as advised in ve.ui.SpecialCharacterPage
	symbols.forEach( function ( symbol ) {
		if ( !symbol.notWorking && !symbol.duplicate ) {
			var tex = symbol.tex || symbol.insert;
			var classes = [ 've-ui-mwLatexPage-symbol' ];
			classes.push(
				've-ui-mwLatexSymbol-' + tex.replace( /[^\w]/g, function ( c ) {
					return '_' + c.charCodeAt( 0 ) + '_';
				} )
			);
			if ( symbol.width ) {
				classes.push( 've-ui-mwLatexPage-symbol-' + symbol.width );
			}
			if ( symbol.contain ) {
				classes.push( 've-ui-mwLatexPage-symbol-contain' );
			}
			if ( symbol.largeLayout ) {
				classes.push( 've-ui-mwLatexPage-symbol-largeLayout' );
			}
			var symbolNode = document.createElement( 'div' );
			classes.forEach( function ( className ) {
				// The following classes are used here:
				// * ve-ui-mwLatexPage-symbol
				// * ve-ui-mwLatexPage-symbol-wide
				// * ve-ui-mwLatexPage-symbol-wider
				// * ve-ui-mwLatexPage-symbol-widest
				// * ve-ui-mwLatexPage-symbol-contain
				// * ve-ui-mwLatexPage-symbol-largeLayout
				symbolNode.classList.add( className );
			} );
			$.data( symbolNode, 'symbol', symbol );
			symbolsNode.appendChild( symbolNode );
		}
	} );

	this.$element
		.addClass( 've-ui-mwLatexPage' )
		.append( $( '<h3>' ).text( name ), $symbols );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWLatexPage, OO.ui.PageLayout );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWLatexPage.prototype.setupOutlineItem = function ( outlineItem ) {
	ve.ui.MWLatexPage.super.prototype.setupOutlineItem.call( this, outlineItem );
	this.outlineItem.setLabel( this.label );
	this.outlineItem.$element.addClass( 've-ui-mwLatexPage-outline' );
};
