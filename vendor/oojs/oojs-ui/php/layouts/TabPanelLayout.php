<?php

namespace OOUI;

/**
 * TabPanelLayouts are used within IndexLayouts to create tab panels that
 * users can select and display from the index's optional TabSelectWidget
 * navigation. TabPanels are usually not instantiated directly, rather extended to include the
 * required content and functionality.
 *
 * Each tab panel must have a unique symbolic name, which is passed to the constructor.
 */
class TabPanelLayout extends PanelLayout {

	/**
	 * @var string
	 */
	private $name;
	/**
	 * @var string
	 */
	private $label;
	/**
	 * @var bool
	 */
	private $active;

	/**
	 * @param string $name Unique symbolic name of tab panel
	 * @param array $config Configuration options
	 *      - string|HtmlSnippet $config['label'] Label for tab panel's tab
	 * @param-taint $config escapes_htmlnoent
	 */
	public function __construct( $name, array $config = [] ) {
		// Allow passing positional parameters inside the config array
		if ( is_array( $name ) && isset( $name['name'] ) ) {
			$config = $name;
			$name = $config['name'];
		}

		$config = array_merge( [ 'scrollable' => true ], $config );

		// Parent constructor
		parent::__construct( $config );

		// Initialization
		$this->name = $name;
		$this->label = $config['label'] ?? null;
		$this->addClasses( [ 'oo-ui-tabPanelLayout' ] );
		$this->setAttributes( [
			'role' => 'tabpanel',
		] );
	}

	public function getConfig( &$config ) {
		$config['name'] = $this->name;
		$config['label'] = $this->label;
		// scrollable default has changed to true
		if ( !$this->hasClass( 'oo-ui-panelLayout-scrollable' ) ) {
			$config['scrollable'] = false;
		} else {
			unset( $config['scrollable'] );
		}
		return parent::getConfig( $config );
	}

	public function getName() {
		return $this->name;
	}

	public function getLabel() {
		return $this->label;
	}

	public function setActive( $active ) {
		$this->active = $active;
		$this->removeClasses( [ 'oo-ui-tabPanelLayout-active' ] );
		if ( $active ) {
			$this->addClasses( [ 'oo-ui-tabPanelLayout-active' ] );
		}
	}
}
