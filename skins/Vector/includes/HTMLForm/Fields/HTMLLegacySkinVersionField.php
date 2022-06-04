<?php

namespace Vector\HTMLForm\Fields;

use Vector\Constants;

/**
 * The field on Special:Preferences (and Special:GlobalPreferences) that allows the user to
 * enable/disable the legacy version of the Vector skin. Per
 * https://phabricator.wikimedia.org/T242381, the field is a checkbox, that, when checked, enables
 * the legacy version of the Vector skin.
 *
 * `HTMLLegacySkinVersionField` adapts the boolean storage type of a checkbox field to the string
 * storage type of the Vector skin version preference (e.g. see `Constants::SKIN_VERSION_LEGACY`).
 *
 * However, we cannot extend `HTMLCheckField` to inherit the behavior of a checkbox field.
 * `HTMLCheckField::loadDataFromRequest` returns boolean values. Returning non-boolean values in
 * `HTMLLegacySkinVersionField::loadDataFromRequest` would violate Liskov's Substitution Principle.
 * Like `HTMLExpiryField`, `HTMLLegacySkinVersionField` proxies to a private instance of
 * `HTMLCheckField`, adapting parameter and return values where necessary.
 *
 * @package Vector\HTMLForm\Fields
 * @internal
 */
final class HTMLLegacySkinVersionField extends \HTMLFormField {

	/**
	 * @var \HTMLCheckField
	 */
	private $checkField;

	/**
	 * @inheritDoc
	 */
	public function __construct( $params ) {
		/**
		 * HTMLCheckField must be given a boolean as the 'default' value.
		 * Since MW 1.38.0-wmf.9, we could be given a boolean or a string.
		 * @see T296068
		 */
		$params['default'] = $params['default'] === true ||
			$params['default'] === Constants::SKIN_VERSION_LEGACY;

		parent::__construct( $params );

		$this->checkField = new \HTMLCheckField( $params );
	}

	// BEGIN ADAPTER

	/** @inheritDoc */
	public function getInputHTML( $value ) {
		return $this->checkField->getInputHTML( $value === Constants::SKIN_VERSION_LEGACY );
	}

	/** @inheritDoc */
	public function getInputOOUI( $value ) {
		return $this->checkField->getInputOOUI( (string)( $value === Constants::SKIN_VERSION_LEGACY ) );
	}

	/**
	 * @inheritDoc
	 *
	 * @return string If the checkbox is checked, then `Constants::SKIN_VERSION_LEGACY`;
	 *  `Constants::SKIN_VERSION_LATEST` otherwise
	 */
	public function loadDataFromRequest( $request ) {
		return $this->checkField->loadDataFromRequest( $request )
			? Constants::SKIN_VERSION_LEGACY
			: Constants::SKIN_VERSION_LATEST;
	}

	// END ADAPTER

	/** @inheritDoc */
	public function getLabel() {
		return $this->checkField->getLabel();
	}

	// Note well that we can't invoke the following methods of `HTMLCheckField` directly because
	// they're protected and `HTMLSkinVectorField` doesn't extend `HTMLCheckField`.

	/** @inheritDoc */
	protected function getLabelAlignOOUI() {
		// See \HTMLCheckField::getLabelAlignOOUI
		return 'inline';
	}

	/** @inheritDoc */
	protected function needsLabel() {
		// See \HTMLCheckField::needsLabel
		return false;
	}
}
