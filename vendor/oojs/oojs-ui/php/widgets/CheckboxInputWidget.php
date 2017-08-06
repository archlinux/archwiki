<?php

namespace OOUI;

/**
 * Checkbox input widget.
 */
class CheckboxInputWidget extends InputWidget {

	/* Static Properties */

	public static $tagName = 'span';

	/* Properties */

	/**
	 * Whether the checkbox is selected.
	 *
	 * @var boolean
	 */
	protected $selected;

	/**
	 * @param array $config Configuration options
	 * @param boolean $config['selected'] Whether the checkbox is initially selected
	 *   (default: false)
	 */
	public function __construct( array $config = [] ) {
		// Parent constructor
		parent::__construct( $config );

		// Initialization
		$this->addClasses( [ 'oo-ui-checkboxInputWidget' ] );
		// Required for pretty styling in MediaWiki theme
		$this->appendContent( new Tag( 'span' ) );
		$this->setSelected( isset( $config['selected'] ) ? $config['selected'] : false );
	}

	protected function getInputElement( $config ) {
		return ( new Tag( 'input' ) )->setAttributes( [ 'type' => 'checkbox' ] );
	}

	/**
	 * Set selection state of this checkbox.
	 *
	 * @param boolean $state Whether the checkbox is selected
	 * @return $this
	 */
	public function setSelected( $state ) {
		$this->selected = (bool)$state;
		if ( $this->selected ) {
			$this->input->setAttributes( [ 'checked' => 'checked' ] );
		} else {
			$this->input->removeAttributes( [ 'checked' ] );
		}
		return $this;
	}

	/**
	 * Check if this checkbox is selected.
	 *
	 * @return boolean Checkbox is selected
	 */
	public function isSelected() {
		return $this->selected;
	}

	public function getConfig( &$config ) {
		if ( $this->selected ) {
			$config['selected'] = $this->selected;
		}
		return parent::getConfig( $config );
	}
}
