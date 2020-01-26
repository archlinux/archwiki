<?php

namespace OOUI;

/**
 * A SelectWidget is of a generic selection of options.
 *
 * Should be used in conjunction with OptionWidget
 */
class SelectWidget extends Widget {

	use GroupWidget;

	/**
	 * @param array $config Configuration options
	 *      - OptionWidget[] $config['items'] OptionWidget objects to add to the select
	 * @param-taint $config escapes_html
	 */
	public function __construct( array $config = [] ) {
		$config = array_merge(
			[ 'items' => [] ],
			$config
		);

		$this->initializeGroupElement( array_merge( $config, [ 'group' => $this ] ) );

		$this->addClasses( [
			'oo-ui-selectWidget',
			'oo-ui-selectWidget-unpressed',
		] );
		$this->setAttributes( [
			'role' => 'listbox'
		] );

		$this->addItems( $config['items'] );
		parent::__construct( $config );
	}
}
