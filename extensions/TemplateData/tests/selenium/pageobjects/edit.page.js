import Page from 'wdio-mediawiki/Page.js';

class EditPage extends Page {

	async openTemplateInsertionDialog() {
		await super.openTitle( 'TemplateData-favoriting-templates', {
			veaction: 'edit',
			hidewelcomedialog: 1,
			vehidebetadialog: 1,
			cxhidebetapopup: 1
		} );
		try {
			await this.insertMenu.waitForDisplayed();
		} catch ( e ) {
			return false;
		}
		await this.insertMenu.click();
		await this.insertTemplate.click();
		return true;
	}

	get insertMenu() {
		return $( '.ve-ui-toolbar-group-insert' );
	}

	get insertTemplate() {
		return $( '.oo-ui-tool-name-transclusion' );
	}

	get dialogHeader() {
		return $( '.oo-ui-window-head .oo-ui-processDialog-location' );
	}

	get templateSearchFieldInput() {
		return $( '.ext-templatedata-search-field .oo-ui-inputWidget-input' );
	}

	get emptyListLabel() {
		return $( '.ext-templatedata-TemplateList-empty .oo-ui-labelElement-label' );
	}

	get searchResultsMenu() {
		return $( '.ext-templatedata-search-field .oo-ui-lookupElement-menu' );
	}

	getSearchResultFavoriteButton( num ) {
		return $( '.ext-templatedata-search-field .oo-ui-lookupElement-menu > :nth-child( ' + num + ' ) .oo-ui-buttonElement-button' );
	}

	get templateListMenuItems() {
		return $$( '.ext-templatedata-TemplateList .ext-templatedata-TemplateMenuItem' );
	}

}

export default new EditPage();
