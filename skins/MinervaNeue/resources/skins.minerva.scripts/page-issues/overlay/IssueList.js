const
	mobile = require( 'mobile.startup' ),
	View = mobile.View,
	issueNotice = require( './IssueNotice.js' );

/**
 * IssueList
 *
 * @ignore
 * @param {IssueSummary} issues
 * @return {View}
 */
module.exports = function issueList( issues ) {
	return View.make( {
		tagName: 'ul',
		className: 'cleanup'
	}, ( issues || [] ).map( ( issue ) => issueNotice( issue ).$el ) );
};
