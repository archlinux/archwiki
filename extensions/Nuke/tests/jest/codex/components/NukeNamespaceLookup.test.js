const { mount, flushPromises } = require( '@vue/test-utils' );
const NukeNamespaceLookup = require( '../../../../modules/ext.nuke.codex/components/NukeNamespaceLookup.vue' );
const namespaces = require( '../../../assets/namespaces.json' );
const { nextTick } = require( 'vue' );

const canonicalNamespaceNames = Object.values( namespaces.query.namespaces )
	// Only select namespaces that have an ID > 0.
	// This will exclude the main namespace, and any virtual namespaces we have.
	.filter( ( v ) => v.id > 0 )
	// Get the canonical name of each namespace.
	.map( ( v ) => v.canonical );

function getWrapper( props = {} ) {
	const div = document.createElement( 'div' );
	document.body.appendChild( div );
	return mount( NukeNamespaceLookup, {
		propsData: props,
		attachTo: div
	} );
}

function getLookupMenuItems( wrapper ) {
	return wrapper.findAll( '.cdx-menu-item__text__label' )
		.map( ( i ) => i.text() );
}

describe( 'NukeNamespaceLookup', () => {

	let wrapper;

	beforeEach( () => {
		document.body.innerHTML = '';
	} );

	afterEach( () => {
		wrapper.unmount();
	} );

	it( 'mounts', () => {
		wrapper = getWrapper();
		const input = wrapper.find( 'input#nuke-namespace-lookup' );
		expect( input.exists() ).toBe( true );
	} );

	it( 'loads namespaces', async () => {
		wrapper = getWrapper();
		// Ensure that the namespaces have been loaded
		await flushPromises();
		await nextTick();

		// Find all menu items
		const allMenuItems = getLookupMenuItems( wrapper );

		// The (Main) namespaces uses the 'blanknamespace' message.
		expect( allMenuItems ).toContain( 'blanknamespace' );
		// Verify all our namespaces have names
		for ( const namespace of canonicalNamespaceNames ) {
			expect( allMenuItems ).toContain( namespace );
		}
	} );

	it( 'shows all menu items on no input', async () => {
		wrapper = getWrapper();
		const input = wrapper.find( 'input#nuke-namespace-lookup' );
		// Ensure that the input actually exists
		expect( input.exists() ).toBe( true );

		await flushPromises();
		await input.trigger( 'input' );

		// All menu items should show up if the input has no value
		const allMenuItems = getLookupMenuItems( wrapper );
		// The (Main) namespaces uses the 'blanknamespace' message.
		expect( allMenuItems ).toContain( 'blanknamespace' );
		// Verify all our namespaces have names
		for ( const namespace of canonicalNamespaceNames ) {
			expect( allMenuItems ).toContain( namespace );
		}
	} );

	it( 'filters menu items on input', async () => {
		wrapper = getWrapper();
		const input = wrapper.find( 'input#nuke-namespace-lookup' );
		// Ensure that the input actually exists
		expect( input.exists() ).toBe( true );

		await flushPromises();
		await input.setValue( 'talk' );
		await input.trigger( 'input' );

		// We're filtering only by talk namespaces.
		const allTalkNamespaces = canonicalNamespaceNames
			.filter( ( v ) => /[Tt]alk/.test( v ) );

		// All menu items should show up if the input has no value
		const allMenuItems = getLookupMenuItems( wrapper );

		expect( allMenuItems.length ).toBe( allTalkNamespaces.length );
	} );

	it( "doesn't update during a race condition", async () => {
		wrapper = getWrapper();
		const input = wrapper.find( 'input#nuke-namespace-lookup' );
		// Ensure that the input actually exists
		expect( input.exists() ).toBe( true );

		await flushPromises();
		// DO NOT AWAIT THIS!
		// noinspection ES6MissingAwait
		input.setValue( 'project' );
		await input.setValue( 'talk' );
		await input.trigger( 'input' );

		// We're filtering only by talk namespaces.
		const allTalkNamespaces = canonicalNamespaceNames
			.filter( ( v ) => /[Tt]alk/.test( v ) );

		// All menu items should show up if the input has no value
		const allMenuItems = getLookupMenuItems( wrapper );

		expect( allMenuItems.length ).toBe( allTalkNamespaces.length );
	} );

	it( 'validates user input', async () => {
		wrapper = getWrapper();
		const input = wrapper.find( 'input#nuke-namespace-lookup' );
		// Ensure that the input actually exists
		expect( input.exists() ).toBe( true );

		await flushPromises();
		await input.setValue( 'unknownnamespace' );
		await input.trigger( 'blur' );

		const warning = wrapper.find( '.cdx-message.cdx-message--warning' );
		expect( warning.exists() ).toBe( true );
		expect( warning.text() ).toBe( 'nuke-namespace-invalid' );
	} );

	it( 'clears status on selection', async () => {
		wrapper = getWrapper();
		const input = wrapper.find( 'input#nuke-namespace-lookup' );
		// Ensure that the input actually exists
		expect( input.exists() ).toBe( true );

		await flushPromises();
		await input.setValue( 'unknownnamespace' );
		await input.trigger( 'blur' );

		// Warning should be showing
		let warning = wrapper.find( '.cdx-message.cdx-message--warning' );
		expect( warning.exists() ).toBe( true );
		expect( warning.text() ).toBe( 'nuke-namespace-invalid' );

		await input.setValue( 'talk' );

		// Clear the warning by making a selection.
		// In this case, we'll be clicking on the "Talk" namespace menu item.
		const talkSelection = wrapper.find( '.cdx-menu-item' );
		expect( talkSelection.exists() ).toBe( true );
		await talkSelection.trigger( 'click' );

		warning = wrapper.find( '.cdx-message.cdx-message--warning' );
		expect( warning.exists() ).toBe( false );
	} );

} );
