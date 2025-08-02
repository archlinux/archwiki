<?php

namespace MediaWiki\Extension\Nuke\Form\HTMLForm;

use MediaWiki\HTMLForm\Field\HTMLDateTimeField;
use MediaWiki\HTMLForm\Field\HTMLTextField;

/**
 * A subclass of HTMLDateTimeField which is not infused by default. This allows
 * us to finely control validation of the "from" and "to" date fields on the
 * Special:Nuke form.
 */
class NukeDateTimeField extends HTMLDateTimeField {

	/**
	 * Type for this field. Should be "date", but backwards compatibility in case it isn't exists.
	 * @var string
	 */
	protected $mType = "date";

	/**
	 * @internal
	 * @inheritDoc
	 */
	public function __construct( $params ) {
		parent::__construct( $params );

		// Use 'date' by default, as this is what's used on Special:Nuke and what the module
		// explicitly supports.
		$this->mType = 'date';

		// Set 'min' from 'maxAge', if provided
		if ( array_key_exists( 'maxAge', $params ) && $params['maxAge'] ) {
			$this->mParams['min'] = date( 'Y-m-d', time() - $params['maxAge'] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function validate( $value, $alldata ) {
		// Avoiding `parent`; we want to skip the validation from HTMLDateTimeField.
		$p = HTMLTextField::validate( $value, $alldata );

		if ( $p !== true ) {
			return $p;
		}

		if ( $value === '' ) {
			// required was already checked by parent::validate
			return true;
		}

		$date = $this->parseDate( $value );
		if ( !$date ) {
			// Messages: htmlform-date-invalid htmlform-time-invalid htmlform-datetime-invalid
			return $this->msg( "htmlform-{$this->mType}-invalid" );
		}

		if ( isset( $this->mParams['min'] ) ) {
			$min = $this->parseDate( $this->mParams['min'] );
			if ( $min && $date < $min ) {
				if ( array_key_exists( 'maxAge', $this->mParams ) && $this->mParams['maxAge'] ) {
					// Use a custom message when the date is below the minimum
					return $this->msg(
						"nuke-date-limited",
						$this->mParent->getLanguage()->formatTimePeriod( $this->mParams['maxAge'], [
							'avoid' => 'avoidhours',
							'noabbrevs' => true
						] )
					);
				} else {
					// For compatibility with original HTMLDateTimeField options

					// Messages: htmlform-date-toolow htmlform-time-toolow htmlform-datetime-toolow
					return $this->msg( "htmlform-{$this->mType}-toolow", $this->formatDate( $min ) );
				}
			}
		}

		if ( isset( $this->mParams['max'] ) ) {
			$max = $this->parseDate( $this->mParams['max'] );
			if ( $max && $date > $max ) {
				// Messages: htmlform-date-toohigh htmlform-time-toohigh htmlform-datetime-toohigh
				return $this->msg( "htmlform-{$this->mType}-toohigh", $this->formatDate( $max ) );
			}
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function getFieldLayoutOOUI( $inputField, $config ) {
		$widget = parent::getFieldLayoutOOUI( $inputField, $config );
		// Add a custom class that we can select with JavaScript later.
		$widget->addClasses( [ "ext-nuke-dateTimeField" ] );
		return $widget;
	}

	/**
	 * @inheritDoc
	 */
	public function getInputOOUI( $value ) {
		// Forcefully add required modules here.
		// `shouldInfuseOOUI` being false will cause module preloading to be disabled. We need
		// to do this on our own (see HTMLFormField::getOOUI)
		$this->mParent->getOutput()->addModules( 'mediawiki.htmlform.ooui' );
		$this->mParent->getOutput()->addModules( $this->getOOUIModules() );

		return parent::getInputOOUI( $value );
	}

	/**
	 * @inheritDoc
	 */
	protected function getOOUIModules() {
		return [ 'mediawiki.widgets.DateInputWidget', 'ext.nuke.fields.NukeDateTimeField' ];
	}

	/**
	 * @inheritDoc
	 */
	protected function shouldInfuseOOUI() {
		// Do not infuse by default; we'll do this on our own with the
		// `ext.nuke.fields.NukeDateTimeField` module.
		return false;
	}

}
