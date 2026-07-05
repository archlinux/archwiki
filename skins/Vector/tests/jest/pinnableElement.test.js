jest.mock( '../../resources/skins.vector.js/features.js' );
jest.mock( '../../resources/skins.vector.js/popupNotification.js' );

const features = require( '../../resources/skins.vector.js/features.js' );
const popupNotification = require( '../../resources/skins.vector.js/popupNotification.js' );
const mustache = require( 'mustache' );
const fs = require( 'fs' );
const pinnableHeaderTemplate = fs.readFileSync( 'includes/templates/PinnableHeader.mustache', 'utf8' );
const pinnableElement = require( '../../resources/skins.vector.js/pinnableElement.js' );

/**
 * Mock for matchMedia, which is not included in JSDOM.
 * https://jestjs.io/docs/26.x/manual-mocks#mocking-methods-which-are-not-implemented-in-jsdom
 */
Object.defineProperty( window, 'matchMedia', {
	writable: true,
	value: jest.fn().mockImplementation( ( query ) => ( {
		matches: false,
		media: query,
		onchange: null,
		addListener: jest.fn(), // deprecated
		removeListener: jest.fn(), // deprecated
		addEventListener: jest.fn(),
		removeEventListener: jest.fn(),
		dispatchEvent: jest.fn()
	} ) )
} );

// Mock functionality of features.js
let pinnedStatus = false;
features.toggleDocClasses = jest.fn( ( name, pinState ) => {
	pinnedStatus = pinState;
} );
features.save = jest.fn();
features.isEnabled = jest.fn( () => pinnedStatus );

popupNotification.add = jest.fn( () => Promise.resolve() );
popupNotification.remove = jest.fn();

const unpinnedData = {
	'is-pinned': false,
	'data-feature-name': 'pinned',
	'data-pinnable-element-id': 'pinnable-element',
	label: 'simple pinnable element',
	'label-tag-name': 'div',
	'pin-label': 'pin',
	'unpin-label': 'unpin',
	'data-pinned-container-id': 'pinned-container',
	'data-unpinned-container-id': 'unpinned-container'
};

const pinnedData = {
	'is-pinned': true,
	'data-feature-name': 'appearance-pinned',
	'data-pinnable-element-id': 'vector-appearance',
	label: 'simple pinnable element',
	'label-tag-name': 'div',
	'pin-label': 'pin',
	'unpin-label': 'unpin',
	'data-pinned-container-id': 'pinned-container',
	'data-unpinned-container-id': 'unpinned-container'
};

const initializeHTML = ( headerData ) => {
	pinnedStatus = headerData[ 'is-pinned' ];
	const pinnableHeaderHTML = mustache.render( pinnableHeaderTemplate, headerData );
	const pinnableElementHTML = `<div id="pinnable-element">${ pinnableHeaderHTML }</div>`;
	document.body.innerHTML = `<div id="pinned-container">
			${ headerData[ 'is-pinned' ] ? pinnableElementHTML : '' }
		</div>
		<div class="vector-dropdown">
			<input type="checkbox" id="checkbox" class="vector-dropdown-checkbox">
			<label for="checkbox" class="vector-menu-heading ">
				<span class="vector-menu-heading-label">Dropdown</span>
			</label>
			<div class="vector-menu-content">
				<div id="unpinned-container">
				${ !headerData[ 'is-pinned' ] ? pinnableElementHTML : '' }
				</div>
			</div>
		</div>
`;
};

