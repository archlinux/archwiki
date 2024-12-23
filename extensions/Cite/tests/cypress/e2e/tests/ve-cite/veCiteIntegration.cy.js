import * as helper from './../../utils/functions.helper.js';
import * as veHelper from './../../utils/ve.helper.js';

const title = helper.getTestString( 'CiteTest-title' );

const refText1 = 'This is citation #1 for reference #1 and #2';
const refText2 = 'This is citation #2 for reference #3';

const wikiText = `This is reference #1: <ref name="a">${ refText1 }</ref><br> ` +
	'This is reference #2 <ref name="a" /><br>' +
	`This is reference #3 <ref>${ refText2 }</ref><br>` +
	'<references />';

let usesCitoid;

describe( 'Visual Editor Cite Integration', () => {
	before( () => {
		helper.editPage( title, wikiText );
	} );

	beforeEach( () => {
		helper.visitTitle( title );
		helper.waitForMWLoader();

		cy.window().then( async ( win ) => {
			usesCitoid = win.mw.loader.getModuleNames().includes( 'ext.citoid.visualEditor' );
		} );

		veHelper.setVECookiesToDisableDialogs();
		veHelper.openVEForEditingReferences( title, usesCitoid );
	} );

	it( 'should edit and verify reference content in Visual Editor', () => {
		veHelper.getVEFootnoteMarker( 'a', 1, 1 ).click();

		// Popup appears containing ref content
		veHelper.getVEReferenceContextItem()
			.should( 'be.visible' )
			.should( 'contain.text', refText1 );

		// Open reference edit dialog
		veHelper.getVEReferenceContextItemEdit().click();

		// Dialog appears with ref content
		veHelper.getVEReferenceEditDialog()
			.should( 'be.visible' )
			.should( 'contain.text', refText1 );
	} );

	it( 'should display existing references in the Cite re-use dialog', () => {
		if ( usesCitoid ) {
			veHelper.openVECiteoidReuseDialog();

		} else {
			veHelper.openVECiteReuseDialog();
		}

		// Assert reference content for the first reference
		veHelper.getCiteReuseDialogRefResultName( 1 ).should( 'have.text', 'a' );
		veHelper.getCiteReuseDialogRefResultCitation( 1 ).should( 'have.text', '[1]' );
		veHelper.getCiteReuseDialogRefText( 1 ).should( 'have.text', refText1 );

		// Assert reference content for the second reference
		veHelper.getCiteReuseDialogRefResultName( 2 ).should( 'have.text', '' );
		veHelper.getCiteReuseDialogRefResultCitation( 2 ).should( 'have.text', '[2]' );
		veHelper.getCiteReuseDialogRefText( 2 ).should( 'have.text', refText2 );

	} );

} );
