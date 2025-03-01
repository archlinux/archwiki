/* eslint-disable no-jquery/no-class-state */
( function () {
	const watchstar = require( 'skins.minerva.scripts/watchstar.js' );
	const toggleClasses = watchstar.test.toggleClasses;
	const WATCHED_CLASS = watchstar.test.WATCHED_ICON_CLASS;
	const TEMP_WATCHED_CLASS = watchstar.test.TEMP_WATCHED_ICON_CLASS;
	const UNWATCHED_CLASS = watchstar.test.UNWATCHED_ICON_CLASS;

	QUnit.module( 'Minerva Watchstar' );

	function createElemWithClass( cssClass, iconClass ) {
		const $icon = $( '<span>' ).addClass( `minerva-icon ${ iconClass }` );
		return $( '<div>' ).addClass( cssClass ).append( $icon );
	}

	QUnit.test( 'toggleClasses() from watched to unwatched', ( assert ) => {
		const $elem = createElemWithClass( WATCHED_CLASS );
		toggleClasses( $elem, false );
		assert.true( $elem.find( '.minerva-icon' ).hasClass( UNWATCHED_CLASS ) );
	} );

	QUnit.test( 'toggleClasses() from unwatched to watched', ( assert ) => {
		const $elem = createElemWithClass( UNWATCHED_CLASS );
		toggleClasses( $elem, true, null );
		assert.true( $elem.find( '.minerva-icon' ).hasClass( WATCHED_CLASS ) );
	} );

	QUnit.test( 'toggleClasses() from unwatched to temp watched', ( assert ) => {
		const $elem = createElemWithClass( UNWATCHED_CLASS );
		toggleClasses( $elem, true, 'expiry' );
		assert.true( $elem.find( '.minerva-icon' ).hasClass( TEMP_WATCHED_CLASS ) );
	} );

	QUnit.test( 'toggleClasses() from temp watched to watched', ( assert ) => {
		const $elem = createElemWithClass( TEMP_WATCHED_CLASS );
		toggleClasses( $elem, true, null );
		assert.true( $elem.find( '.minerva-icon' ).hasClass( WATCHED_CLASS ) );
	} );

}() );
