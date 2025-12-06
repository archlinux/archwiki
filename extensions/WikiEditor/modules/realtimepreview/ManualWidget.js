/* global RealtimePreview */
/**
 * @class
 * @constructor
 * @param {RealtimePreview} realtimePreview
 */
function ManualWidget( realtimePreview ) {
	const config = {
		classes: [ 'ext-WikiEditor-ManualWidget' ],
		$element: $( '<a>' )
	};
	ManualWidget.super.call( this, config );

	// Mixins.
	OO.ui.mixin.AccessKeyedElement.call( this, {} );
	OO.ui.mixin.ButtonElement.call( this, Object.assign( {
		$button: this.$element
	}, config ) );
	OO.ui.mixin.IconElement.call( this, { icon: 'reload' } );
	OO.ui.mixin.TitledElement.call( this, {
		title: mw.msg( 'wikieditor-realtimepreview-reload-title' )
	} );

	// UI elements.
	const $reloadLabel = $( '<span>' )
		.text( mw.msg( 'wikieditor-realtimepreview-manual' ) );
	const $reloadButton = $( '<span>' )
		.addClass( 'ext-WikiEditor-realtimepreview-manual-reload' )
		.text( mw.msg( 'wikieditor-realtimepreview-reload' ) );
	this.connect( realtimePreview, {
		click: function () {
			if ( !this.isScreenWideEnough() ) {
				// Do nothing if realtime preview is not visible.
				return;
			}
			// Only refresh the preview if we're enabled.
			if ( this.enabled ) {
				this.doRealtimePreview();
			}
			mw.hook( 'ext.WikiEditor.realtimepreview.reloadManual' ).fire( this );
		}.bind( realtimePreview )
	} );
	this.$element.append( this.$icon, $reloadLabel, $reloadButton );
}

OO.inheritClass( ManualWidget, OO.ui.Widget );
OO.mixinClass( ManualWidget, OO.ui.mixin.AccessKeyedElement );
OO.mixinClass( ManualWidget, OO.ui.mixin.ButtonElement );
OO.mixinClass( ManualWidget, OO.ui.mixin.IconElement );
OO.mixinClass( ManualWidget, OO.ui.mixin.TitledElement );

module.exports = ManualWidget;
