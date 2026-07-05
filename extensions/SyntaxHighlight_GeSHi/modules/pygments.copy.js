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
		.addClass( 'mw-highlight-copy-button' )
		.on( 'click', function () {
			const btn = this;
			const wrapper = btn.closest( '.mw-highlight-copy' );
			const contentNode = wrapper ? wrapper.firstChild : btn.previousElementSibling;
			const content = contentNode && contentNode.matches( 'pre, code' ) && contentNode.textContent.trim();
			try {
				navigator.clipboard.writeText( content );
			} catch ( e ) {
				return;
			}
			btn.textContent = mw.msg( 'syntaxhighlight-button-copied' );
			setTimeout( () => {
				btn.textContent = mw.msg( 'syntaxhighlight-button-copy' );
			}, 5000 );
		} );

	mw.hook( 'wikipage.content' ).add( ( $content ) => {
		$content.find( 'div.mw-highlight-copy:not(.mw-highlight-copy--bound)' )
			.append( $btn.clone( true ) )
			.addClass( 'mw-highlight-copy--bound' );

		$content.find( 'code.mw-highlight-copy:not(.mw-highlight-copy--bound)' )
			.after( $btn.clone( true ) )
			.addClass( 'mw-highlight-copy--bound' );
	} );
}
