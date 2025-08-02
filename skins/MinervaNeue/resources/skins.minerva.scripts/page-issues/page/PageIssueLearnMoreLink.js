/**
 * Creates a "read more" button with given text.
 *
 * @internal
 * @ignore
 * @param {string} msg
 * @return {jQuery}
 */
function newPageIssueLearnMoreLink( msg ) {
	return $( '<span>' )
		.addClass( 'ambox-learn-more' )
		.text( msg );
}

module.exports = newPageIssueLearnMoreLink;
