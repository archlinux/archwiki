const { getFormattedBlockDetails } = require( './api.js' );

/**
 * Widget to show a popup with block details for the current user.
 *
 * @class
 * @extends OO.ui.PopupButtonWidget
 */
function BlockDetailsPopupButtonWidget() {
	OO.ui.PopupButtonWidget.call( this, {
		icon: 'info',
		title: mw.msg( 'checkuser-tempaccount-reveal-blocked-title' ),
		framed: false,
		popup: {
			padded: true
		}
	} );

	this.$element.addClass( 'ext-checkuser-blockDetailsPopupButtonWidget' );

	this.loadingIndicator = new OO.ui.ProgressBarWidget( {
		progress: false,
		inline: true
	} );

	this.loadingIndicator.$element.addClass(
		'ext-checkuser-blockDetailsPopupButtonWidget-loadingIndicator'
	);

	this.loadingIndicator.$element.attr( 'aria-label', mw.msg( 'checkuser-tempaccount-reveal-blocked-loading' ) );

	this.popup.connect( this, { toggle: 'onVisibilityChanged' } );
}
OO.inheritClass( BlockDetailsPopupButtonWidget, OO.ui.PopupButtonWidget );

/**
 * Promise holding resolved block details, reused between different popups on the same page.
 *
 * @static
 */
BlockDetailsPopupButtonWidget.static.cachedBlockDetails = null;

/**
 * Load and display formatted block details inside the popup when opened.
 *
 * @param {boolean} visible Whether the popup was toggled to be visible or not
 */
BlockDetailsPopupButtonWidget.prototype.onVisibilityChanged = function ( visible ) {
	if ( !visible ) {
		return;
	}

	if ( !BlockDetailsPopupButtonWidget.static.cachedBlockDetails ) {
		BlockDetailsPopupButtonWidget.static.cachedBlockDetails = getFormattedBlockDetails().then(
			( data ) => {
				const blockInfo = data &&
					data.query &&
					data.query.checkuserformattedblockinfo &&
					data.query.checkuserformattedblockinfo.details;

				if ( blockInfo ) {
					const $title = $( '<h4>' )
						.text( mw.msg( 'checkuser-tempaccount-reveal-blocked-header' ) );

					const $desc = $( '<p>' )
						.text( mw.msg( 'checkuser-tempaccount-reveal-blocked-description' ) );

					return $( '<div>' )
						.append( $title )
						.append( $desc )
						// Safety: blockInfo is expected to contain
						// HTML rendered by the wikitext parser.
						.append( blockInfo )
						.html();
				}

				return BlockDetailsPopupButtonWidget.static.createMessageWidget(
					'success',
					mw.msg( 'checkuser-tempaccount-reveal-blocked-missingblock' )
				);
			},
			() => BlockDetailsPopupButtonWidget.static.createMessageWidget(
				'error', mw.msg( 'checkuser-tempaccount-reveal-blocked-error' )
			)
		);

		this.popup.$body.empty();
		this.popup.$body.append( this.loadingIndicator.$element );
	}

	BlockDetailsPopupButtonWidget.static.cachedBlockDetails.then(
		( html ) => {
			this.popup.$body.empty();
			this.popup.$body.append( html );
		}
	);
};

/**
 * Convenience function to create a message widget.
 *
 * @static
 * @param {string} type A type name supported by OO.ui.MessageWidget, e.g. 'success'
 * @param {string} label The text to display in the message widget
 * @return {jQuery} jQuery object containing message widget HTML
 */
BlockDetailsPopupButtonWidget.static.createMessageWidget = function ( type, label ) {
	const messageWidget = new OO.ui.MessageWidget( {
		type: type,
		label: label,
		inline: true
	} );

	return messageWidget.$element;
};

module.exports = BlockDetailsPopupButtonWidget;
