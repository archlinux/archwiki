/* Publish module for wikiEditor */
/*jshint onevar:false */
( function ( $ ) {

$.wikiEditor.modules.publish = {

/**
 * Compatability map
 */
browsers: {
	// Left-to-right languages
	ltr: {
		msie: [['>=', 7]],
		firefox: [['>=', 3]],
		opera: [['>=', 9.6]],
		safari: [['>=', 4]]
	},
	// Right-to-left languages
	rtl: {
		msie: [['>=', 8]],
		firefox: [['>=', 3]],
		opera: [['>=', 9.6]],
		safari: [['>=', 4]]
	}
},
/**
 * Internally used functions
 */
fn: {
	/**
	 * Creates a publish module within a wikiEditor
	 * @param context Context object of editor to create module in
	 * @param config Configuration object to create module from
	 */
	create: function ( context ) {
		// Build the dialog behind the Publish button
		var dialogID = 'wikiEditor-' + context.instance + '-dialog';
		$.wikiEditor.modules.dialogs.fn.create(
			context,
			{
				previewsave: {
					id: dialogID,
					titleMsg: 'wikieditor-publish-dialog-title',
					html: '\
						<div class="wikiEditor-publish-dialog-copywarn"></div>\
						<div class="wikiEditor-publish-dialog-editoptions">\
							<form id="wikieditor-' + context.instance + '-publish-dialog-form">\
								<div class="wikiEditor-publish-dialog-summary">\
									<label for="wikiEditor-' + context.instance + '-dialog-summary"\
										rel="wikieditor-publish-dialog-summary"></label>\
									<br />\
									<input type="text" id="wikiEditor-' + context.instance + '-dialog-summary"\
										style="width: 100%;" />\
								</div>\
								<div class="wikiEditor-publish-dialog-options">\
									<input type="checkbox"\
										id="wikiEditor-' + context.instance + '-dialog-minor" />\
									<label for="wikiEditor-' + context.instance + '-dialog-minor"\
										rel="wikieditor-publish-dialog-minor"></label>\
									<input type="checkbox"\
										id="wikiEditor-' + context.instance + '-dialog-watch" />\
									<label for="wikiEditor-' + context.instance + '-dialog-watch"\
										rel="wikieditor-publish-dialog-watch"></label>\
								</div>\
							</form>\
						</div>',
					init: function () {
						var i;

						$( this ).find( '[rel]' ).each( function () {
							$( this ).text( mediaWiki.msg( $( this ).attr( 'rel' ) ) );
						} );

						/* REALLY DIRTY HACK! */
						// Reformat the copyright warning stuff, if available
						if ( $( '#editpage-copywarn p' ).length ) {
							var copyWarnHTML = $( '#editpage-copywarn p' ).html();
							// TODO: internationalize by splitting on other characters that end statements
							var copyWarnStatements = copyWarnHTML.split( '. ' );
							var newCopyWarnHTML = '<ul>';
							for ( i = 0; i < copyWarnStatements.length; i++ ) {
								if ( copyWarnStatements[i] !== '' ) {
									var copyWarnStatement = $.trim( copyWarnStatements[i] ).replace( /\.*$/, '' );
									newCopyWarnHTML += '<li>' + copyWarnStatement + '.</li>';
								}
							}
							newCopyWarnHTML += '</ul>';
							// No list if there's only one element
							$( this ).find( '.wikiEditor-publish-dialog-copywarn' ).html(
									copyWarnStatements.length > 1 ? newCopyWarnHTML : copyWarnHTML
							);
						}
						/* END OF REALLY DIRTY HACK */

						if ( $( '#wpMinoredit' ).length === 0 ) {
							$( '#wikiEditor-' + context.instance + '-dialog-minor' ).hide();
						} else if ( $( '#wpMinoredit' ).prop( 'checked' ) ) {
							$( '#wikiEditor-' + context.instance + '-dialog-minor' )
								.prop( 'checked', true );
						}
						if ( $( '#wpWatchthis' ).length === 0 ) {
							$( '#wikiEditor-' + context.instance + '-dialog-watch' ).hide();
						} else if ( $( '#wpWatchthis' ).prop( 'checked' ) ) {
							$( '#wikiEditor-' + context.instance + '-dialog-watch' )
								.prop( 'checked', true );
							}

						$( this ).find( 'form' ).submit( function ( e ) {
							$( this ).closest( '.ui-dialog' ).find( 'button:first' ).click();
							e.preventDefault();
						} );
					},
					immediateCreate: true,
					dialog: {
						buttons: {
							'wikieditor-publish-dialog-publish': function () {
								var minorChecked = $( '#wikiEditor-' + context.instance +
									'-dialog-minor' ).is( ':checked' ) ?
										'checked' : '';
								var watchChecked = $( '#wikiEditor-' + context.instance +
									'-dialog-watch' ).is( ':checked' ) ?
										'checked' : '';
								$( '#wpMinoredit' ).prop( 'checked', minorChecked );
								$( '#wpWatchthis' ).prop( 'checked', watchChecked );
								$( '#wpSummary' ).val( $( '#wikiEditor-' + context.instance +
									'-dialog-summary' ).val() );
								$( '#editform' ).submit();
							},
							'wikieditor-publish-dialog-goback': function () {
								$( this ).dialog( 'close' );
							}
						},
						open: function () {
							$( '#wikiEditor-' + context.instance + '-dialog-summary' ).focus();
						},
						width: 500
					},
					resizeme: false
				}
			}
		);

		context.fn.addButton( {
			'captionMsg': 'wikieditor-publish-button-publish',
			'action': function () {
				$( '#' + dialogID ).dialog( 'open' );
				return false;
			}
		} );

		context.fn.addButton( {
			'captionMsg': 'wikieditor-publish-button-cancel',
			'action': function () {
				window.location.href = $( '#mw-editform-cancel' ).attr( 'href' );
				return false;
			}
		} );
	}
}

};

}( jQuery ) );
