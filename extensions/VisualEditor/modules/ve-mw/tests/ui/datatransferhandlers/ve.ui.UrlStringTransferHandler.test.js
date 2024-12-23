/*!
 * VisualEditor UserInterface UrlStringTransferHandler tests.
 *
 * @copyright See AUTHORS.txt
 */

QUnit.module( 've.ui.UrlStringTransferHandler (MW)' );

/* Tests */

QUnit.test.each( 'paste', [
	{
		msg: 'External link converts to internal link',
		pasteString: location.origin + mw.Title.newFromText( 'Main Page' ).getUrl(),
		pasteType: 'text/plain',
		expectedData: () => {
			// Explicitly create an internal link so we can assert this behaviour is working
			const a = ve.dm.MWInternalLinkAnnotation.static.newFromTitle( mw.Title.newFromText( 'Main Page' ) ).element;
			return [
				[ 'M', [ a ] ],
				[ 'a', [ a ] ],
				[ 'i', [ a ] ],
				[ 'n', [ a ] ],
				[ ' ', [ a ] ],
				[ 'P', [ a ] ],
				[ 'a', [ a ] ],
				[ 'g', [ a ] ],
				[ 'e', [ a ] ]
			];
		}
	}
], ( assert, caseItem ) => {
	ve.test.utils.runUrlStringHandlerTest( assert, caseItem.pasteString, caseItem.pasteHtml, caseItem.pasteType, caseItem.expectedData, location.origin, caseItem.msg );
} );
