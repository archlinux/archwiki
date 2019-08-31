<?php

namespace OOUI;

/**
 * TabSelectWidget is a list that contains TabOptionWidget options
 */
class TabSelectWidget extends SelectWidget {
	use TabIndexedElement;

	/**
	 * @param array $config Configuration options
	 * @param-taint $config escapes_html
	 */
	public function __construct( array $config = [] ) {
		parent::__construct( $config );

		$this->initializeTabIndexedElement( array_merge( $config, [ 'tabIndexed' => $this ] ) );

		$this->addClasses( [ 'oo-ui-tabSelectWidget' ] );
		$this->setAttributes( [
			'role' => 'tablist'
		] );
	}
}
