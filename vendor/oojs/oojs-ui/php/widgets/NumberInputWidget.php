<?php

namespace OOUI;

/**
 * Input widget with a number field.
 */
class NumberInputWidget extends TextInputWidget {

	protected $buttonStep;
	protected $pageStep;
	protected $showButtons;

	/**
	 * @param array $config Configuration options
	 * @param int $config['placeholder'] Placeholder number
	 * @param bool $config['autofocus'] Ask the browser to focus this widget, using the 'autofocus'
	 *   HTML attribute (default: false)
	 * @param bool $config['readOnly'] Prevent changes (default: false)
	 * @param number $config['min'] Minimum input allowed
	 * @param number $config['max'] Maximum input allowed
	 * @param number|null $config['step'] If specified, the field only accepts values that are
	 *   multiples of this. (default: null)
	 * @param number $config['buttonStep'] Delta when using the buttons or up/down arrow keys.
	 *   Defaults to `step` if specified, otherwise `1`.
	 * @param number $config['pageStep'] Delta when using the page-up/page-down keys.
	 *   Defaults to 10 times `buttonStep`.
	 * @param number $config['showButtons'] Show increment and decrement buttons (default: true)
	 * @param bool $config['required'] Mark the field as required.
	 *   Implies `indicator: 'required'`. Note that `false` & setting `indicator: 'required'
	 * @param-taint $config escapes_html
	 */
	public function __construct( array $config = [] ) {
		$config['type'] = 'number';
		$config['multiline'] = false;

		// Parent constructor
		parent::__construct( $config );

		if ( isset( $config['min'] ) ) {
			$this->input->setAttributes( [ 'min' => $config['min'] ] );
		}

		if ( isset( $config['max'] ) ) {
			$this->input->setAttributes( [ 'max' => $config['max'] ] );
		}

		$this->input->setAttributes( [ 'step' => $config['step'] ?? 'any' ] );

		if ( isset( $config['buttonStep'] ) ) {
			$this->buttonStep = $config['buttonStep'];
		}
		if ( isset( $config['pageStep'] ) ) {
			$this->pageStep = $config['pageStep'];
		}
		if ( isset( $config['showButtons'] ) ) {
			$this->showButtons = $config['showButtons'];
		}

		$this->addClasses( [
			'oo-ui-numberInputWidget',
			'oo-ui-numberInputWidget-php',
		] );
	}

	public function getConfig( &$config ) {
		$min = $this->input->getAttribute( 'min' );
		if ( $min !== null ) {
			$config['min'] = $min;
		}
		$max = $this->input->getAttribute( 'max' );
		if ( $max !== null ) {
			$config['max'] = $max;
		}
		$step = $this->input->getAttribute( 'step' );
		if ( $step !== 'any' ) {
			$config['step'] = $step;
		}
		if ( $this->pageStep !== null ) {
			$config['pageStep'] = $this->pageStep;
		}
		if ( $this->buttonStep !== null ) {
			$config['buttonStep'] = $this->buttonStep;
		}
		if ( $this->showButtons !== null ) {
			$config['showButtons'] = $this->showButtons;
		}
		return parent::getConfig( $config );
	}
}
