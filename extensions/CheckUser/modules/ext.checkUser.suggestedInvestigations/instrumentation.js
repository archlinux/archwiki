'use strict';

/**
 * Initialize instrumentation for Suggested Investigations.
 *
 * @return {void}
 */
module.exports = () => {
	const useInstrument = require( './composables/useInstrument.js' );
	const logEvent = useInstrument();

	// T418740: Instrumentation for the "SI cases" link on contributions special pages
	const $siCasesLink = $( '.mw-contributions-link-suggested-investigations' );
	if ( $siCasesLink.length !== 0 ) {
		$siCasesLink.on(
			'click',
			() => logEvent(
				'contributions_toollink_click',
				{ context: mw.config.get( 'wgRelevantUserName' ) }
			)
		);
	}

	// T418740: Instrumentation for the "SI cases" links on Special:CheckUser Get Users results.
	// Use mousedown instead of click to also capture middle-click and right-click
	// "Open in new tab", since users need to keep the CheckUser results page open.
	const $siCasesLinks = $( '.mw-checkuser-get-users-results .mw-checkuser-si-cases-link' );
	if ( $siCasesLinks.length !== 0 ) {
		$siCasesLinks.on(
			'mousedown',
			() => logEvent( 'checkuser_si_cases_link_click', {
				context: 'special_checkuser_get_users'
			} )
		);
	}
};
