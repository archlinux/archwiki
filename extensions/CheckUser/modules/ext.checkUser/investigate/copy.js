/**
 * Feature for copying wikitext version of the Compare results table (T251361).
 * This feature is available for wikis that have Parsoid/RESTBase.
 */
module.exports = function addCopyFeature() {
	let copyTextLayout = null,
		wikitextButton = null,
		hidden = true,
		requested = false;

	function onWikitextButtonClick() {
		function getSanitizedHtml( $table ) {
			$table = $table.clone();

			$table.find( '.oo-ui-widget, .ext-checkuser-investigate-table-options-container' ).remove();
			$table.find( '.mw-userlink' )
				.attr( 'rel', 'mw:ExtLink' )
				.attr( 'href', function () {
					return new URL( $( this ).attr( 'href' ), location.href ).toString();
				} );

			$table.find( '[class]' ).addBack( '[class]' ).removeAttr( 'class' );
			$table.addClass( 'mw-datatable' );

			$table.find( 'tr, td' ).each( ( i, element ) => {
				Object.keys( element.dataset ).forEach( ( key ) => {
					element.removeAttribute( 'data-' + key );
				} );
			} );

			return $table[ 0 ].outerHTML;
		}

		hidden = !hidden;
		if ( hidden ) {
			wikitextButton.setLabel( mw.msg( 'checkuser-investigate-compare-copy-button-label' ) );
			copyTextLayout.toggle( false );
		} else {
			wikitextButton.setLabel( mw.msg( 'checkuser-investigate-compare-copy-button-label-hide' ) );
			copyTextLayout.toggle( true );
		}

		if ( !requested ) {
			copyTextLayout.textInput.pushPending();
			const restApi = new mw.Rest();
			const html = getSanitizedHtml( $( '.ext-checkuser-investigate-table-compare' ) );
			restApi.post( '/v1/transform/html/to/wikitext/', { html: html } ).then( ( data ) => {
				copyTextLayout.textInput.popPending();
				copyTextLayout.textInput.setValue( data );
			} );
		}

		requested = true;
	}

	const messageWidget = new OO.ui.MessageWidget( {
		type: 'notice',
		label: mw.msg( 'checkuser-investigate-compare-copy-message-label' ),
		classes: [ 'ext-checkuser-investigate-copy-message' ]
	} );
	messageWidget.setIcon( 'table' );

	wikitextButton = new OO.ui.ButtonWidget( {
		label: mw.msg( 'checkuser-investigate-compare-copy-button-label' ),
		classes: [
			'ext-checkuser-investigate-copy-button'
		],
		flags: [ 'primary', 'progressive' ]
	} );
	wikitextButton.on( 'click', onWikitextButtonClick );

	copyTextLayout = new mw.widgets.CopyTextLayout( {
		multiline: true,
		align: 'top',
		textInput: {
			autosize: true,
			// The following classes are used here:
			// * mw-editfont-monospace
			// * mw-editfont-sans-serif
			// * mw-editfont-serif
			classes: [ 'mw-editfont-' + mw.user.options.get( 'editfont' ) ]
		}
	} );
	copyTextLayout.toggle( false );

	$( '.ext-checkuser-investigate-tabs-indexLayout .oo-ui-indexLayout-stackLayout' )
		.append(
			messageWidget.$element.append(
				wikitextButton.$element,
				copyTextLayout.$element
			)
		);
};
