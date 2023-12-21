<?php

/**
 * HTML form generation and submission handling, vertical-form style.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use MediaWiki\Html\Html;

/**
 * Compact stacked vertical format for forms.
 *
 * @stable to extend
 */
class VFormHTMLForm extends HTMLForm {
	/**
	 * Wrapper and its legend are never generated in VForm mode.
	 * @var bool
	 */
	protected $mWrapperLegend = false;

	protected $displayFormat = 'vform';

	public static function loadInputFromParameters( $fieldname, $descriptor,
		HTMLForm $parent = null
	) {
		$field = parent::loadInputFromParameters( $fieldname, $descriptor, $parent );
		$field->setShowEmptyLabel( false );
		return $field;
	}

	public function getHTML( $submitResult ) {
		// This is required for VForm HTMLForms that use that style regardless
		// of wgUseMediaWikiUIEverywhere (since they pre-date it).
		// When wgUseMediaWikiUIEverywhere is removed, this should be consolidated
		// with the addModuleStyles in SpecialPage->setHeaders.
		$this->getOutput()->addModuleStyles( [
			'mediawiki.ui',
			'mediawiki.ui.button',
			'mediawiki.ui.input',
			'mediawiki.ui.checkbox',
		] );

		return parent::getHTML( $submitResult );
	}

	/**
	 * @inheritDoc
	 */
	protected function formatField( HTMLFormField $field, $value ) {
		return $field->getVForm( $value );
	}

	protected function getFormAttributes() {
		return [ 'class' => [ 'mw-htmlform', 'mw-ui-vform', 'mw-ui-container' ] ] +
			parent::getFormAttributes();
	}

	public function wrapForm( $html ) {
		// Always discard $this->mWrapperLegend
		return Html::rawElement( 'form', $this->getFormAttributes(), $html );
	}
}
