/*
 * Loads Echo on CentralAuth autologin
 */
mw.hook( 'centralauth-p-personal-reset' ).add( () => {
	mw.loader.using( [
		'ext.echo.init',
		'ext.echo.styles.badge',
		'oojs-ui.styles.icons-alerts',
		'ext.echo.styles.alert'
	] );
} );
