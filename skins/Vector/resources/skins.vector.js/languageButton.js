/**
 * Checks whether ULS is enabled and if so disables the default
 * drop down behavior of the button.
 */
function disableLanguageDropdown() {
	var ulsModuleStatus = mw.loader.getState( 'ext.uls.interface' ),
		pLangBtnLabel;

	if ( ulsModuleStatus && ulsModuleStatus !== 'registered' ) {
		mw.loader.using( 'ext.uls.interface' ).then( function () {
			var pLangBtn = document.getElementById( 'p-lang-btn' );
			if ( !pLangBtn ) {
				return;
			}

			pLangBtn.classList.add( 'vector-menu--hide-dropdown' );
		} );
	} else {
		pLangBtnLabel = document.getElementById( 'p-lang-btn-label' );
		if ( !pLangBtnLabel ) {
			return;
		}

		// Remove .mw-interlanguage-selector to show the dropdown arrow since evidently
		// ULS is not used.
		pLangBtnLabel.classList.remove( 'mw-interlanguage-selector' );
	}
}

/**
 * Initialize the language button.
 */
module.exports = function () {
	disableLanguageDropdown();
};
