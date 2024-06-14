<?php

namespace MediaWiki\HTMLForm\Field;

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLFormField;
use MediaWiki\HTMLForm\VFormHTMLForm;
use Message;

/**
 * Adds a generic button inline to the form. Does not do anything, you must add
 * click handling code in JavaScript. Use a HTMLSubmitField if you merely
 * wish to add a submit button to a form.
 *
 * Additional recognized configuration parameters include:
 * - flags: OOUI flags for the button, see OOUI\FlaggedElement
 * - buttonlabel-message: Message to use for the button display text, instead
 *   of the value from 'default'. Overrides 'buttonlabel' and 'buttonlabel-raw'.
 * - buttonlabel: Text to display for the button display text, instead
 *   of the value from 'default'. Overrides 'buttonlabel-raw'.
 * - buttonlabel-raw: HTMLto display for the button display text, instead
 *   of the value from 'default'.
 * - formnovalidate: Set to true if clicking this button should suppress
 *   client-side form validation. Used in HTMLFormFieldCloner for add/remove
 *   buttons.
 *
 * @stable to extend
 * @since 1.22
 */
class HTMLButtonField extends HTMLFormField {
	protected $buttonType = 'button';
	protected $buttonLabel = null;

	/** @var array Flags to add to OOUI Button widget */
	protected $mFlags = [];

	protected $mFormnovalidate = false;

	/**
	 * @stable to call
	 * @inheritDoc
	 */
	public function __construct( $info ) {
		$info['nodata'] = true;

		$this->setShowEmptyLabel( false );

		parent::__construct( $info );

		if ( isset( $info['flags'] ) ) {
			$this->mFlags = $info['flags'];
		}

		if ( isset( $info['formnovalidate'] ) ) {
			$this->mFormnovalidate = $info['formnovalidate'];
		}

		# Generate the label from a message, if possible
		if ( isset( $info['buttonlabel-message'] ) ) {
			$this->buttonLabel = $this->getMessage( $info['buttonlabel-message'] )->parse();
		} elseif ( isset( $info['buttonlabel'] ) ) {
			if ( $info['buttonlabel'] === '&#160;' || $info['buttonlabel'] === "\u{00A0}" ) {
				// Apparently some things set &nbsp directly and in an odd format
				$this->buttonLabel = "\u{00A0}";
			} else {
				$this->buttonLabel = htmlspecialchars( $info['buttonlabel'] );
			}
		} elseif ( isset( $info['buttonlabel-raw'] ) ) {
			$this->buttonLabel = $info['buttonlabel-raw'];
		}
	}

	public function getInputHTML( $value ) {
		$flags = '';
		$prefix = 'mw-htmlform-';
		if ( $this->mParent instanceof VFormHTMLForm ) {
			$prefix = 'mw-ui-';
			// add mw-ui-button separately, so the descriptor doesn't need to set it
			$flags .= ' ' . $prefix . 'button';
		}
		foreach ( $this->mFlags as $flag ) {
			$flags .= ' ' . $prefix . $flag;
		}
		$attr = [
			'class' => 'mw-htmlform-submit ' . $this->mClass . $flags,
			'id' => $this->mID,
			'type' => $this->buttonType,
			'name' => $this->mName,
			'value' => $this->getDefault(),
			'formnovalidate' => $this->mFormnovalidate,
		] + $this->getAttributes( [ 'disabled', 'tabindex' ] );

		return Html::rawElement( 'button', $attr,
			$this->buttonLabel ?: htmlspecialchars( $this->getDefault() ) );
	}

	/**
	 * Get the OOUI widget for this field.
	 * @stable to override
	 * @param string $value
	 * @return \OOUI\ButtonInputWidget
	 */
	public function getInputOOUI( $value ) {
		return new \OOUI\ButtonInputWidget( [
			'name' => $this->mName,
			'value' => $this->getDefault(),
			'label' => $this->buttonLabel
				? new \OOUI\HtmlSnippet( $this->buttonLabel )
				: $this->getDefault(),
			'type' => $this->buttonType,
			'classes' => [ 'mw-htmlform-submit', $this->mClass ],
			'id' => $this->mID,
			'flags' => $this->mFlags,
		] + \OOUI\Element::configFromHtmlAttributes(
			$this->getAttributes( [ 'disabled', 'tabindex' ] )
		) );
	}

	public function getInputCodex( $value, $hasErrors ) {
		$flags = [];
		$flagClassMap = [
			'progressive' => 'cdx-button--action-progressive',
			'destructive' => 'cdx-button--action-destructive',
			'primary' => 'cdx-button--weight-primary',
			'quiet' => 'cdx-button--weight-quiet',
		];

		foreach ( $this->mFlags as $flag ) {
			if ( isset( $flagClassMap[$flag] ) ) {
				$flags[] = $flagClassMap[$flag];
			}
		}

		$buttonClasses = [ 'mw-htmlform-submit', 'cdx-button', $this->mClass ];
		$buttonClassesAndFlags = array_merge( $buttonClasses, $flags );
		$attr = [
			'class' => $buttonClassesAndFlags,
			'id' => $this->mID,
			'type' => $this->buttonType,
			'name' => $this->mName,
			'value' => $this->getDefault(),
			'formnovalidate' => $this->mFormnovalidate,
		] + $this->getAttributes( [ 'disabled', 'tabindex' ] );

		return Html::rawElement( 'button', $attr,
			$this->buttonLabel ?: htmlspecialchars( $this->getDefault() ) );
	}

	/**
	 * @inheritDoc
	 * @stable to override
	 */
	protected function needsLabel() {
		return false;
	}

	/**
	 * Button cannot be invalid
	 * @stable to override
	 *
	 * @param string $value
	 * @param array $alldata
	 *
	 * @return bool|string|Message
	 */
	public function validate( $value, $alldata ) {
		return true;
	}
}

/** @deprecated class alias since 1.42 */
class_alias( HTMLButtonField::class, 'HTMLButtonField' );
