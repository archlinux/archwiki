<?php
/**
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

namespace MediaWiki\Extension\Gadgets\Content;

use MediaWiki\Content\JsonContent;
use MediaWiki\Json\FormatJson;
use MediaWiki\Status\Status;

class GadgetDefinitionContent extends JsonContent {

	/** @var Status|null Cached validation result */
	private $validation;

	/**
	 * @param string $text
	 */
	public function __construct( $text ) {
		parent::__construct( $text, 'GadgetDefinition' );
	}

	public function isValid() {
		// parent::isValid() is called in validate()
		return $this->validate()->isOK();
	}

	/**
	 * Pretty-print JSON.
	 *
	 * If called before validation, it may return JSON "null".
	 *
	 * @return string
	 */
	public function beautifyJSON() {
		// @todo we should normalize entries in module.pages
		return FormatJson::encode( $this->getAssocArray(), "\t", FormatJson::UTF8_OK );
	}

	/**
	 * Helper for isValid
	 *
	 * This placed into a separate method so that the detailed error can be communicated
	 * to editors via GadgetDefinitionContentHandler::validateSave, instead of the generic
	 * 'invalid-content-data' message from ContentHandler::validateSave based on isValid.
	 *
	 * @return Status
	 */
	public function validate() {
		// Cache the validation result to avoid re-computations
		if ( !$this->validation ) {
			if ( !parent::isValid() ) {
				// Invalid JSON, use the detailed Status from JsonContent::getData for syntax errors.
				$this->validation = $this->getData();
			} else {
				$validator = new GadgetDefinitionValidator();
				$this->validation = $validator->validate( $this->getAssocArray(), true );
			}
		}
		return $this->validation;
	}

	/**
	 * Get the JSON content as an associative array with
	 * all fields filled out, populating defaults as necessary.
	 *
	 * @return array
	 * @suppress PhanUndeclaredMethod
	 */
	public function getAssocArray() {
		$info = wfObjectToArray( $this->getData()->getValue() );
		/** @var GadgetDefinitionContentHandler $handler */
		$handler = $this->getContentHandler();
		$info = wfArrayPlus2d( $info, $handler->getEmptyDefinition() );

		return $info;
	}

	/**
	 * @inheritDoc
	 */
	protected function objectTable( $val ) {
		if ( $val instanceof GadgetDefinitionContentArmor ) {
			return (string)$val;
		}

		return parent::objectTable( $val );
	}
}
