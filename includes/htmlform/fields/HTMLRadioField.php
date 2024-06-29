<?php

namespace MediaWiki\HTMLForm\Field;

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLFormField;
use MediaWiki\Parser\Sanitizer;
use Xml;

/**
 * Radio checkbox fields.
 *
 * @stable to extend
 */
class HTMLRadioField extends HTMLFormField {
	/**
	 * @stable to call
	 * @param array $params
	 *   In addition to the usual HTMLFormField parameters, this can take the following fields:
	 *   - 'flatlist': If given, the options will be displayed on a single line (wrapping to following
	 *     lines if necessary), rather than each one on a line of its own. This is desirable mostly
	 *     for very short lists of concisely labelled options.
	 *   - 'option-descriptions': Associative array mapping values to raw HTML descriptions. These
	 *     descriptions are displayed below their respective options. Note that the key-value
	 *     relationship is reversed compared to 'options' and friends. Only supported by the Codex
	 *     display format; if this is set when another display format is used, an exception is thrown.
	 *   - 'option-descriptions-messages': Associative array mapping values to message keys.
	 *     Overwrites 'option-descriptions'.
	 *   - 'option-descriptions-messages-parse': If true, parse the messages in
	 *     'option-descriptions-messages'.
	 */
	public function __construct( $params ) {
		parent::__construct( $params );

		if ( isset( $params['flatlist'] ) ) {
			$this->mClass .= ' mw-htmlform-flatlist';
		}
	}

	public function validate( $value, $alldata ) {
		$p = parent::validate( $value, $alldata );

		if ( $p !== true ) {
			return $p;
		}

		if ( !is_string( $value ) && !is_int( $value ) ) {
			return $this->msg( 'htmlform-required' );
		}

		$validOptions = HTMLFormField::flattenOptions( $this->getOptions() );

		if ( in_array( strval( $value ), $validOptions, true ) ) {
			return true;
		} else {
			return $this->msg( 'htmlform-select-badoption' );
		}
	}

