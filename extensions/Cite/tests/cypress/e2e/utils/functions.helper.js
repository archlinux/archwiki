import querystring from 'querystring';

export function clickUntilVisible( clickElement, expectedSelector, timeout = 5000 ) {
	const timeoutTime = Date.now() + timeout;

	function clickUntilVisibleWithinTime() {
		cy.get( expectedSelector ).then( ( $element ) => {
			if ( Date.now() > timeoutTime || $element.is( ':visible' ) ) {
				return;
			}

			clickElement.click();
			clickUntilVisibleWithinTime();
		} );
	}

	clickUntilVisibleWithinTime();
}

export function getTestString( prefix = '' ) {
	return prefix + Math.random().toString();
}

export function visitTitle( title, query = {} ) {
	cy.visit( `/index.php?title=${ encodeURIComponent( title ) }&${ querystring.stringify( query ) }` );
}

export function waitForMWLoader() {
	// Rely on the retry behavior of Cypress assertions to use this as a "wait"
	// until the specified conditions are met.
	cy.window()
		.should( 'have.property', 'mw' )
		.and( 'have.property', 'loader' )
		.and( 'have.property', 'using' );
}

export function waitForModuleReady( moduleName ) {
	cy.window()
		.should( 'have.property', 'mw' )
		.and( 'have.property', 'loader' )
		.and( 'have.property', 'getState' );
	cy.window()
		.should(
			( win ) => expect( win.mw.loader.getState( moduleName ) ).to.eq( 'ready' )
		);
}

export function editPage( title, wikiText ) {
	visitTitle( '' );
	waitForMWLoader();
	cy.window().then( async ( win ) => {
		await win.mw.loader.using( 'mediawiki.api' );
		const response = await new win.mw.Api().postWithEditToken( {
			action: 'edit',
			title: title,
			text: wikiText,
			formatversion: '2'
		} );

		expect( response.edit.result ).to.equal( 'Success' );
	} );
}

export function loginAsAdmin() {
	visitTitle( 'Special:UserLogin' );
	cy.get( '#wpName1' ).type( cy.config( 'mediawikiAdminUsername' ) );
	cy.get( '#wpPassword1' ).type( cy.config( 'mediawikiAdminPassword' ) );
	cy.get( '#wpLoginAttempt' ).click();
}

export function getReference( num ) {
	return cy.get( `#mw-content-text .reference:nth-of-type(${ num })` );

}

export function getCiteSubBacklink( num ) {
	return cy.get( `.mw-cite-backlink sup:nth-of-type(${ num }) a` );
}

export function getCiteMultiBacklink( num ) {
	return cy.get( `.references li:nth-of-type(${ num }) .mw-cite-up-arrow-backlink` );
}

export function getCiteSingleBacklink( num ) {
	return cy.get( `.references li:nth-of-type(${ num }) .mw-cite-backlink a` );
}

export function getFragmentFromLink( linkElement ) {
	return linkElement.invoke( 'attr', 'href' ).then( ( href ) => href.split( '#' )[ 1 ] );
}

export function backlinksIdShouldMatchFootnoteId( supIndex, backlinkIndex, rowNumber ) {
	return getRefsFromArticleSection()
		.eq( supIndex )
		.invoke( 'attr', 'id' )
		.then( ( id ) => {
			getRefFromReferencesSection( rowNumber )
				.find( '.mw-cite-backlink a' )
				.eq( backlinkIndex )
				.invoke( 'attr', 'href' )
				.should( 'eq', `#${ id }` );
		} );
}

// Article Section
export function getRefsFromArticleSection() {
	return cy.get( '#mw-content-text p sup' );
}

export function articleSectionRefMarkersContainCorrectRefName( refMarkerContent ) {
	return getRefsFromArticleSection()
		.find( `a:contains('[${ refMarkerContent }]')` ) // Filter by refMarkerContent
		.each( ( $el ) => {
			cy.wrap( $el )
				.should( 'have.text', `[${ refMarkerContent }]` )
				.and( 'have.attr', 'href', `#cite_note-a-${ refMarkerContent }` );
		} );
}

// References Section
export function getRefsFromReferencesSection() {
	return cy.get( '#mw-content-text .references li' );
}

export function getRefFromReferencesSection( rowNumber ) {
	return getRefsFromReferencesSection().eq( rowNumber - 1 );
}

export function referenceSectionRefIdContainsRefName( rowNumber, refName ) {
	const id = refName !== null ? `cite_note-${ refName }-${ rowNumber }` : `cite_note-${ rowNumber }`;
	return getRefFromReferencesSection( rowNumber ).should( 'have.attr', 'id', id );
}

export function verifyBacklinkHrefContent( refName, rowNumber, index ) {
	const expectedHref = `#cite_ref-${ refName }_${ rowNumber }-${ index }`;
	return getRefFromReferencesSection( rowNumber )
		.find( '.mw-cite-backlink a' )
		.eq( index )
		.should( 'have.attr', 'href', expectedHref );
}

export function abandonReference( id ) {
	cy.get( `:not(.reference-text) > #${ id } a` )
		.trigger( 'mouseout' );
	// Wait for the 300ms default ABANDON_END_DELAY.
	// eslint-disable-next-line cypress/no-unnecessary-waiting
	cy.wait( 500 );
}

export function dwellReference( id ) {
	cy.get( `:not(.reference-text) > #${ id } a` )
		.trigger( 'mouseover' );
}

export function assertPreviewIsScrollable( isScrollable ) {
	cy.get( '.mwe-popups-extract .mwe-popups-scroll' )
		.should( ( $el ) => isScrollable === ( $el.prop( 'scrollHeight' ) > $el.prop( 'offsetHeight' ) ) );
}
