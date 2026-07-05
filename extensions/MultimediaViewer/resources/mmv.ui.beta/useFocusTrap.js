/**
 * Focus trap composable for modal dialogs.
 *
 * Implements the sentinel-based focus trapping pattern used by Codex's
 * CdxDialog. Two invisible sentinel elements bookend the dialog content;
 * when one receives focus (via Tab / Shift+Tab past the edge), focus is
 * redirected to the opposite end.
 *
 * @module useFocusTrap
 */

const { watch, nextTick } = require( 'vue' );

const FOCUSABLE_SELECTOR =
	'input, select, textarea, button, object, a, area, ' +
	'[contenteditable], [tabindex]:not([tabindex^="-"])';

/**
 * Attempt to focus the first (or last) focusable element inside a container,
 * excluding elements with the given sentinel class. Iterates through all
 * candidates, verifying focus actually landed — elements may refuse focus if
 * hidden or disabled. Matches Codex's `focusFirstFocusableElement`.
 *
 * @param {HTMLElement} container
 * @param {string} sentinelClass CSS class used to identify sentinel elements
 * @param {boolean} [reverse=false] If true, try candidates in reverse order
 * @return {boolean} True if a candidate accepted focus
 */
function focusFirstFocusable( container, sentinelClass, reverse ) {
	let candidates = Array.from(
		container.querySelectorAll( FOCUSABLE_SELECTOR )
	).filter( ( el ) => !el.classList.contains( sentinelClass ) );

	if ( reverse ) {
		candidates = candidates.reverse();
	}

	for ( const candidate of candidates ) {
		candidate.focus();
		if ( document.activeElement === candidate ) {
			return true;
		}
	}
	return false;
}

/**
 * @typedef {Object} FocusTrapOptions
 * @property {string} [sentinelClass='mmv-focus-sentinel'] CSS class applied to the sentinel
 *   elements, used to exclude them when searching for focusable children.
 * @property {import('vue').Ref<HTMLElement|null>} [holderRef] Template ref for a
 *   `tabindex="-1"` element inside the container. When provided, initial focus
 *   is placed on this holder instead of a real focusable. This avoids a race
 *   condition that happens when interactive elements inside the container are
 *   rendered asynchronously and are not guaranteed to be available as soon as the
 *   dialog opens.
 */

/**
 * @typedef {Object} FocusTrapReturn
 * @property {Function} onFocusTrapStart Focus handler for the start sentinel;
 *   redirects focus to the last focusable element in the container.
 * @property {Function} onFocusTrapEnd Focus handler for the end sentinel;
 *   redirects focus to the first focusable element in the container.
 */

/**
 * Focus trap composable for a dialog element.
 *
 * @param {import('vue').Ref<HTMLElement|null>} containerRef Template ref for the dialog root
 * @param {import('vue').Ref<boolean>} isActive Ref that is true when the trap should be active
 * @param {FocusTrapOptions} [options] Configuration options
 * @return {FocusTrapReturn}
 */
function useFocusTrap( containerRef, isActive, options ) {
	options = options || {};
	const sentinelClass = options.sentinelClass || 'mmv-focus-sentinel';
	const holderRef = options.holderRef;

	function onFocusTrapStart() {
		if ( containerRef.value ) {
			focusFirstFocusable( containerRef.value, sentinelClass, true );
		}
	}

	function onFocusTrapEnd() {
		if ( containerRef.value ) {
			focusFirstFocusable( containerRef.value, sentinelClass );
		}
	}

	watch( isActive, ( active ) => {
		if ( !active ) {
			return;
		}
		nextTick( () => {
			if ( !containerRef.value ) {
				return;
			}
			if ( holderRef && holderRef.value ) {
				holderRef.value.focus();
				return;
			}
			focusFirstFocusable( containerRef.value, sentinelClass );
		} );
	} );

	return {
		onFocusTrapStart,
		onFocusTrapEnd
	};
}

module.exports = { useFocusTrap };
