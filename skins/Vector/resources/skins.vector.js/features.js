/** @interface MwApi */

const debounce = require( /** @type {string} */ ( 'mediawiki.util' ) ).debounce;
const userPreferences = require( './userPreferences.js' );

/**
 * Saves preference to user preferences and/or cookies.
 *
 * @param {string} feature
 * @param {boolean} enabled
 */
function save( feature, enabled ) {
	if ( !mw.user.isNamed() ) {
		switch ( feature ) {
			case 'toc-pinned':
			case 'limited-width':
			case 'appearance-pinned':
				// Save the setting under the new system
				mw.user.clientPrefs.set( `vector-feature-${ feature }`, enabled ? '1' : '0' );
				break;
			default:
				// not a supported anonymous preference
				break;
		}
	} else {
		debounce( () => {
			userPreferences.saveOptions( {
				[ `vector-${ feature }` ]: enabled ? 1 : 0
			} );
		}, 500 )();
	}
}

/**
 * @param {string} name feature name
 * @param {boolean} [override] option to force enabled or disabled state.
 * @param {boolean} [isNotClientPreference] the feature is not a client preference,
 *  so does not persist for logged out users.
 * @return {boolean} The new feature state (false=disabled, true=enabled).
 * @throws {Error} if unknown feature toggled.
 */
function toggleDocClasses( name, override, isNotClientPreference ) {
	const suffixEnabled = isNotClientPreference ? 'enabled' : 'clientpref-1';
	const suffixDisabled = isNotClientPreference ? 'disabled' : 'clientpref-0';
	const featureClassEnabled = `vector-feature-${ name }-${ suffixEnabled }`,
		classList = document.documentElement.classList,
		featureClassDisabled = `vector-feature-${ name }-${ suffixDisabled }`,
		// If neither of the classes can be found it is a legacy feature
		isLoggedInOnlyFeature = !classList.contains( featureClassDisabled ) &&
			!classList.contains( featureClassEnabled );

	// Check in legacy mode.
	if ( isLoggedInOnlyFeature && !isNotClientPreference ) {
		// try again using the legacy classes
		return toggleDocClasses( name, override, true );
	} else if ( classList.contains( featureClassDisabled ) || override === true ) {
		classList.remove( featureClassDisabled );
		classList.add( featureClassEnabled );
		return true;
	} else if ( classList.contains( featureClassEnabled ) || override === false ) {
		classList.add( featureClassDisabled );
		classList.remove( featureClassEnabled );
		return false;
	} else {
		throw new Error( `Attempt to toggle unknown feature: ${ name }` );
	}
}

/**
 * @param {string} name
 * @throws {Error} if unknown feature toggled.
 */
function toggle( name ) {
	const featureState = toggleDocClasses( name );
	save( name, featureState );
}

/**
 * Checks if the feature is enabled.
 *
 * @param {string} name
 * @return {boolean}
 */
function isEnabled( name ) {
	return document.documentElement.classList.contains( getClass( name, true ) ) ||
		document.documentElement.classList.contains( getClass( name, true, true ) );
}

/**
 * Get name of feature class.
 *
 * @param {string} name
 * @param {boolean} featureEnabled
 * @param {boolean} [isClientPreference] whether the feature is also a client preference
 * @return {string}
 */
function getClass( name, featureEnabled, isClientPreference ) {
	if ( featureEnabled ) {
		const suffix = isClientPreference ? 'clientpref-1' : 'enabled';
		return `vector-feature-${ name }-${ suffix }`;
	} else {
		const suffix = isClientPreference ? 'clientpref-0' : 'disabled';
		return `vector-feature-${ name }-${ suffix }`;
	}
}

module.exports = { getClass, isEnabled, toggle, toggleDocClasses };
