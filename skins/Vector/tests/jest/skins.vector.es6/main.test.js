window.matchMedia = window.matchMedia || function () {
	return {
		matches: false,
		onchange: () => {}
	};
};

const { test } = require( '../../../resources/skins.vector.js/setupIntersectionObservers.js' );
describe( 'main.js', () => {
	it( 'getHeadingIntersectionHandler', () => {
		const content = document.createElement( 'div' );
		content.setAttribute( 'class', 'mw-body-content' );
		content.setAttribute( 'id', 'mw-content-text' );
		const parserOutput = document.createElement( 'div' );
		parserOutput.setAttribute( 'class', 'mw-parser-output' );
		content.appendChild( parserOutput );

		const heading = document.createElement( 'div' );
		heading.classList.add( 'mw-heading' );
		const headline = document.createElement( 'h2' );
		headline.setAttribute( 'id', 'headline' );
		heading.appendChild( headline );
		parserOutput.appendChild( heading );

		[
			[ content, 'toc-mw-content-text' ],
			[ heading, 'toc-headline' ]
		].forEach( ( testCase ) => {
			const node = /** @type {HTMLElement} */ ( testCase[ 0 ] );
			const fn = jest.fn();
			const handler = test.getHeadingIntersectionHandler( fn );
			handler( node );
			expect( fn ).toHaveBeenCalledWith( testCase[ 1 ] );
		} );
	} );
} );

const sectionObserverFn = () => ( {
	pause: () => {},
	resume: () => {},
	mount: () => {},
	unmount: () => {},
	setElements: () => {},
	calcIntersection: () => {}
} );

describe( 'Table of contents re-rendering', () => {
	const mockMwHook = () => {
		/** @type {Object.<string, Function>} */
		const callback = {};
		jest.spyOn( mw, 'hook' ).mockImplementation( ( name ) => ( {
			add: function ( fn ) {
				callback[ name ] = fn;

				return this;
			},
			fire: ( data ) => {
				if ( callback[ name ] ) {
					callback[ name ]( data );
				}
			}
		} ) );
	};

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	it( 'listens to wikipage.tableOfContents hook when mounted', () => {
		mockMwHook();
		const spy = jest.spyOn( mw, 'hook' );
		const tocElement = document.createElement( 'div' );
		const bodyContent = document.createElement( 'article' );
		const toc = test.setupTableOfContents( tocElement, bodyContent, sectionObserverFn );
		expect( toc ).not.toBe( null );
		expect( spy ).toHaveBeenCalledWith( 'wikipage.tableOfContents' );
		expect( spy ).not.toHaveBeenCalledWith( 'wikipage.tableOfContents.vector' );
	} );

	it( 'Firing wikipage.tableOfContents triggers reloadTableOfContents', async () => {
		mockMwHook();
		const tocElement = document.createElement( 'div' );
		const bodyContent = document.createElement( 'article' );
		const toc = test.setupTableOfContents( tocElement, bodyContent, sectionObserverFn );
		if ( !toc ) {
			// something went wrong
			expect( true ).toBe( false );
			return;
		}
		const spy = jest.spyOn( toc, 'reloadTableOfContents' ).mockImplementation( () => Promise.resolve() );

		mw.hook( 'wikipage.tableOfContents' ).fire( [
			// Add new section to see how the re-render performs.
			{
				toclevel: 1,
				number: '4',
				line: 'bat',
				anchor: 'bat',
				'is-top-level-section': true,
				'is-parent-section': false,
				'array-sections': null
			}
		] );

		expect( spy ).toHaveBeenCalled();
	} );
} );
