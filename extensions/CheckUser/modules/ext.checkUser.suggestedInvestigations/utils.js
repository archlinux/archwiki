/**
 * Updates the status of a case in the DOM after a successful use of
 * the dialog defined in components/ChangeInvestigationStatusDialog.vue
 * to change the status on the backend.
 *
 * @param {number} caseId
 * @param {'open'|'resolved'|'invalid'} status
 * @param {string} reason
 */
function updateCaseStatusOnPage( caseId, status, reason ) {
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
	// If the status is invalid and no reason is provided, then the reason defaults to
	// the message checkuser-suggestedinvestigations-status-reason-default-invalid
	const statusReasonElement = document.querySelector(
		'.mw-checkuser-suggestedinvestigations-status-reason' + caseIdDataSelector
	);
	if ( reason === '' && status === 'invalid' ) {
		statusReasonElement.textContent = mw.msg( 'checkuser-suggestedinvestigations-status-reason-default-invalid' );
	} else {
		statusReasonElement.textContent = reason;
	}

	// Because there isn't a good way to render Vue HTML outside the component or to infuse
	// CSS-only elements into Vue components, it will be easier to change the CSS classes for
	// the Codex chip elements using JQuery.
	const statusElement = document.querySelector(
		'.mw-checkuser-suggestedinvestigations-status' + caseIdDataSelector
	);

	// Update the icon associated with the status chip to reflect the new status
	let newIconClass;
	switch ( status ) {
		case 'open':
			newIconClass = 'cdx-info-chip--notice';
			break;
		case 'resolved':
			newIconClass = 'cdx-info-chip--success';
			break;
		case 'invalid':
		default:
			newIconClass = 'cdx-info-chip--warning';
			break;
	}

	const $chipIcon = $( statusElement.querySelector( '.cdx-info-chip' ) );
	$chipIcon.removeClass( [ 'cdx-info-chip--notice', 'cdx-info-chip--success', 'cdx-info-chip--warning' ] );
	// Classes are defined in the switch above
	// eslint-disable-next-line mediawiki/class-doc
	$chipIcon.addClass( newIconClass );

	// Update the status text to reflect the new status
	const chipText = statusElement.querySelector( '.cdx-info-chip--text' );
	// Uses:
	// * checkuser-suggestedinvestigations-status-open
	// * checkuser-suggestedinvestigations-status-resolved
	// * checkuser-suggestedinvestigations-status-invalid
	chipText.textContent = mw.msg( 'checkuser-suggestedinvestigations-status-' + status );
}

module.exports = {
	updateCaseStatusOnPage: updateCaseStatusOnPage
};
