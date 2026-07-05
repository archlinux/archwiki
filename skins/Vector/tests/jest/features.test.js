const features = require( '../../resources/skins.vector.js/features.js' );

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

describe( 'features.toggleDocClasses', () => {
	beforeEach( () => {
		document.documentElement.className = '';
	} );

	test( 'sets feature to enabled when no override present and feature is disabled', () => {
		const featureName = 'mock-feature';
		document.documentElement.classList.add( `vector-feature-${ featureName }-clientpref-0` );
		const result = features.toggleDocClasses( featureName );
		expect( result ).toBe( true );
		expect( document.documentElement.classList.contains( `vector-feature-${ featureName }-clientpref-1` ) ).toBe( true );
		expect( document.documentElement.classList.contains( `vector-feature-${ featureName }-clientpref-0` ) ).toBe( false );
	} );

	test( 'sets feature to disabled when no override present and feature is enabled', () => {
		const featureName = 'mock-feature';
		document.documentElement.classList.add( `vector-feature-${ featureName }-clientpref-1` );
		const result = features.toggleDocClasses( featureName );
		expect( result ).toBe( false );
		expect( document.documentElement.classList.contains( `vector-feature-${ featureName }-clientpref-0` ) ).toBe( true );
		expect( document.documentElement.classList.contains( `vector-feature-${ featureName }-clientpref-1` ) ).toBe( false );
	} );

	test( 'forces feature to enabled when override is true and feature already enabled', () => {
		const featureName = 'mock-feature';
		document.documentElement.classList.add( `vector-feature-${ featureName }-clientpref-1` );
		const result = features.toggleDocClasses( featureName, true );
		expect( result ).toBe( true );
		expect( document.documentElement.classList.contains( `vector-feature-${ featureName }-clientpref-1` ) ).toBe( true );
		expect( document.documentElement.classList.contains( `vector-feature-${ featureName }-clientpref-0` ) ).toBe( false );
	} );

	test( 'forces feature to enabled when override is true and feature is disabled', () => {
		const featureName = 'mock-feature';
		document.documentElement.classList.add( `vector-feature-${ featureName }-clientpref-0` );
		const result = features.toggleDocClasses( featureName, true );
		expect( result ).toBe( true );
		expect( document.documentElement.classList.contains( `vector-feature-${ featureName }-clientpref-1` ) ).toBe( true );
		expect( document.documentElement.classList.contains( `vector-feature-${ featureName }-clientpref-0` ) ).toBe( false );
	} );

	test( 'forces feature to disabled when override is false and feature already disabled', () => {
		const featureName = 'mock-feature';
		document.documentElement.classList.add( `vector-feature-${ featureName }-clientpref-0` );
		const result = features.toggleDocClasses( featureName, false );
		expect( result ).toBe( false );
		expect( document.documentElement.classList.contains( `vector-feature-${ featureName }-clientpref-0` ) ).toBe( true );
		expect( document.documentElement.classList.contains( `vector-feature-${ featureName }-clientpref-1` ) ).toBe( false );
	} );

	test( 'forces feature to disabled when override is false and feature is enabled', () => {
		const featureName = 'mock-feature';
		document.documentElement.classList.add( `vector-feature-${ featureName }-clientpref-1` );
		const result = features.toggleDocClasses( featureName, false );
		expect( result ).toBe( false );
		expect( document.documentElement.classList.contains( `vector-feature-${ featureName }-clientpref-0` ) ).toBe( true );
		expect( document.documentElement.classList.contains( `vector-feature-${ featureName }-clientpref-1` ) ).toBe( false );
	} );

} );
