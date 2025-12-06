const { mount, flushPromises } = require( '@vue/test-utils' );
const NukeTargetLookup = require( '../../../../modules/ext.nuke.codex/components/NukeTargetLookup.vue' );

function getWrapper( props = {} ) {
	const div = document.createElement( 'div' );
	document.body.appendChild( div );
	return mount( NukeTargetLookup, {
		propsData: props,
		attachTo: div
	} );
}

describe( 'NukeTargetLookup', () => {

	let wrapper;

	beforeEach( () => {
		document.body.innerHTML = '';
	} );

	afterEach( () => {
		wrapper.unmount();
	} );

	it( 'mounts', () => {
		wrapper = getWrapper();
		const field = wrapper.find( '#nuke-target-lookup' );
		expect( field.exists() ).toBe( true );
	} );

	it( 'fetches results', async () => {
		wrapper = getWrapper();
		const field = wrapper.find( '#nuke-target-lookup' );
		expect( field.exists() ).toBe( true );

		const input = field.find( 'input' );
		await input.setValue( 'Test' );
		await input.trigger( 'input' );
		await flushPromises();

		const menuItems = wrapper.findAll( '.cdx-menu-item:not(.cdx-menu__no-results)' );
		expect( menuItems.length ).toBe( 5 );
	} );

	it( 'ignores race conditions', async () => {
		wrapper = getWrapper();
		const field = wrapper.find( '#nuke-target-lookup' );
		expect( field.exists() ).toBe( true );

		const input = field.find( 'input' );
		// DO NOT AWAIT THIS!
		// noinspection ES6MissingAwait
		input.setValue( 'Example' );
		await input.setValue( 'Test' );
		await input.trigger( 'input' );
		await flushPromises();

		const menuItems = wrapper.findAll( '.cdx-menu-item:not(.cdx-menu__no-results)' );
		expect( menuItems.length ).toBe( 5 );
		expect( menuItems.map( ( v ) => v.text() ).every( ( v ) => v.startsWith( 'Test' ) ) ).toBe( true );
	} );

	it( 'clears when empty', async () => {
		wrapper = getWrapper();
		const field = wrapper.find( '#nuke-target-lookup' );
		expect( field.exists() ).toBe( true );

		const input = field.find( 'input' );
		await input.setValue( '' );
		await input.trigger( 'input' );
		await flushPromises();

		expect( wrapper.find( '.cdx-menu__no-results' ).exists() ).toBe( true );
		expect( wrapper.findAll( '.cdx-menu-item:not(.cdx-menu__no-results)' ).length ).toBe( 0 );
	} );

	it( 'clears when no results', async () => {
		wrapper = getWrapper();
		const field = wrapper.find( '#nuke-target-lookup' );
		expect( field.exists() ).toBe( true );

		const input = field.find( 'input' );
		await input.setValue( "UserThatDoesn'tExist" );
		await input.trigger( 'input' );
		await flushPromises();

		expect( wrapper.find( '.cdx-menu__no-results' ).exists() ).toBe( true );
		expect( wrapper.findAll( '.cdx-menu-item:not(.cdx-menu__no-results)' ).length ).toBe( 0 );
	} );

	it( 'clears when erroring', async () => {
		wrapper = getWrapper();
		const field = wrapper.find( '#nuke-target-lookup' );
		expect( field.exists() ).toBe( true );

		const input = field.find( 'input' );
		await input.setValue( 'Timeout' );
		await input.trigger( 'input' );
		await flushPromises();

		expect( wrapper.find( '.cdx-menu__no-results' ).exists() ).toBe( true );
		expect( wrapper.findAll( '.cdx-menu-item:not(.cdx-menu__no-results)' ).length ).toBe( 0 );
	} );

} );
