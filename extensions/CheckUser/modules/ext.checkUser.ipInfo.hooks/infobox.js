function addSpecialGlobalContributionsLink( $info, info, generateMarkup, target, pageName ) {
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
		const globalContributionsCount = 'ipinfo-source-checkuser' in info.data ?
			info.data[ 'ipinfo-source-checkuser' ].globalContributionsCount : 0;
		const $globalContributions = $( '<div>' );

		const $globalContributionsCount = $( '<span>' )
			.addClass( 'ipinfo-widget-value-global-contributions' )
			.text( mw.msg( 'checkuser-ipinfo-global-contributions-value', globalContributionsCount ) );
		$globalContributions.append( $globalContributionsCount );
		const globalContributionsUrl = mw.util.getUrl( 'Special:GlobalContributions', { target } );
		const $globalContributionsLink = $( '<div>' )
			.addClass( 'ext-ipinfo-global-contribution-link' )
			.append( $( '<a>' )
				.attr( 'href', globalContributionsUrl )
				.text( mw.msg( 'checkuser-ipinfo-global-contributions-url-text' ) ) );
		$globalContributions.append( $globalContributionsLink );

		const $globalContributionsProperty = generateMarkup(
			'global-contributions',
			$globalContributions,
			mw.msg( 'checkuser-ipinfo-global-contributions-label' ),
			mw.msg( 'checkuser-ipinfo-global-contributions-tooltip' ) );

		$info.find( '[data-property="edits"]' )
			.after( $globalContributionsProperty );

	}
}

module.exports = addSpecialGlobalContributionsLink;
