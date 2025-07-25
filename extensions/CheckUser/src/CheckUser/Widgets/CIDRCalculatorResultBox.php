<?php

namespace MediaWiki\CheckUser\CheckUser\Widgets;

use OOUI\TextInputWidget;

class CIDRCalculatorResultBox extends TextInputWidget {

	/**
	 * @param array $config
	 */
	public function __construct( array $config = [] ) {
		parent::__construct( $config );
		$this->input->setAttributes( [ 'disabled' => 'disabled' ] );
	}

	/**
	 * Because this widget is always disabled
	 * by definition this does nothing.
	 *
	 * @param bool $disabled unused
	 * @return $this
	 */
	public function setDisabled( $disabled ) {
		// Ignore calls to setDisabled as it should always be disabled.
		return $this;
	}
}
