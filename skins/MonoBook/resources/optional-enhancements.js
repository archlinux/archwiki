/* eslint-disable no-implicit-globals */
/**
 * Loads additional modules based on whether Echo or ULS extensions
 * have been installed.
 *
 * @return {jQuery.Deferred}
 */
function loadOptionalDependencies() {
	var optionalDependencies = [];
	// If the `ext.echo.init` module is null it means Echo has not been installed.
	// The Monobook Echo module should only be added if Echo is installed and user is logged in.
	if ( mw.loader.getState( 'ext.echo.init' ) !== null && !mw.user.isAnon() ) {
		optionalDependencies.push( 'skins.monobook.mobile.echohack' );
	}

	if ( mw.loader.getState( 'ext.uls.interface' ) !== null ) {
		optionalDependencies.push( 'skins.monobook.mobile.uls' );
	}

	return mw.loader.load( optionalDependencies );
}

module.exports = loadOptionalDependencies;
