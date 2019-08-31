<?php

namespace OOUI;

/**
 * StackLayouts contain a series of PanelLayouts
 */
class StackLayout extends PanelLayout {

	use GroupElement;

	/**
	 * @param array $config Configuration options
	 *      - bool $config['continuous'] Show all panels, one after another (default: false)
	 *      - PanelLayout[] $config['items'] Panel layouts to add to the stack layout.
	 * @param-taint $config escapes_htmlnoent
	 */
	public function __construct( array $config = [] ) {
		$config = array_merge( [
			'preserveContent' => false,
			'continuous' => false,
			'items' => [],
			'scrollable' => !empty( $config['continuous'] ) && $config['continuous']
		], $config );

		// Parent constructor
		parent::__construct( $config );

		// Traits
		$this->initializeGroupElement( array_merge( $config, [ 'group' => $this ] ) );

		// Initialization
		$this->addClasses( [ 'oo-ui-stackLayout' ] );
		if ( $config['continuous'] ) {
			$this->addClasses( [ 'oo-ui-stackLayout-continuous' ] );
		}
		$this->addItems( $config['items'] );
	}

	public function getConfig( &$config ) {
		$config = parent::getConfig( $config );
		if ( $this->hasClass( 'oo-ui-stackLayout-continuous' ) ) {
			$config['continuous'] = true;
			// scrollable default has changed to true
			if ( !$this->hasClass( 'oo-ui-panelLayout-scrollable' ) ) {
				$config['scrollable'] = false;
			} else {
				unset( $config['scrollable'] );
			}
		}
		return $config;
	}

}
