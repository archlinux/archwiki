/**
 * @typedef {Object} ClientPreference
 * @property {string[]} options that are valid for this client preference
 * @property {string} preferenceKey for registered users.
 */
const config = /** @type {Record<string,ClientPreference>} */( require( './config.json' ) );
let /** @type {MwApi} */ api;
/**
 * @typedef {Object} PreferenceOption
 * @property {string} label
 * @property {string} value
 *
 */

/**
 * @return {string[]} of active client preferences
 */
function getClientPreferences() {
	return Array.from( document.documentElement.classList ).filter(
		( className ) => className.match( /-clientpref-/ )
	).map( ( className ) => className.split( '-clientpref-' )[ 0 ] );
}

/**
 * @param {Element} parent
 * @param {string} featureName
 * @param {ClientPreference} pref
 * @param {string} value
 * @param {string} currentValue
 */
function appendRadioToggle( parent, featureName, pref, value, currentValue ) {
	const input = document.createElement( 'input' );
	const name = `vector-client-pref-${featureName}-group`;
	const id = `vector-client-pref-${featureName}-value-${value}`;
	input.name = name;
	input.id = id;
	input.type = 'radio';
	input.value = value;
	if ( currentValue === value ) {
		input.checked = true;
	}
	const label = document.createElement( 'label' );
	// eslint-disable-next-line mediawiki/msg-doc
	label.textContent = mw.msg( `${featureName}-${value}-label` );
	label.setAttribute( 'for', id );
	const container = document.createElement( 'div' );
	container.appendChild( input );
	container.appendChild( label );
	parent.appendChild( container );
	input.addEventListener( 'change', () => {
		// @ts-ignore https://github.com/wikimedia/typescript-types/pull/44
		mw.user.clientPrefs.set( featureName, value );
		if ( mw.user.isNamed() ) {
			mw.util.debounce( function () {
				api = api || new mw.Api();
				api.saveOption( pref.preferenceKey, value );
			}, 100 )();
		}
	} );
}

/**
 * @param {string} className
 * @return {Element}
 */
function createRow( className ) {
	const row = document.createElement( 'div' );
	row.setAttribute( 'class', className );
	return row;
}

/**
 * adds a toggle button
 *
 * @param {string} featureName
 * @param {string} label
 * @return {Element|null}
 */
function makeClientPreferenceBinaryToggle( featureName, label ) {
	const pref = config[ featureName ];
	if ( !pref ) {
		return null;
	}
	// @ts-ignore https://github.com/wikimedia/typescript-types/pull/44
	const currentValue = mw.user.clientPrefs.get( featureName );
	// The client preference was invalid. This shouldn't happen unless a gadget
	// or script has modified the documentElement.
	if ( !currentValue ) {
		return null;
	}
	const row = createRow( '' );
	const form = document.createElement( 'form' );
	const labelNode = document.createElement( 'label' );
	labelNode.textContent = label;
	form.appendChild( labelNode );
	const toggle = document.createElement( 'fieldset' );
	pref.options.forEach( ( value ) => {
		appendRadioToggle( toggle, featureName, pref, value, currentValue );
	} );
	form.appendChild( toggle );
	row.appendChild( form );
	return row;
}

/**
 * @param {string} featureName
 * @return {Element|null}
 */
function makeClientPreference( featureName ) {
	// eslint-disable-next-line mediawiki/msg-doc
	const labelMsg = mw.message( `${featureName}-name` );
	// If the user is not debugging messages and no language exists exit as its a hidden client preference.
	if ( !labelMsg.exists() && mw.config.get( 'wgUserLanguage' ) !== 'qqx' ) {
		return null;
	} else {
		return makeClientPreferenceBinaryToggle( featureName, labelMsg.text() );
	}
}

/**
 * Fills the client side preference dropdown with controls.
 */
function fillClientPreferencesDropdown() {
	const dropdownContents = document.querySelectorAll( '#vector-client-prefs .vector-dropdown-content' )[ 0 ];
	if ( !dropdownContents ) {
		return;
	}
	getClientPreferences().forEach( ( pref ) => {
		const prefNode = makeClientPreference( pref );
		if ( prefNode ) {
			dropdownContents.appendChild( prefNode );
		}
	} );
}

module.exports = fillClientPreferencesDropdown;
