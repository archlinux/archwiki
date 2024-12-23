/*!
 * VisualEditor MediaWiki Initialization DesktopArticleTarget tests.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

QUnit.module( 've.init.mw.DesktopArticleTarget', ve.test.utils.newMwEnvironment( {
	config: {
		wgVisualEditor: ve.extendObject( {}, mw.config.get( 'wgVisualEditor' ), {
			pageLanguageCode: 'he',
			pageLanguageDir: 'rtl'
		} ),
		wgVisualEditorConfig: ve.extendObject( {}, mw.config.get( 'wgVisualEditorConfig' ), {
			// Disable welcome dialog
			showBetaWelcome: false
		} ),
		wgAction: 'view',
		wgNamespaceNumber: 0,
		wgCanonicalNamespace: ''
	},
	beforeEach: function () {
		this.originalLocation = location.href;
	},
	afterEach: function () {
		// Clean up after history.pushState in ve.init.mw.DesktopArticleTarget#restorePage,
		// which replaces JavaScriptTest with Badtitle and leaves it behind
		if ( location.href !== this.originalLocation ) {
			history.replaceState( null, '', this.originalLocation );
		}
	}
} ) );

QUnit.test( 'init', ( assert ) => {
	const response = {
			visualeditor: {
				result: 'success',
				notices: [
					'<b>HTML string notice</b> message',
					{
						type: 'object notice',
						message: '<b>object notice</b> message'
					}
				],
				copyrightWarning: '<div id="editpage-copywarn">Blah blah</div>',
				checkboxesDef: {
					wpMinoredit: {
						id: 'wpMinoredit',
						'label-message': 'minoredit',
						tooltip: 'minoredit',
						'label-id': 'mw-editpage-minoredit',
						'legacy-name': 'minor',
						default: false
					},
					wpWatchthis: {
						id: 'wpWatchthis',
						'label-message': 'watchthis',
						tooltip: 'watch',
						'label-id': 'mw-editpage-watch',
						'legacy-name': 'watch',
						default: true
					}
				},
				checkboxesMessages: {
					'accesskey-minoredit': 'i',
					'tooltip-minoredit': 'Mark this as a minor edit',
					minoredit: 'This is a minor edit',
					'accesskey-watch': 'w',
					'tooltip-watch': 'Add this page to your watchlist',
					watchthis: 'Watch this page'
				},
				protectedClasses: '',
				basetimestamp: '20161119005107',
				starttimestamp: '20180831122319',
				oldid: 1804,
				blockinfo: null,
				wouldautocreate: false,
				canEdit: true,
				content: '<!DOCTYPE html>\n' + ve.dm.example.singleLine`
					<html prefix="dc: http://purl.org/dc/terms/ mw: http://mediawiki.org/rdf/" about="http://localhost/MediaWiki/core/index.php/Special:Redirect/revision/1804">
						<head prefix="mwr: http://localhost/MediaWiki/core/index.php/Special:Redirect/"><meta property="mw:TimeUuid" content="a4fc0409-ad18-11e8-9b45-dd8cefbedb6d"/>
							<meta charset="utf-8"/>
							<meta property="mw:pageNamespace" content="0"/>
							<meta property="mw:pageId" content="643"/>
							<link rel="dc:replaces" resource="mwr:revision/0"/>
							<meta property="dc:modified" content="2016-11-19T00:51:07.000Z"/>
							<meta property="mw:revisionSHA1" content="da39a3ee5e6b4b0d3255bfef95601890afd80709"/>
							<meta property="mw:html:version" content="1.7.0"/>
							<link rel="dc:isVersionOf" href="http://localhost/MediaWiki/core/index.php/Empty"/>
							<title>Empty</title>
							<base href="http://localhost/MediaWiki/core/index.php/"/>
							<link rel="stylesheet" href="//localhost/MediaWiki/core/load.php?modules=mediawiki.legacy.commonPrint%2Cshared%7Cmediawiki.skinning.content.parsoid%7Cmediawiki.skinning.interface%7Cskins.vector.styles%7Csite.styles%7Cext.cite.style%7Cext.cite.styles%7Cmediawiki.page.gallery.styles&amp;only=styles&amp;skin=vector"/>
						</head>
						<body id="mwAA" lang="he" class="mw-content-rtl sitedir-rtl rtl mw-body-content parsoid-body mediawiki mw-parser-output" dir="rtl">
							<section data-mw-section-id="0" id="mwAQ"></section>
						</body>
					</html>
				`,
				preloaded: false,
				etag: '"1804/a4fc0409-ad18-11e8-9b45-dd8cefbedb6d"'
			}
		},
		target = new ve.init.mw.DesktopArticleTarget(),
		dataPromise = ve.createDeferred().resolve( response ).promise(),
		done = assert.async();

	// eslint-disable-next-line no-jquery/no-global-selector
	$( '#qunit-fixture' ).append( target.$element );

	target.on( 'surfaceReady', () => {
		assert.strictEqual( target.getSurface().getModel().getDocument().getLang(), 'he', 'Page language is passed through from config' );
		assert.strictEqual( target.getSurface().getModel().getDocument().getDir(), 'rtl', 'Page direction is passed through from config' );
		target.activatingDeferred.then( async () => {
			assert.equalDomElement(
				target.toolbar.tools.notices.noticeItems[ 0 ].$element[ 0 ],
				$( '<div class="ve-ui-mwNoticesPopupTool-item"><b>HTML string notice</b> message</div>' )[ 0 ],
				'HTML string notice message is passed through from API'
			);
			assert.strictEqual( target.toolbar.tools.notices.noticeItems[ 0 ].type, undefined, 'Plain text notice type is undefined' );
			assert.equalDomElement(
				target.toolbar.tools.notices.noticeItems[ 1 ].$element[ 0 ],
				$( '<div class="ve-ui-mwNoticesPopupTool-item"><b>object notice</b> message</div>' )[ 0 ],
				'Object notice message is passed through from API'
			);
			assert.strictEqual( target.toolbar.tools.notices.noticeItems[ 1 ].type, 'object notice', 'Object notice type is passed through from API' );

			// Open the save dialog and examine it (this bypasses a bunch of stuff, and may fail in funny
			// ways, but #showSaveDialog has many dependencies that I don't want to simulate here).
			const dialogs = target.getSurface().getDialogs();
			const instance = dialogs.openWindow( 'mwSave', target.getSaveDialogOpeningData() );
			await instance.opened;
			const dialog = dialogs.getCurrentWindow();
			assert.equalDomElement(
				dialog.$element.find( '#editpage-copywarn' )[ 0 ],
				$( '<div id="editpage-copywarn">Blah blah</div>' )[ 0 ],
				'Copyright warning message is passed through from API'
			);
			dialogs.closeWindow( 'mwSave' );
			await instance.closed;

			// Store doc state and examine it
			target.storeDocState();
			const storedData = JSON.parse( sessionStorage.getItem( 've-docstate' ) );
			const ignoredKeys = {
				// Not stored because it's always 'success'
				result: true,
				// Not stored because it's stored elsewhere
				content: true,
				// Not stored because if you're blocked, the editor opens in read-only mode (or doesn't open
				// at all, on mobile), so we'll never have to restore from auto-save
				blockinfo: true
			};
			for ( const key in response.visualeditor ) {
				if ( !ignoredKeys[ key ] ) {
					assert.deepEqual(
						storedData.response[ key ],
						response.visualeditor[ key ],
						key + ' can be restored from auto-save data'
					);
				}
			}

			await target.destroy();
			done();
		} );
	} );
	target.activate( dataPromise );
} );
