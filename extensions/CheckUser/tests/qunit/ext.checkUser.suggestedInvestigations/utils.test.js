'use strict';

const utils = require( 'ext.checkUser.suggestedInvestigations/utils.js' );

QUnit.module( 'ext.checkUser.suggestedInvestigations.utils', QUnit.newMwEnvironment() );

/**
 * Maps a status to a CSS class representing the chip type
 *
 * @param {'open'|'resolved'|'invalid'} status
 * @return {string}
 */
function mapStatusToChipType( status ) {
	switch ( status ) {
		case 'open':
			return 'cdx-info-chip--notice';
		case 'resolved':
			return 'cdx-info-chip--success';
		case 'invalid':
			return 'cdx-info-chip--warning';
		default:
			return '';
	}
}

/**
 * Generates an element which matches the structure of the button used to
 * update the status of a case
 *
 * @param {number} caseId
 * @param {'open'|'resolved'|'invalid'} status
 * @param {string} reason
 * @return {Element}
 */
const generateChangeStatusButton = ( caseId, status, reason ) => {
	const changeStatusButton = document.createElement( 'span' );
	changeStatusButton.setAttribute( 'class', 'mw-checkuser-suggestedinvestigations-change-status-button' );
	changeStatusButton.setAttribute( 'data-case-id', caseId );
	changeStatusButton.setAttribute( 'data-case-status', status );
	changeStatusButton.setAttribute( 'data-case-status-reason', reason );
	return changeStatusButton;
};

/**
 * Generates an element which matches the structure of the status chip
 * cell for a row in the special page
 *
 * @param {number} caseId
 * @param {'open'|'resolved'|'invalid'} status
 * @return {Element}
 */
const generateStatusElement = ( caseId, status ) => {
	const statusElement = document.createElement( 'div' );
	statusElement.setAttribute( 'class', 'mw-checkuser-suggestedinvestigations-status' );
	statusElement.setAttribute( 'data-case-id', caseId );

	const chipElement = document.createElement( 'div' );
	chipElement.setAttribute( 'class', 'cdx-info-chip ' + mapStatusToChipType( status ) );

	const chipTextElement = document.createElement( 'span' );
	chipTextElement.setAttribute( 'class', 'cdx-info-chip--text' );
	chipTextElement.textContent = '(checkuser-suggestedinvestigations-status-' + status + ')';

	chipElement.append( chipTextElement );
	statusElement.append( chipElement );

	return statusElement;
};

/**
 * Generates an element which matches the structure of the "notes" cell
 * for a row in the special page
 *
 * @param {number} caseId
 * @param {string} statusReasonText
 * @return {Element}
 */
const generateStatusReasonElement = ( caseId, statusReasonText ) => {
	const statusReasonElement = document.createElement( 'span' );
	statusReasonElement.setAttribute( 'class', 'mw-checkuser-suggestedinvestigations-status-reason' );
	statusReasonElement.setAttribute( 'data-case-id', caseId );
	statusReasonElement.textContent = statusReasonText;
	return statusReasonElement;
};

QUnit.test.each( 'Test updateCaseStatusOnPage', {
	'status goes from open to resolved': [
		'open', '', 'resolved', 'testingabc'
	],
	'status goes from resolved to open': [
		'resolved', 'testingabc', 'open', 'testingabc'
	],
	'status goes from open to invalid with a reason provided': [
		'open', '', 'invalid', 'testing'
	],
	'status goes from open to invalid with no reason provided': [
		'open', '', 'invalid', ''
	],
	'status goes from invalid to resolved': [
		'invalid', 'false positive', 'resolved', 'case resolved'
	],
	'no change in status, but change in reason': [
		'resolved', 'testingabc', 'resolved', 'testingabcdef'
	]
}, ( assert, [ initialStatus, initialStatusReason, newStatus, newStatusReason ] ) => {
	// eslint-disable-next-line no-jquery/no-global-selector
	const $qunitFixture = $( '#qunit-fixture' );
	const statusElement = generateStatusElement( 123, initialStatus );
	$qunitFixture.append( statusElement );
	const statusReasonElement = generateStatusReasonElement( 123, initialStatusReason );
	$qunitFixture.append( statusReasonElement );
	const changeStatusButton = generateChangeStatusButton(
		123, initialStatus, initialStatusReason
	);
	$qunitFixture.append( changeStatusButton );

	utils.updateCaseStatusOnPage( 123, newStatus, newStatusReason );

	assert.strictEqual(
		changeStatusButton.getAttribute( 'data-case-status' ),
		newStatus,
		'New status in data attribute is correct'
	);
	assert.strictEqual(
		changeStatusButton.getAttribute( 'data-case-status-reason' ),
		newStatusReason,
		'New status reason in data attribute is correct'
	);

	if ( newStatus === 'invalid' && newStatusReason === '' ) {
		assert.strictEqual(
			statusReasonElement.textContent,
			'(checkuser-suggestedinvestigations-status-reason-default-invalid)',
			'New status reason should use the default for the invalid status'
		);
	} else {
		assert.strictEqual(
			statusReasonElement.textContent,
			newStatusReason,
			'New status reason is correct'
		);
	}
	assert.strictEqual(
		statusElement.querySelector( '.cdx-info-chip--text' ).textContent,
		'(checkuser-suggestedinvestigations-status-' + newStatus + ')',
		'New status reason is correct'
	);
	assert.strictEqual(
		statusElement.querySelector( '.cdx-info-chip' ).getAttribute( 'class' ),
		'cdx-info-chip ' + mapStatusToChipType( newStatus ),
		'New status reason is correct'
	);
} );
