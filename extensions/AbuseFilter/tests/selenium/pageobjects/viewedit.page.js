'use strict';

const Page = require( 'wdio-mediawiki/Page' );

class ViewEditPage extends Page {
	// Here we avoid things depending on the config, e.g. group and global
	get filterId() { return $( '#mw-abusefilter-edit-id .mw-input' ); }
	get name() { return $( 'input[name="wpFilterDescription"]' ); }
	get rules() { return $( '#wpFilterRules' ); }
	get comments() { return $( 'textarea[name="wpFilterNotes"]' ); }
	get hidden() { return $( 'input[name="wpFilterHidden"]' ); }
	get enabled() { return $( 'input[name="wpFilterEnabled"]' ); }
	get deleted() { return $( 'input[name="wpFilterDeleted"]' ); }

	// @todo This assumes that warn is enabled in the config, which is true by default
	get warnCheckbox() { return $( 'input[name="wpFilterActionWarn"]' ); }
	get warnOtherMessage() { return $( 'input[name="wpFilterWarnMessageOther"]' ); }

	get exportData() { return $( '#mw-abusefilter-export textarea' ).getValue(); }

	get submitButton() { return $( '#mw-abusefilter-editing-form input[type="submit"]' ); }

	get error() { return $( '.errorbox' ); }
	get warning() { return $( '.warningbox' ); }

	submit() {
		this.submitButton.waitForClickable();
		this.submitButton.click();
	}

	/**
	 * Conveniency: the ace editor is hard to manipulate, and working with
	 * the hidden textarea isn't great (sendKeys is not processed)
	 */
	switchEditor() {
		const button = $( '#mw-abusefilter-switcheditor' );
		button.waitForClickable();
		button.click();
	}

	setWarningMessage( msg ) {
		$( 'select[name="wpFilterWarnMessage"]' ).selectByAttribute( 'value', 'other' );
		this.warnOtherMessage.setValue( msg );
	}

	invalidateToken() {
		$( '#mw-abusefilter-editing-form input[name="wpEditToken"]' ).setValue( '' );
	}

	open( subpage ) {
		super.openTitle( 'Special:AbuseFilter/' + subpage );
	}
}
module.exports = new ViewEditPage();
