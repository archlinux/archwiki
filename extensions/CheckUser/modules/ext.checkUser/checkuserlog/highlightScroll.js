/**
 * Scroll to the highlighted log entry in Special:CheckUserLog.
 *
 * If the page includes at least one entry that is highlighted, scroll to the first one.
 */
( function () {
	const $highlightEntry = $( '.mw-checkuser-log-highlight-entry' ).first();
	if (
		$highlightEntry.length > 0 &&
		$highlightEntry.offset().top > $( window ).height()
	) {
		$( window ).scrollTop(
			$highlightEntry.offset().top - 100
		);
	}
}() );
