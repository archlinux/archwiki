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

	/* Static Properties */

	public static $tagName = 'fieldset';

	/**
	 * @param array $config Configuration options
	 * @param FieldLayout[] $config['items'] Items to add
	 */
	public function __construct( array $config = [] ) {
		// Parent constructor
		parent::__construct( $config );

		// Traits
		$this->initializeIconElement( $config );
		$this->initializeLabelElement( array_merge( $config, [
			'labelElement' => new Tag( 'legend' )
		] ) );
		$this->initializeGroupElement( $config );

		// Initialization
		$this->group->addClasses( [ 'oo-ui-fieldsetLayout-group' ] );
		$this
			->addClasses( [ 'oo-ui-fieldsetLayout' ] )
			->prependContent( $this->label, $this->icon, $this->group );
		if ( isset( $config['items'] ) ) {
			$this->addItems( $config['items'] );
		}
	}
}
