/**
 * @class
 * @extends ve.ui.MWTransclusionOutlinePartWidget
 *
 * @constructor
 * @param {ve.dm.MWTemplatePlaceholderModel} placeholder
 */
ve.ui.MWTransclusionOutlinePlaceholderWidget = function VeUiMWTransclusionOutlinePlaceholderWidget( placeholder ) {
	var label = placeholder.getTransclusion().isSingleTemplate() ?
		ve.msg( 'visualeditor-dialog-transclusion-template-search' ) :
		ve.msg( 'visualeditor-dialog-transclusion-add-template' );

	// Parent constructor
	ve.ui.MWTransclusionOutlinePlaceholderWidget.super.call( this, placeholder, {
		icon: 'puzzle',
		label: label
	} );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWTransclusionOutlinePlaceholderWidget, ve.ui.MWTransclusionOutlinePartWidget );
