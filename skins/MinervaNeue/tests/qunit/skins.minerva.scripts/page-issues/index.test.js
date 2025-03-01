QUnit.module( 'Minerva pageIssues', () => {
	const mobile = require( 'mobile.startup' );
	const pageIssues = require( 'skins.minerva.scripts/page-issues/index.js' );
	const insertBannersOrNotice = pageIssues.test.insertBannersOrNotice;
	const PageHTMLParser = mobile.PageHTMLParser;
	const overlayManager = mobile.getOverlayManager();
	const $mockContainer = $(
		'<div id="bodyContent">' +
			'<table class="ambox ambox-content">' +
				'<tbody class="mbox-text">' +
					'<tr><td><span class="mbox-text-span"> ambox text span </span></td></tr>' +
				'</tbody>' +
			'</table>' +
		'</div>'
	);
	const labelText = 'label text';
	const inline = true;
	const SECTION = '0';
	const processedAmbox = insertBannersOrNotice(
		new PageHTMLParser( $mockContainer ),
		labelText, SECTION, inline, overlayManager
	).ambox;

	QUnit.test( 'insertBannersOrNotice() should add a "learn more" message', ( assert ) => {
		assert.true( /(skin-minerva-issue-learn-more)/.test( processedAmbox.html() ) );
	} );

	QUnit.test( 'insertBannersOrNotice() should add an icon', ( assert ) => {
		assert.true( /(minerva-icon)/.test( processedAmbox.html() ) );
	} );
	QUnit.test( 'clicking on the product of insertBannersOrNotice() should trigger a URL change', ( assert ) => {
		processedAmbox.click();
		assert.strictEqual( window.location.hash, '#/issues/' + SECTION );
	} );
} );
