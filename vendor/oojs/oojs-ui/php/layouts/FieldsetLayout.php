<?php

namespace OOUI;

/**
 * Layout made of a fieldset and optional legend.
 *
 * Just add FieldLayout items.
 */
class FieldsetLayout extends Layout {
	use IconElement;
	use LabelElement;
	use GroupElement;

	/**
	 * @param array $config Configuration options
	 * @param FieldLayout[] $config['items'] Items to add
	 */
	public function __construct( array $config = [] ) {
		// Parent constructor
		parent::__construct( $config );

		// Traits
		$this->initializeIconElement( $config );
		$this->initializeLabelElement( $config );
		$this->initializeGroupElement( $config );

		// Initialization
		$this
			->addClasses( [ 'oo-ui-fieldsetLayout' ] )
			->prependContent( $this->icon, $this->label, $this->group );
		if ( isset( $config['items'] ) ) {
			$this->addItems( $config['items'] );
		}
	}
}