	/**
	 * This returns a block of all the radio options, in one cell.
	 * @see includes/HTMLFormField#getInputHTML()
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	public function getInputHTML( $value ) {
		if (
			isset( $this->mParams['option-descriptions'] ) ||
			isset( $this->mParams['option-descriptions-messages'] ) ) {
			throw new \InvalidArgumentException(
				"Non-Codex HTMLForms do not support the 'option-descriptions' parameter for radio buttons"
			);
		}

		$html = $this->formatOptions( $this->getOptions(), strval( $value ) );

		return $html;
	}

	public function getInputOOUI( $value ) {
		if (
			isset( $this->mParams['option-descriptions'] ) ||
			isset( $this->mParams['option-descriptions-messages'] ) ) {
			throw new \InvalidArgumentException(
				"Non-Codex HTMLForms do not support the 'option-descriptions' parameter for radio buttons"
			);
		}

		$options = [];
		foreach ( $this->getOptions() as $label => $data ) {
			if ( is_int( $label ) ) {
				$label = strval( $label );
			}
			$options[] = [
				'data' => $data,
				// @phan-suppress-next-line SecurityCheck-XSS Labels are raw when not from message
				'label' => $this->mOptionsLabelsNotFromMessage ? new \OOUI\HtmlSnippet( $label ) : $label,
			];
		}

		return new \OOUI\RadioSelectInputWidget( [
			'name' => $this->mName,
			'id' => $this->mID,
			'value' => $value,
			'options' => $options,
		] + \OOUI\Element::configFromHtmlAttributes(
			$this->getAttributes( [ 'disabled', 'tabindex' ] )
		) );
	}

	public function getInputCodex( $value, $hasErrors ) {
		$optionDescriptions = $this->getOptionDescriptions();
		$html = '';

		// Iterate over an array of options and return the HTML markup.
		foreach ( $this->getOptions() as $label => $radioValue ) {
			$description = $optionDescriptions[$radioValue] ?? '';
			$descriptionID = Sanitizer::escapeIdForAttribute(
				$this->mID . "-$radioValue-description"
			);

			// Attributes for the radio input.
			$radioInputClasses = [ 'cdx-radio__input' ];
			$radioInputAttribs = [
				'id' => Sanitizer::escapeIdForAttribute( $this->mID . "-$radioValue" ),
				'type' => 'radio',
				'name' => $this->mName,
				'class' => $radioInputClasses,
				'value' => $radioValue
			];
			$radioInputAttribs += $this->getAttributes( [ 'disabled', 'tabindex' ] );
			if ( $description ) {
				$radioInputAttribs['aria-describedby'] = $descriptionID;
			}

			// Set the selected value as "checked".
			if ( $radioValue === $value ) {
				$radioInputAttribs['checked'] = true;
			}

			// Attributes for the radio icon.
			$radioIconClasses = [ 'cdx-radio__icon' ];
			$radioIconAttribs = [
				'class' => $radioIconClasses,
			];

			// Attributes for the radio label.
			$radioLabelClasses = [ 'cdx-label__label' ];
			$radioLabelAttribs = [
				'class' => $radioLabelClasses,
				'for' => $radioInputAttribs['id']
			];

			// HTML markup for radio input, radio icon, and radio label elements.
			$radioInput = Html::element( 'input', $radioInputAttribs );
			$radioIcon = Html::element( 'span', $radioIconAttribs );
			$radioLabel = $this->mOptionsLabelsNotFromMessage
				? Html::rawElement( 'label', $radioLabelAttribs, $label )
				: Html::element( 'label', $radioLabelAttribs, $label );

			$radioDescription = '';
			if ( isset( $optionDescriptions[$radioValue] ) ) {
				$radioDescription = Html::rawElement(
					'span',
					[ 'id' => $descriptionID, 'class' => 'cdx-label__description' ],
					$optionDescriptions[$radioValue]
				);
			}
			$radioLabelWrapper = Html::rawElement(
				'div',
				[ 'class' => 'cdx-radio__label cdx-label' ],
				$radioLabel . $radioDescription
			);

			// HTML markup for CSS-only Codex Radio.
			$radio = Html::rawElement(
				'span',
				[ 'class' => 'cdx-radio' ],
				$radioInput . $radioIcon . $radioLabelWrapper
			);

			// Append the Codex Radio HTML markup to the initialized empty string variable.
			$html .= $radio;
		}

		return $html;
	}

	public function formatOptions( $options, $value ) {
		$html = '';

		$attribs = $this->getAttributes( [ 'disabled', 'tabindex' ] );
		$elementFunc = [ Html::class, $this->mOptionsLabelsNotFromMessage ? 'rawElement' : 'element' ];

		# @todo Should this produce an unordered list perhaps?
		foreach ( $options as $label => $info ) {
			if ( is_array( $info ) ) {
				$html .= Html::rawElement( 'h1', [], $label ) . "\n";
				$html .= $this->formatOptions( $info, $value );
			} else {
				$id = Sanitizer::escapeIdForAttribute( $this->mID . "-$info" );
				$classes = [ 'mw-htmlform-flatlist-item' ];
				$radio = Xml::radio(
					$this->mName, $info, $info === $value, $attribs + [ 'id' => $id ]
				);
				$radio .= "\u{00A0}" . call_user_func( $elementFunc, 'label', [ 'for' => $id ], $label );

				$html .= ' ' . Html::rawElement(
					'div',
					[ 'class' => $classes ],
					$radio
				);
			}
		}

		return $html;
	}

	/**
	 * Fetch the array of option descriptions from the field's parameters. This checks
	 * 'option-descriptions-messages' first, then 'option-descriptions'.
	 *
	 * @return array|null Array mapping option values to raw HTML descriptions
	 */
	protected function getOptionDescriptions() {
		if ( array_key_exists( 'option-descriptions-messages', $this->mParams ) ) {
			$needsParse = $this->mParams['option-descriptions-messages-parse'] ?? false;
			$optionDescriptions = [];
			foreach ( $this->mParams['option-descriptions-messages'] as $value => $msgKey ) {
				$msg = $this->msg( $msgKey );
				$optionDescriptions[$value] = $needsParse ? $msg->parse() : $msg->escaped();
			}
			return $optionDescriptions;
		} elseif ( array_key_exists( 'option-descriptions', $this->mParams ) ) {
			return $this->mParams['option-descriptions'];
		}
	}

	protected function needsLabel() {
		return false;
	}
}

/** @deprecated class alias since 1.42 */
class_alias( HTMLRadioField::class, 'HTMLRadioField' );
