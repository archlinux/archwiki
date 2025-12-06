'use strict';

const { mount } = require( '@vue/test-utils' );

jest.mock( 'mediawiki.String', () => ( {
	byteLength: ( str ) => str.length,
	trimByteLength: ( safeVal, newVal, byteLimit ) => ( { newVal: newVal.slice( 0, byteLimit ) } )
} ), { virtual: true } );

const CharacterLimitedTextInput = require( '../../../../modules/ext.checkUser.suggestedInvestigations/components/CharacterLimitedTextInput.vue' );

describe( 'CharacterLimitedTextInput', () => {
	beforeEach( () => {
		const mwConvertNumber = jest.fn();
		mwConvertNumber.mockImplementation( ( number ) => String( number ) );
		mw.language.convertNumber = mwConvertNumber;
	} );

	function render( props = {} ) {
		const wrapper = mount( CharacterLimitedTextInput, {
			props: Object.assign( {
				byteLimit: 600,
				textContent: '',
				'onUpdate:text-content': ( textContent ) => wrapper.setProps( { textContent } )
			}, props )
		} );

		return wrapper;
	}

	it( 'should update content and not show character count when far from the limit', async () => {
		const wrapper = render();

		const initialCharacterCount = wrapper.find( '.ext-checkuser-suggestedinvestigations-dialog__reason-character-count' );

		await wrapper.find( 'input' ).setValue( 'test' );

		const newCharacterCount = wrapper.find( '.ext-checkuser-suggestedinvestigations-dialog__reason-character-count' );

		const emitted = wrapper.emitted();

		expect( emitted[ 'update:text-content' ] ).toStrictEqual( [ [ 'test' ] ] );
		expect( initialCharacterCount.exists() ).toBe( false );
		expect( newCharacterCount.exists() ).toBe( false );
	} );

	it( 'shows character count when near the limit', async () => {
		const wrapper = render( { byteLimit: 100 } );

		const initialCharacterCount = wrapper.find( '.ext-checkuser-suggestedinvestigations-dialog__reason-character-count' );

		await wrapper.find( 'input' ).setValue( 'test' );

		const newCharacterCount = wrapper.find( '.ext-checkuser-suggestedinvestigations-dialog__reason-character-count' );

		expect( initialCharacterCount.exists() ).toBe( false );
		expect( newCharacterCount.text() ).toBe( '96' );
	} );

	it( 'limits character count after exceeding the limit', async () => {
		const wrapper = render( { byteLimit: 6 } );

		await wrapper.find( 'input' ).setValue( 'abcdefghi' );

		const newCharacterCount = wrapper.find( '.ext-checkuser-suggestedinvestigations-dialog__reason-character-count' );
		const newValue = wrapper.find( 'input' ).element.value;

		expect( newCharacterCount.text() ).toBe( '0' );
		expect( newValue ).toBe( 'abcdef' );
	} );

	it( 'does not show character count without input even if initial limit is small', async () => {
		const wrapper = render( { byteLimit: 50 } );

		const initialCharacterCount = wrapper.find( '.ext-checkuser-suggestedinvestigations-dialog__reason-character-count' );

		expect( initialCharacterCount.exists() ).toBe( false );
	} );

	it( 'should forward other props to input wrapper component', () => {
		const wrapper = render( { class: 'foo' } );

		expect( wrapper.find( '.cdx-text-input' ).classes() ).toContain( 'foo' );
	} );
} );
