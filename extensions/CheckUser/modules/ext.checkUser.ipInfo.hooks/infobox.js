function addSpecialGlobalContributionsLink( $info, target, pageName ) {
	if (
		(
			pageName === 'IPContributions' &&
			mw.util.isIPAddress( target )
		) ||
		(
			[ 'Contributions', 'DeletedContributions' ].includes( pageName ) &&
			mw.util.isTemporaryUser( target )
		)
	) {
		const globalContributionsUrl = mw.util.getUrl( 'Special:GlobalContributions', { target } );
		const $globalEdits = $( '<div>' )
			.addClass( 'ext-ipinfo-global-contribution-link' )
			.append( $( '<a>' )
				.attr( 'href', globalContributionsUrl )
				.text( mw.msg( 'ext-ipinfo-global-contributions-url-text' ) ) );
		$info.find( '[data-property="edits"]' )
			.append( $globalEdits );
	}
}

module.exports = addSpecialGlobalContributionsLink;
