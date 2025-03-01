/**
 * Adapted from https://www.mediawiki.org/wiki/MediaWiki:Gadget-site-tpl-copy.js
 * Original author: Krinkle
 */

// eslint-disable-next-line compat/compat
const hasFeature = navigator.clipboard && 'writeText' in navigator.clipboard;
if ( hasFeature ) {
	// Add type=button to avoid form submission in preview
	const $btn = $( '<button>' )
		.attr( 'type', 'button' )
		.text( mw.msg( 'syntaxhighlight-button-copy' ) )
		.on( 'click', function () {
			const btn = this;
			const wrapper = btn.closest( '.mw-highlight-copy' );
			const preNode = wrapper && wrapper.querySelector( 'pre' );
			const content = preNode && preNode.textContent.trim();
			try {
				navigator.clipboard.writeText( content );
			} catch ( e ) {
				return;
			}
			const prevLabel = btn.textContent;
			btn.textContent = mw.msg( 'syntaxhighlight-button-copied' );
			setTimeout( () => {
				btn.textContent = prevLabel;
			}, 5000 );
		} );

	mw.hook( 'wikipage.content' ).add( ( $content ) => {
		$content.find( '.mw-highlight-copy:not(.mw-highlight-copy--bound)' )
			.append( $btn.clone( true ) )
			.addClass( 'mw-highlight-copy--bound' );
	} );
}
