const features = require( './features.js' );
const LIMITED_WIDTH_FEATURE_NAME = 'limited-width';

/**
 * Sets data attribute for click tracking purposes.
 *
 * @param {HTMLElement} toggleBtn
 */
function setDataAttribute( toggleBtn ) {
	toggleBtn.dataset.eventName = features.isEnabled( LIMITED_WIDTH_FEATURE_NAME ) ?
		'limited-width-toggle-off' : 'limited-width-toggle-on';
}
/**
 * adds a toggle button
 */
function init() {
	const toggle = document.createElement( 'button' );
	toggle.setAttribute( 'title', mw.msg( 'vector-limited-width-toggle' ) );
	toggle.setAttribute( 'aria-hidden', 'true' );
	toggle.textContent = mw.msg( 'vector-limited-width-toggle' );
	toggle.classList.add( 'mw-ui-icon', 'mw-ui-icon-element', 'mw-ui-button', 'vector-limited-width-toggle' );
	setDataAttribute( toggle );
	document.body.appendChild( toggle );
	toggle.addEventListener( 'click', function () {
		features.toggle( LIMITED_WIDTH_FEATURE_NAME );
		setDataAttribute( toggle );
		// Fire a simulated window resize event (T328121)
		let event;
		if ( typeof Event === 'function' ) {
			event = new Event( 'resize' );
		} else {
			// IE11
			event = window.document.createEvent( 'UIEvents' );
			event.initUIEvent( 'resize', true, false, window, 0 );
		}
		window.dispatchEvent( event );
	} );
}

module.exports = init;
