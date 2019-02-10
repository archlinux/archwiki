// eslint-disable-next-line no-unused-vars
var Widgets = {};
/*
* Back to top button
* Taken from https://codepen.io/desirecode/pen/MJPJqV/
*/
$( document ).ready( function () {
	$( window ).scroll( function () {
		if ( $( this ).scrollTop() > 100 ) {
			$( '.scroll' ).fadeIn();
		} else {
			$( '.scroll' ).fadeOut();
		}
	} );
	$( '.scroll' ).click( function () {
		$( 'html, body' ).animate( { scrollTop: 0 }, 600 );
		return false;
	} );
} );
