/**
 * @typedef {Object} ClientPreference
 * @property {string[]} options that are valid for this client preference
 * @property {string} preferenceKey for registered users.
 * @property {string} betaMessage whether to show a notice indicating this feature is in beta.
 * @property {string} [type] defaults to radio. Supported: radio, switch
 * @property {Function} [callback] callback executed after a client preference has been modified.
 */

/**
 * @typedef {Object} UserPreferencesApi
 * @property {Function} saveOptions
 */
/**
 * @typedef {Object} PreferenceOption
 * @property {string} label
 * @property {string} value
 */

/**
 * Get the list of client preferences that are active on the page, including hidden.
 *
 * @return {string[]} of active client preferences
 */
function getClientPreferences() {
	return Array.from( document.documentElement.classList ).filter(
		( className ) => className.match( /-clientpref-/ )
	).map( ( className ) => className.split( '-clientpref-' )[ 0 ] );
}

/**
 * Check if the feature is excluded from the current page.
 *
 * @param {string} featureName
 * @return {boolean}
 */
function isFeatureExcluded( featureName ) {
	return document.documentElement.classList.contains( featureName + '-clientpref--excluded' );
}

/**
 * Get the list of client preferences that are active on the page and not hidden.
 *
 * @param {Record<string,ClientPreference>} config
 * @return {string[]} of user facing client preferences
 */
function getVisibleClientPreferences( config ) {
	const active = getClientPreferences();
	// Order should be based on key in config.json
	return Object.keys( config ).filter( ( key ) => active.indexOf( key ) > -1 );
}

/**
 * @param {string} featureName
 * @param {string} value
 * @param {Record<string,ClientPreference>} config
 * @param {UserPreferencesApi} [userPreferences]
 */
function toggleDocClassAndSave( featureName, value, config, userPreferences ) {
	const pref = config[ featureName ];
	const callback = pref.callback || ( () => {} );
	if ( mw.user.isNamed() ) {
		// FIXME: Ideally this would be done in mw.user.clientprefs API.
		// mw.user.clientPrefs.get is marked as being only stable for anonymous and temporary users.
		// So instead we have to keep track of all the different possible values and remove them
		// before adding the new class.
		config[ featureName ].options.forEach( ( possibleValue ) => {
			document.documentElement.classList.remove( `${ featureName }-clientpref-${ possibleValue }` );
		} );
		document.documentElement.classList.add( `${ featureName }-clientpref-${ value }` );
		// Client preferences often change the layout of the page significantly, so emit
		// a window resize event for other apps that need to update (T374092).
		window.dispatchEvent( new Event( 'resize' ) );
		// Ideally this should be taken care of via a single core helper function.
		mw.util.debounce( () => {
			userPreferences = userPreferences || new mw.Api();
			userPreferences.saveOptions( { [ pref.preferenceKey ]: value } ).then( () => {
				callback();
			} );
		}, 100 )();
		// END FIXME.
	} else {
		// This case is much simpler, the API transparently takes care of classes as well as storage
		mw.user.clientPrefs.set( featureName, value );
		callback();
	}
}

/**
 * @param {string} featureName
 * @param {string} value
 * @return {string}
 */
const getInputId = ( featureName, value ) => `skin-client-pref-${ featureName }-value-${ value }`;

/**
 * @param {string} type
 * @param {string} featureName
 * @param {string} value
 * @return {HTMLInputElement}
 */
function makeInputElement( type, featureName, value ) {
	const input = document.createElement( 'input' );
	const name = `skin-client-pref-${ featureName }-group`;
	const id = getInputId( featureName, value );
	input.name = name;
	input.id = id;
	input.type = type;
	if ( type === 'checkbox' ) {
		input.checked = value === '1';
	} else {
		input.value = value;
	}
	input.setAttribute( 'data-event-name', id );
	return input;
}

/**
 * @param {string} featureName
 * @param {string} value
 * @return {HTMLLabelElement}
 */
