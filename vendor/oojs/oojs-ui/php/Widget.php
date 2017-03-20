<?php

namespace OOUI;

/**
 * User interface control.
 *
 * @abstract
 */
class Widget extends Element {

	/* Static Properties */

	/**
	 * Whether this widget will behave reasonably when wrapped in a HTML `<label>`. If this is true,
	 * wrappers such as FieldLayout may use a `<label>`.
	 *
	 * @var boolean
	 */
	public static $supportsSimpleLabel = false;

	/* Properties */

	/**
	 * Disabled.
	 *
	 * @var boolean Widget is disabled
	 */
	protected $disabled = false;

	/* Methods */

	/**
	 * @param array $config Configuration options
	 * @param boolean $config['disabled'] Disable (default: false)
	 */
	public function __construct( array $config = [] ) {
		// Initialize config
		$config = array_merge( [ 'disabled' => false ], $config );

		// Parent constructor
		parent::__construct( $config );

		// Initialization
		$this->addClasses( [ 'oo-ui-widget' ] );
		$this->setDisabled( $config['disabled'] );
	}

	/**
	 * Check if the widget is disabled.
	 *
	 * @return boolean Button is disabled
	 */
	public function isDisabled() {
		return $this->disabled;
	}

	/**
	 * Set the disabled state of the widget.
	 *
	 * This should probably change the widgets' appearance and prevent it from being used.
	 *
	 * @param boolean $disabled Disable widget
	 * @return $this
	 */
	public function setDisabled( $disabled ) {
		$this->disabled = !!$disabled;
		$this->toggleClasses( [ 'oo-ui-widget-disabled' ], $this->disabled );
		$this->toggleClasses( [ 'oo-ui-widget-enabled' ], !$this->disabled );
		$this->setAttributes( [ 'aria-disabled' => $this->disabled ? 'true' : 'false' ] );

		return $this;
	}

	public function getConfig( &$config ) {
		if ( $this->disabled ) {
			$config['disabled'] = $this->disabled;
		}
		return parent::getConfig( $config );
	}
}
