const
	mobile = require( 'mobile.startup' ),
	View = mobile.View;

/**
 * @ignore
 * @param {IssueSummary} issue
 * @return {View}
 */
module.exports = function issueNotice( issue ) {
	const $renderedTemplate = mw.template.get(
		'skins.minerva.scripts',
		'IssueNotice.mustache'
	).render( issue );
	$renderedTemplate.prepend( issue.issue.iconElement );
	return View.make( {
		tagName: 'li'
	}, [ $renderedTemplate ] );
};
