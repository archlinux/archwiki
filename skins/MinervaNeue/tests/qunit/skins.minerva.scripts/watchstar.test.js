/* eslint-disable no-jquery/no-class-state */
( function () {
	var watchstar = require( '../../../resources/skins.minerva.scripts/watchstar.js' ),
		toggleClasses = watchstar.test.toggleClasses,
		WATCHED_CLASS = watchstar.test.WATCHED_ICON_CLASS,
		TEMP_WATCHED_CLASS = watchstar.test.TEMP_WATCHED_ICON_CLASS,
		UNWATCHED_CLASS = watchstar.test.UNWATCHED_ICON_CLASS;

	QUnit.module( 'Minerva Watchstar' );

	function createElemWithClass( cssClass, iconClass ) {
		const $icon = $( '<span>' ).addClass( `minerva-icon ${iconClass}` );
		return $( '<div/>' ).addClass( cssClass ).append( $icon );
	}

	QUnit.test( 'toggleClasses() from watched to unwatched', function ( assert ) {
		var $elem = createElemWithClass( WATCHED_CLASS );
		toggleClasses( $elem, false );
		assert.true( $elem.find( '.minerva-icon' ).hasClass( UNWATCHED_CLASS ) );
	} );

	QUnit.test( 'toggleClasses() from unwatched to watched', function ( assert ) {
		var $elem = createElemWithClass( UNWATCHED_CLASS );
		toggleClasses( $elem, true, null );
		assert.true( $elem.find( '.minerva-icon' ).hasClass( WATCHED_CLASS ) );
	} );

	QUnit.test( 'toggleClasses() from unwatched to temp watched', function ( assert ) {
		var $elem = createElemWithClass( UNWATCHED_CLASS );
		toggleClasses( $elem, true, 'expiry' );
		assert.true( $elem.find( '.minerva-icon' ).hasClass( TEMP_WATCHED_CLASS ) );
	} );

	QUnit.test( 'toggleClasses() from temp watched to watched', function ( assert ) {
		var $elem = createElemWithClass( TEMP_WATCHED_CLASS );
		toggleClasses( $elem, true, null );
		assert.true( $elem.find( '.minerva-icon' ).hasClass( WATCHED_CLASS ) );
	} );

}() );