describe( 'Pinnable element', () => {
	test( 'renders', () => {
		initializeHTML( unpinnedData );
		expect( document.body.innerHTML ).toMatchSnapshot();
	} );

	test( 'updates pinnable header classes when toggle is pressed', () => {
		initializeHTML( unpinnedData );
		pinnableElement.init();
		const pinButton = /** @type {HTMLElement} */ ( document.querySelector( '.vector-pinnable-header-pin-button' ) );
		const unpinButton = /** @type {HTMLElement} */ ( document.querySelector( '.vector-pinnable-header-unpin-button' ) );
		const header = /** @type {HTMLElement} */ ( document.querySelector( `.${ unpinnedData[ 'data-pinnable-element-id' ] }-pinnable-header` ) );

		expect( header.classList.contains( pinnableElement.PINNED_HEADER_CLASS ) ).toBe( false );
		expect( header.classList.contains( pinnableElement.UNPINNED_HEADER_CLASS ) ).toBe( true );
		pinButton.click();
		expect( header.classList.contains( pinnableElement.PINNED_HEADER_CLASS ) ).toBe( true );
		expect( header.classList.contains( pinnableElement.UNPINNED_HEADER_CLASS ) ).toBe( false );
		unpinButton.click();
		expect( header.classList.contains( pinnableElement.PINNED_HEADER_CLASS ) ).toBe( false );
		expect( header.classList.contains( pinnableElement.UNPINNED_HEADER_CLASS ) ).toBe( true );
	} );

	test( 'moves pinnable element when data attributes are defined', () => {
		initializeHTML( unpinnedData );
		pinnableElement.init();
		const pinButton = /** @type {HTMLElement} */ ( document.querySelector( '.vector-pinnable-header-pin-button' ) );
		const unpinButton = /** @type {HTMLElement} */ ( document.querySelector( '.vector-pinnable-header-unpin-button' ) );
		const pinnableElem = /** @type {HTMLElement} */ ( document.getElementById( unpinnedData[ 'data-pinnable-element-id' ] ) );

		expect( pinnableElem.parentElement && pinnableElem.parentElement.id ).toBe( 'unpinned-container' );
		pinButton.click();
		expect( pinnableElem.parentElement && pinnableElem.parentElement.id ).toBe( 'pinned-container' );
		unpinButton.click();
		expect( pinnableElem.parentElement && pinnableElem.parentElement.id ).toBe( 'unpinned-container' );
	} );

	test( 'calls features.toggleDocClasses(() when toggle is pressed', () => {
		initializeHTML( unpinnedData );
		pinnableElement.init();
		const pinButton = /** @type {HTMLElement} */ ( document.querySelector( '.vector-pinnable-header-pin-button' ) );
		const unpinButton = /** @type {HTMLElement} */ ( document.querySelector( '.vector-pinnable-header-unpin-button' ) );

		pinButton.click();
		expect( features.toggleDocClasses ).toHaveBeenCalledTimes( 1 );
		expect( features.toggleDocClasses ).toHaveBeenCalledWith( unpinnedData[ 'data-feature-name' ], true );

		features.toggleDocClasses.mockClear();
		unpinButton.click();
		expect( features.toggleDocClasses ).toHaveBeenCalledTimes( 1 );
		expect( features.toggleDocClasses ).toHaveBeenCalledWith( unpinnedData[ 'data-feature-name' ], false );
	} );

	test( 'isPinned() calls features.isEnabled()', () => {
		initializeHTML( unpinnedData );
		pinnableElement.init();
		const header = /** @type {HTMLElement} */ ( document.querySelector( `.${ unpinnedData[ 'data-pinnable-element-id' ] }-pinnable-header` ) );

		features.isEnabled.mockClear();
		pinnableElement.isPinned( header );
		expect( features.isEnabled ).toHaveBeenCalledTimes( 1 );
		expect( features.isEnabled ).toHaveBeenCalledWith( unpinnedData[ 'data-feature-name' ] );
	} );

	test( 'setFocusAfterToggle() sets focus on appropriate element after pinnableElement is toggled', () => {
		initializeHTML( unpinnedData );
		pinnableElement.init();
		const dropdownCheckbox = /** @type {HTMLElement} */ ( document.getElementById( 'checkbox' ) );
		const pinButton = /** @type {HTMLElement} */ ( document.querySelector( '.vector-pinnable-header-pin-button' ) );
		const unpinButton = /** @type {HTMLElement} */ ( document.querySelector( '.vector-pinnable-header-unpin-button' ) );

		pinButton.click();
		expect( document.activeElement ).toBe( unpinButton );
		unpinButton.click();
		expect( document.activeElement ).toBe( dropdownCheckbox );
	} );
	test( 'updates pinnable header classes when hideVectorColumnsHandler() is called', () => {
		initializeHTML( pinnedData );
		pinnableElement.init();
		const header = /** @type {HTMLElement} */ ( document.querySelector( `.${ pinnedData[ 'data-pinnable-element-id' ] }-pinnable-header` ) );
		expect( header.classList.contains( pinnableElement.PINNED_HEADER_CLASS ) ).toBe( true );
		expect( header.classList.contains( pinnableElement.UNPINNED_HEADER_CLASS ) ).toBe( false );
		pinnableElement.hideVectorColumnsHandler();
		expect( header.classList.contains( pinnableElement.PINNED_HEADER_CLASS ) ).toBe( false );
		expect( header.classList.contains( pinnableElement.UNPINNED_HEADER_CLASS ) ).toBe( true );
	} );
	test( 'restores to saved pinned state when restoreVectorColumnsHandler() is called', () => {
		initializeHTML( pinnedData );
		const header = /** @type {HTMLElement} */ ( document.querySelector( `.${ pinnedData[ 'data-pinnable-element-id' ] }-pinnable-header` ) );
		pinnableElement.init();
		pinnableElement.hideVectorColumnsHandler();
		expect( header.classList.contains( pinnableElement.PINNED_HEADER_CLASS ) ).toBe( false );
		expect( header.classList.contains( pinnableElement.UNPINNED_HEADER_CLASS ) ).toBe( true );
		pinnableElement.restoreVectorColumnsHandler();
		expect( header.classList.contains( pinnableElement.PINNED_HEADER_CLASS ) ).toBe( true );
		expect( header.classList.contains( pinnableElement.UNPINNED_HEADER_CLASS ) ).toBe( false );
	} );
	test( 'does nothing when restoring to unpinned saved state when restoreVectorColumnsHandler() is called', () => {
		initializeHTML( pinnedData );
		const header = /** @type {HTMLElement} */ ( document.querySelector( `.${ pinnedData[ 'data-pinnable-element-id' ] }-pinnable-header` ) );
		pinnableElement.init();
		const unpinButton = /** @type {HTMLElement} */ ( document.querySelector( '.vector-pinnable-header-unpin-button' ) );
		unpinButton.click();
		expect( header.classList.contains( pinnableElement.PINNED_HEADER_CLASS ) ).toBe( false );
		expect( header.classList.contains( pinnableElement.UNPINNED_HEADER_CLASS ) ).toBe( true );
		pinnableElement.hideVectorColumnsHandler();
		expect( header.classList.contains( pinnableElement.PINNED_HEADER_CLASS ) ).toBe( false );
		expect( header.classList.contains( pinnableElement.UNPINNED_HEADER_CLASS ) ).toBe( true );
		pinnableElement.restoreVectorColumnsHandler();
		expect( header.classList.contains( pinnableElement.PINNED_HEADER_CLASS ) ).toBe( false );
		expect( header.classList.contains( pinnableElement.UNPINNED_HEADER_CLASS ) ).toBe( true );
	} );
} );
