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
		expectedData: () => ve.dm.example.annotateText(
			'Main Page',
			// Explicitly create an internal link so we can assert this behaviour is working
			ve.dm.MWInternalLinkAnnotation.static.newFromTitle( mw.Title.newFromText( 'Main Page' ) ).element
		)
	}
], ( assert, caseItem ) => {
	ve.test.utils.runUrlStringHandlerTest(
		assert,
		{
			base: location.origin,
			...caseItem
		}
	);
} );