function makeLabelElement( featureName, value ) {
	const label = document.createElement( 'label' );
	// eslint-disable-next-line mediawiki/msg-doc
	label.textContent = mw.msg( `${ featureName }-${ value }-label` );
	label.setAttribute( 'for', getInputId( featureName, value ) );
	return label;
}

/**
 * Create an element that informs users that a feature is not functional
 * on a given page. This message is hidden by default and made visible in
 * CSS if a specific exclusion class exists.
 *
 * @param {string} featureName
 * @return {HTMLElement}
 */
function makeExclusionNotice( featureName ) {
	const p = document.createElement( 'p' );
	// eslint-disable-next-line mediawiki/msg-doc
	const noticeMessage = mw.message( `${ featureName }-exclusion-notice` );
	p.classList.add( 'exclusion-notice', `${ featureName }-exclusion-notice` );
	p.textContent = noticeMessage.text();
	return p;
}

/**
 * @return {HTMLElement}
 */
function makeBetaInfoTag() {
	const infoTag = document.createElement( 'span' );
	// custom style to avoid moving heading bottom border.
	const infoTagText = document.createElement( 'span' );
	infoTagText.textContent = mw.message( 'vector-night-mode-beta-tag' ).text();
	infoTag.appendChild( infoTagText );
	return infoTag;
}

/**
 * @param {Element} parent
 * @param {string} featureName
 * @param {string} value
 * @param {string} currentValue
 * @param {Record<string,ClientPreference>} config
 * @param {UserPreferencesApi} userPreferences
 */
function appendRadioToggle( parent, featureName, value, currentValue, config, userPreferences ) {
	const input = makeInputElement( 'radio', featureName, value );
	input.classList.add( 'cdx-radio__input' );
	if ( currentValue === value ) {
		input.checked = true;
	}

	if ( isFeatureExcluded( featureName ) ) {
		input.disabled = true;
	}

	const icon = document.createElement( 'span' );
	icon.classList.add( 'cdx-radio__icon' );
	const label = makeLabelElement( featureName, value );
	label.classList.add( 'cdx-radio__label' );
	const container = document.createElement( 'div' );
	container.classList.add( 'cdx-radio' );
	container.appendChild( input );
	container.appendChild( icon );
	container.appendChild( label );
	parent.appendChild( container );
	input.addEventListener( 'change', () => {
		toggleDocClassAndSave( featureName, value, config, userPreferences );
	} );
}

/**
 * @param {HTMLElement} betaMessageElement
 */
function makeFeedbackLink( betaMessageElement ) {
	const pageWikiLink = `[https://${ window.location.hostname + mw.util.getUrl( mw.config.get( 'wgPageName' ) ) } ${ mw.config.get( 'wgTitle' ) }]`;
	const preloadTitle = mw.message( 'vector-night-mode-issue-reporting-preload-title', pageWikiLink ).text();
	const link = mw.msg( 'vector-night-mode-issue-reporting-notice-url', window.location.host, preloadTitle );
	const linkLabel = mw.message( 'vector-night-mode-issue-reporting-link-label' ).text();
	const anchor = document.createElement( 'a' );
	anchor.setAttribute( 'href', link );
	anchor.setAttribute( 'target', '_blank' );
	anchor.setAttribute( 'title', mw.msg( 'vector-night-mode-issue-reporting-notice-tooltip' ) );
	anchor.textContent = linkLabel;

	/**
	 * Shows the success message after clicking the beta feedback link.
	 * Note: event.stopPropagation(); is required to show the success message
	 * without closing the Appearance menu when it's in a dropdown.
	 *
	 * @param {Event} event
	 */
	const showSuccessFeedback = function ( event ) {
		event.stopPropagation();
		const icon = document.createElement( 'span' );
		icon.classList.add( 'vector-icon', 'vector-icon--heart' );
		anchor.textContent = mw.msg( 'vector-night-mode-issue-reporting-link-notification' );
		anchor.classList.add( 'skin-theme-beta-notice-success' );
		anchor.prepend( icon );
		anchor.removeEventListener( 'click', showSuccessFeedback );
	};
	anchor.addEventListener( 'click', ( event ) => showSuccessFeedback( event ) );
	betaMessageElement.appendChild( anchor );
}

