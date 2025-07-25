/**
 * Create a link element that opens the issues overlay.
 *
 * @internal
 * @ignore
 * @param {string} labelText The text value of the element
 * @return {jQuery}
 */
function newPageIssueLink( labelText ) {
	return $( '<a>' ).addClass( 'cleanup mw-mf-cleanup' ).text( labelText );
}

module.exports = newPageIssueLink;
