<?php

namespace OOUI;

/**
 * OptionWidgets are special elements that can be selected and configured with data. The
 * data is often unique for each option, but it does not have to be.
 *
 * OptionWidgets are used SelectWidget to create a selection of mutually exclusive options.
 */
class OptionWidget extends Widget {

	use LabelElement;
	use FlaggedElement;
	use AccessKeyedElement;
	use TitledElement;

	/**
	 * @param array $config Configuration options
	 *      - string|HtmlSnippet $config['label'] Label text
	 *      - bool $config['invisibleLabel'] Whether the label should be visually hidden (but still
	 *        accessible to screen-readers). (default: false)
	 * @param-taint $config escapes_html
	 */
	public function __construct( array $config = [] ) {
		parent::__construct( $config );

		$this->initializeFlaggedElement( $config );
		$this->initializeTitledElement( array_merge( [ 'titled' => $this ], $config ) );
		$this->initializeAccessKeyedElement( array_merge( [ 'accessKeyed' => $this ], $config ) );
		$this->initializeLabelElement( $config );

		$this->setLabel( $config['label'] ?? '' );
		$this->appendContent( $this->label );

		$selected = $config['selected'] ?? false;

		$this->addClasses( [ 'oo-ui-optionWidget' ] );
		$this->toggleClasses( [ 'oo-ui-optionWidget-selected' ], $selected );
		$this->setAttributes( [
			'role' => 'option',
			// 'selected' is not a config option, so set aria-selected false by default (same as js)
			'aria-selected' => $selected ? 'true' : 'false',
		] );
	}

	public function getConfig( &$config ) {
		$selected = $this->hasClass( 'oo-ui-optionWidget-selected' );
		if ( $selected ) {
			$config['selected'] = $selected;
		}
		return parent::getConfig( $config );
	}
}
