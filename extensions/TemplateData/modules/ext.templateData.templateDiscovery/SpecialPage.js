'use strict';

/**
 * @class
 *
 * @constructor
 */
function SpecialPage() {
	this.templateSearchWidget = document.getElementById( 'ext-TemplateData-SpecialTemplateSearch-widget' );
	if ( !this.templateSearchWidget ) {
		// Throw an error if the required elements are not found
		throw new Error( 'Required elements not found' );
	}
}

/**
 * Initialize the special page
 */
SpecialPage.prototype.init = function () {
	const searchForm = new mw.templateData.TemplateSearchLayout();
	this.templateSearchWidget.append( searchForm.$element[ 0 ] );
	searchForm.focus();
	searchForm.on( 'choose', ( item ) => {
		location.href = mw.util.getUrl( item.title );
	} );
};

module.exports = SpecialPage;
