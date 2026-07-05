import Page from 'wdio-mediawiki/Page';

class EditPage extends Page {

	get bulletListOption() {
		return $( '.oo-ui-tool-name-bullet' );
	}

	get boldTextStyleOption() {
		return $( '.oo-ui-tool-name-bold' );
	}

	get content() {
		return $( '#content' );
	}

	get edited() {
		return $( '*=Your edit was saved' );
	}

	get formatParagraphElement() {
		return $( '.ve-ui-toolbar-group-format' );
	}

	get helpPopup() {
		return $( '.ve-ui-mwHelpListToolGroup-tools' );
	}

	get helpElement() {
		return $( '.ve-ui-toolbar-group-help' );
	}

	get insert() {
		return $( '.ve-ui-toolbar-group-insert' );
	}

	get insertMenu() {
		return $( '.oo-ui-tool-name-media' );
	}

	get insertTableElement() {
		return $( '.oo-ui-tool-name-insertTable' );
	}

	get insertedTable() {
		return $( 'table.ve-ce-branchNode' );
	}

	get insertedBulletList() {
		return $( 'ul.ve-ce-branchNode' );
	}

	get insertedNumberedList() {
		return $( 'ol.ve-ce-branchNode' );
	}

	get indentedBulletList() {
		return $( 'ul.ve-ce-branchNode ul.ve-ce-branchNode' );
	}

	get indentedNumberedList() {
		return $( 'ol.ve-ce-branchNode ol.ve-ce-branchNode' );
	}

	get linkMenu() {
		return $( 'div.ve-ui-mwLinkAnnotationInspector' );
	}

	get linkInput() {
		return $( 'div.ve-ui-mwLinkAnnotationInspector input' );
	}

	get insertedInternalLink() {
		return $( 'a.ve-ce-mwInternalLinkAnnotation' );
	}

	get insertedExternalLink() {
		return $( 'span.ve-ce-mwNumberedExternalLinkNode' );
	}

	get pageTitle() {
		return $( 'h1.ve-ce-headingNode' );
	}

	get heading() {
		return $( 'h2.ve-ce-headingNode' );
	}

	get subHeadingOne() {
		return $( 'h3.ve-ce-headingNode' );
	}

	get subHeadingTwo() {
		return $( 'h4.ve-ce-headingNode' );
	}

	get subHeadingThree() {
		return $( 'h5.ve-ce-headingNode' );
	}

	get subHeadingFour() {
		return $( 'h6.ve-ce-headingNode' );
	}

	get preformatted() {
		return $( 'pre.ve-ce-branchNode' );
	}

	get blockQuote() {
		return $( 'blockquote.ve-ce-branchNode' );
	}

	get bold() {
		return $( 'b.ve-ce-boldAnnotation' );
	}

	get italic() {
		return $( 'i.ve-ce-italicAnnotation' );
	}

	get superscript() {
		return $( 'sup.ve-ce-superscriptAnnotation' );
	}

	get subscript() {
		return $( 'sub.ve-ce-subscriptAnnotation' );
	}

	get code() {
		return $( 'code.ve-ce-codeAnnotation' );
	}

	get strikethrough() {
		return $( 's.ve-ce-strikethroughAnnotation' );
	}

	get underline() {
		return $( 'u.ve-ce-underlineAnnotation' );
	}

	get commentMenu() {
		return $( 'div.ve-ui-commentInspector-content' );
	}

	get commentInput() {
		return $( 'div.ve-ui-commentInspector-content textarea' );
	}

	get insertedComment() {
		return $( 'span.ve-ce-commentNode' );
	}

	get notices() {
		return $( '.ve-ui-mwNoticesPopupTool-items' );
	}

	get notification() {
		return $( 'div.mw-notification-content span.oo-ui-labelElement-label' );
	}

	get options() {
		return $( '.oo-ui-tool-name-meta' );
	}

	get pageOptionsElement() {
		return $( '.ve-ui-toolbar-group-pageMenu' );
	}

	get paragraphFormatMenu() {
		return $( '.oo-ui-tool-name-paragraph' );
	}

	get popupToolGroup() {
		return $( '.oo-ui-popupToolGroup-active-tools' );
	}

	get savePage() {
		return $( '.ve-ui-overlay-global .oo-ui-processDialog-actions-primary' );
	}

	get savePageDots() {
		return $( '.ve-ui-toolbar-saveButton' );
	}

	get specialCharacterElement() {
		return $( '.oo-ui-tool-name-specialCharacter' );
	}

	get specialCharacterMenu() {
		return $( '.oo-ui-menuLayout' );
	}

	get structureOptionsElement() {
		return $( '.ve-ui-toolbar-group-structure' );
	}

	get styleTextElement() {
		return $( '.ve-ui-toolbar-group-style' );
	}

	get switchEditorElement() {
		return $( '.ve-ui-toolbar-group-editMode' );
	}

	get toolbar() {
		return $( '.ve-init-mw-desktopArticleTarget-toolbar-open' );
	}

	get veBodyContent() {
		return $( '.mw-body-content.ve-ui-surface' );
	}

	get veRootNode() {
		return $( '.ve-ce-rootNode[role="textbox"]' );
	}

	get visualEditing() {
		return $( '.oo-ui-tool-name-editModeVisual' );
	}

	async openForEditing( title ) {
		return super.openTitle( title, { veaction: 'edit', cxhidebetapopup: 1, hidewelcomedialog: 1, vehidebetadialog: 1 } );
	}

	activationComplete() {
		return browser.executeAsync( ( done ) => {
			mw.hook( 've.activationComplete' ).add( () => {
				done();
			} );
		} );
	}

	clearBeforeUnload() {
		// T269566: Clear VE's beforeunload handler before navigating to avoid the
		// 'Leave site? Changes that you made may not be saved.' popup.
		return browser.execute( () => {
			// eslint-disable-next-line no-undef
			window.onbeforeunload = null;
		} );
	}

	focusRootNode() {
		return browser.execute( () => {
			// eslint-disable-next-line no-undef
			document.querySelector( '.ve-ce-rootNode[role="textbox"]' ).focus();
		} );
	}

	async insertTable() {
		await this.insert.click();
		await this.insertTableElement.click();
	}

	saveComplete() {
		return browser.executeAsync( ( done ) => {
			ve.init.target.on( 'save', () => {
				done();
			} );
		} );
	}

}

export default new EditPage();
