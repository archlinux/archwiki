const Constants = {
	caseStatuses: [ 'open', 'resolved', 'invalid' ],
	lastUpdatedOptions: [
		{ value: '1', labelMsg: 'checkuser-suggestedinvestigations-filter-dialog-last-updated-today' },
		{ value: '3', labelMsg: 'checkuser-suggestedinvestigations-filter-dialog-last-updated-last3days' },
		{ value: '7', labelMsg: 'checkuser-suggestedinvestigations-filter-dialog-last-updated-last7days' },
		{ value: '90', labelMsg: 'checkuser-suggestedinvestigations-filter-dialog-last-updated-last90days' },
		// The empty string value for 'all time' maps to null (no filter) on the server side.
		{ value: '', labelMsg: 'checkuser-suggestedinvestigations-filter-dialog-last-updated-all-time' }
	]
};

module.exports = Constants;
