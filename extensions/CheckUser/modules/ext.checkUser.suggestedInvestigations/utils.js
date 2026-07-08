/**
 * Updates the status of a case in the DOM after a successful use of
 * the dialog defined in components/ChangeInvestigationStatusDialog.vue
 * to change the status on the backend.
 *
 * @param {number} caseId
 * @param {'open'|'resolved'|'invalid'} status
 * @param {string} reason Reason to be shown in the change status dialog input
 * @param {string} formattedReason Reason to be shown in the DOM which may be HTML
 */
function updateCaseStatusOnPage( caseId, status, reason, formattedReason ) {
	// Set the updated data in the data-* properties of the edit button so that opening
	// the dialog in the future uses the new data
	const caseIdDataSelector = '[data-case-id="' + caseId + '"]';
	const changeStatusButton = document.querySelector(
		'.mw-checkuser-suggestedinvestigations-change-status-button' + caseIdDataSelector
	);
	changeStatusButton.setAttribute( 'data-case-status', status );
	changeStatusButton.setAttribute( 'data-case-status-reason', reason );

	// Update the pager row to reflect the new values for the status and status reason
	// so we can avoid refreshing the page (refreshing the page may change the order of
	// the cases on the screen)
	const statusReasonElement = document.querySelector(
		'.mw-checkuser-suggestedinvestigations-status-reason' + caseIdDataSelector
	);
	statusReasonElement.innerHTML = formattedReason;

	// Because there isn't a good way to render Vue HTML outside the component or to infuse
	// CSS-only elements into Vue components, it will be easier to change the CSS classes for
	// the Codex chip elements using JQuery.
	const statusElement = document.querySelector(
		'.mw-checkuser-suggestedinvestigations-status' + caseIdDataSelector
	);

	// Update the icon associated with the status chip to reflect the new status
	const $chipIcon = $( statusElement.querySelector( '.cdx-info-chip' ) );
	$chipIcon.removeClass( [ 'cdx-info-chip--notice', 'cdx-info-chip--success', 'cdx-info-chip--warning' ] );
	// Uses:
	// * cdx-info-chip--notice
	// * cdx-info-chip--success
	// * cdx-info-chip--warning
	$chipIcon.addClass( 'cdx-info-chip--' + caseStatusToChipStatus( status ) );

	// Update the status text to reflect the new status
	const chipText = statusElement.querySelector( '.cdx-info-chip--text' );
	// Uses:
	// * checkuser-suggestedinvestigations-status-open
	// * checkuser-suggestedinvestigations-status-resolved
	// * checkuser-suggestedinvestigations-status-invalid
	chipText.textContent = mw.msg( 'checkuser-suggestedinvestigations-status-' + status );
}

/**
 * Returns the CdxInfoChip status associated with the given case status
 *
 * @param {'open'|'resolved'|'invalid'} caseStatus
 * @return {'notice'|'success'|'warning'}
 */
function caseStatusToChipStatus( caseStatus ) {
	switch ( caseStatus ) {
		case 'open':
			return 'notice';
		case 'resolved':
			return 'success';
		case 'invalid':
		default:
			return 'warning';
	}
}

/**
 * Updates the filters on the current page by redirecting the user to a view with
 * the provided filters.
 *
 * @param {*} filters A list of filters in a format acceptable for
 *   the params parameter for `mw.util.getUrl`
 * @param {window} win
 */
function updateFiltersOnPage( filters, win ) {
	// Let's preserve the limit setting if it exists
	const urlParams = new URLSearchParams( win.location.search );
	const limit = urlParams.get( 'limit' );
	if ( limit ) {
		filters.limit = limit;
	}

	let newUrl = mw.config.get( 'wgServer' );
	newUrl += mw.util.getUrl( mw.config.get( 'wgPageName' ), filters );
	win.location.replace( newUrl );
}

module.exports = {
	updateCaseStatusOnPage: updateCaseStatusOnPage,
	caseStatusToChipStatus: caseStatusToChipStatus,
	updateFiltersOnPage: updateFiltersOnPage
};