/**
 * @param {Element} form
 * @param {string} featureName
 * @param {HTMLElement} labelElement
 * @param {string} currentValue
 * @param {Record<string,ClientPreference>} config
 * @param {UserPreferencesApi} userPreferences
 */
function appendToggleSwitch(
	form, featureName, labelElement, currentValue, config, userPreferences
) {
	const input = makeInputElement( 'checkbox', featureName, currentValue );
	input.classList.add( 'cdx-toggle-switch__input' );
	const switcher = document.createElement( 'span' );
	switcher.classList.add( 'cdx-toggle-switch__switch' );
	const grip = document.createElement( 'span' );
	grip.classList.add( 'cdx-toggle-switch__switch__grip' );
	switcher.appendChild( grip );
	const label = labelElement || makeLabelElement( featureName, currentValue );
	label.classList.add( 'cdx-toggle-switch__label' );
	const toggleSwitch = document.createElement( 'span' );
	toggleSwitch.classList.add( 'cdx-toggle-switch' );
	toggleSwitch.appendChild( input );
	toggleSwitch.appendChild( switcher );
	toggleSwitch.appendChild( label );
	input.addEventListener( 'change', () => {
		toggleDocClassAndSave( featureName, input.checked ? '1' : '0', config, userPreferences );
	} );
	form.appendChild( toggleSwitch );
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
 * Get the label for the feature.
 *
 * @param {string} featureName
 * @return {MwMessage}
 */
// eslint-disable-next-line mediawiki/msg-doc
const getFeatureLabelMsg = ( featureName ) => mw.message( `${ featureName }-name` );

/**
 * adds a toggle button
 *
 * @param {string} featureName
 * @param {Record<string,ClientPreference>} config
 * @param {UserPreferencesApi} userPreferences
 * @return {Element|null}
 */
function makeControl( featureName, config, userPreferences ) {
	const pref = config[ featureName ];
	const isExcluded = isFeatureExcluded( featureName );

	if ( !pref ) {
		return null;
	}
	const currentValue = mw.user.clientPrefs.get( featureName );
	// The client preference was invalid. This shouldn't happen unless a gadget
	// or script has modified the documentElement or client preference is excluded.
	if ( typeof currentValue === 'boolean' && !isExcluded ) {
		return null;
	}
	const row = createRow( '' );
	const form = document.createElement( 'form' );
	const type = pref.type || 'radio';
	switch ( type ) {
		case 'radio':
			pref.options.forEach( ( value ) => {
				appendRadioToggle(
					form, featureName, value, String( currentValue ), config, userPreferences
				);
			} );
			break;
		case 'switch': {
			const labelElement = document.createElement( 'label' );
			labelElement.textContent = getFeatureLabelMsg( featureName ).text();
			appendToggleSwitch(
				form, featureName, labelElement, String( currentValue ), config, userPreferences
			);
			break;
		} default:
			throw new Error( 'Unknown client preference! Only switch or radio are supported.' );
	}
	row.appendChild( form );

	if ( isExcluded ) {
		const exclusionNotice = makeExclusionNotice( featureName );
		row.appendChild( exclusionNotice );
	}
	return row;
}

/**
 * @param {Element} parent
 * @param {string} featureName
 * @param {Record<string,ClientPreference>} config
 * @param {UserPreferencesApi} userPreferences
 */
function makeClientPreference( parent, featureName, config, userPreferences ) {
	const labelMsg = getFeatureLabelMsg( featureName );
	// If the user is not debugging messages and no language exists,
	// exit as its a hidden client preference.
	if ( !labelMsg.exists() && mw.config.get( 'wgUserLanguage' ) !== 'qqx' ) {
		return;
	} else {
		const id = `skin-client-prefs-${ featureName }`;
		// @ts-ignore TODO: upstream patch URL
		const portlet = mw.util.addPortlet( id, labelMsg.text() );

		if ( config[ featureName ].betaMessage ) {
			const betaInfoTag = makeBetaInfoTag();
			if ( !portlet.querySelector( '.vector-menu-heading span' ) ) {
				portlet.querySelector( '.vector-menu-heading' ).textContent += ' ';
				portlet.querySelector( '.vector-menu-heading' ).appendChild( betaInfoTag );
			}
		}

		const labelElement = portlet.querySelector( 'label' );

		// Add additional description for mobile
		// eslint-disable-next-line mediawiki/msg-doc
		const descriptionMsg = mw.message( `${ featureName }-description` );
		if ( descriptionMsg.exists() ) {
			const desc = document.createElement( 'span' );
			desc.classList.add( 'skin-client-pref-description' );
			desc.textContent = descriptionMsg.text();
			if ( labelElement && labelElement.parentNode ) {
				labelElement.appendChild( desc );
			}
		}

		// Add exclusion notice for desktop
		// eslint-disable-next-line mediawiki/msg-doc
		const exclusionNoticeMsg = mw.message( `${ featureName }-exclusion-notice` );
		if ( exclusionNoticeMsg.exists() ) {
			const content = portlet.querySelector( '.vector-menu-content' );
			const notice = document.createElement( 'span' );
			notice.classList.add( 'skin-client-pref-exclusion-notice' );
			notice.textContent = exclusionNoticeMsg.text();
			if ( content ) {
				content.appendChild( notice );
			}
		}

		parent.appendChild( portlet );
		const row = makeControl( featureName, config, userPreferences );
		if ( row ) {
			const tmp = mw.util.addPortletLink( id, '', '' );
			// create a dummy link
			if ( tmp ) {
				const link = tmp.querySelector( 'a' );
				if ( link ) {
					link.replaceWith( row );
				}
			}

			if ( config[ featureName ].betaMessage && !isFeatureExcluded( featureName ) ) {
				const betaMessageElement = document.createElement( 'span' );
				betaMessageElement.id = `${ featureName }-beta-notice`;
				// per requirements: only logged in users can report errors (T372754)
				if ( !mw.user.isAnon() ) {
					makeFeedbackLink( betaMessageElement );
				}
				row.appendChild( betaMessageElement );
			}
		}
	}
}

/**
 * Fills the client side preference dropdown with controls.
 *
 * @param {string} selector of element to fill with client preferences
 * @param {Record<string,ClientPreference>} config
 * @param {UserPreferencesApi} [userPreferences]
 * @return {Promise<Node>}
 */
function render( selector, config, userPreferences ) {
	const node = document.querySelector( selector );
	if ( !node ) {
		return Promise.reject();
	}
	return new Promise( ( resolve ) => {
		getVisibleClientPreferences( config ).forEach( ( pref ) => {
			userPreferences = userPreferences || new mw.Api();
			makeClientPreference( node, pref, config, userPreferences );
		} );
		mw.requestIdleCallback( () => {
			resolve( node );
		} );
	} );
}

/**
 * @param {string} clickSelector what to click
 * @param {string} renderSelector where to render
 * @param {Record<string,ClientPreference>} config
 * @param {UserPreferencesApi} [userPreferences]
 */
function bind( clickSelector, renderSelector, config, userPreferences ) {
	let enhanced = false;
	const chk = /** @type {HTMLInputElement} */ (
		document.querySelector( clickSelector )
	);
	if ( !chk ) {
		return;
	}
	if ( !userPreferences ) {
		userPreferences = new mw.Api();
	}
	if ( chk.checked ) {
		render( renderSelector, config, userPreferences );
		enhanced = true;
	} else {
		chk.addEventListener( 'input', () => {
			if ( enhanced ) {
				return;
			}
			render( renderSelector, config, userPreferences );
			enhanced = true;
		} );
	}
}
module.exports = {
	bind,
	toggleDocClassAndSave,
	render
};
