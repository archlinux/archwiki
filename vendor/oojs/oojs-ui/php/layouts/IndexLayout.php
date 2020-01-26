<?php

namespace OOUI;

/**
 * IndexLayouts contain TabPanelLayout layouts as well as TabSelectWidget tabs that allow users
 * to navigate through the tab panels and select which one to display.
 *
 * Default php rendering shows all the tabs
 */
class IndexLayout extends MenuLayout {

	/**
	 * @var StackLayout
	 */
	protected $stackLayout;
	/**
	 * @var PanelLayout
	 */
	protected $tabPanel;
	/**
	 * @var PanelLayout[]
	 */
	protected $tabPanels;
	/**
	 * @var TabSelectWidget
	 */
	protected $tabSelectWidget;
	/**
	 * @var bool
	 */
	protected $autoFocus;
	/**
	 * @var bool
	 */
	protected $continuous;

	/**
	 * @param array $config Configuration options
	 *      - bool $config['continuous'] Focus on the first focusable element when a new tab panel is
	 *        displayed. Disabled on mobile. (default: false)
	 *      - bool $config['autoFocus'] (default: true)
	 *      - bool $config['framed'] (default: true)
	 * @param-taint $config escapes_htmlnoent
	 */
	public function __construct( array $config = [] ) {
		$config = array_merge(
			$config,
			[ 'menuPosition' => 'top' ]
		);

		parent::__construct( $config );

		$this->tabPanels = [];
		$this->continuous = $config['continuous'] ?? false;

		$this->stackLayout = $this->contentPanel ?? new StackLayout( [
			'continuous' => $this->continuous,
			'expanded' => $this->expanded
		] );
		$this->setContentPanel( $this->stackLayout );
		$this->autoFocus = $config['autoFocus'] ?? true;

		$this->tabSelectWidget = new TabSelectWidget( [
			'framed' => $config['framed'] ?? true
		] );
		$this->tabPanel = $this->menuPanel ?? new PanelLayout( [
			'expanded' => $this->expanded,
			'preserveContent' => false
		] );
		$this->setMenuPanel( $this->tabPanel );

		$this->toggleMenu( true );

		$this->addClasses( [ 'oo-ui-indexLayout' ] );
		$this->stackLayout->addClasses( [ 'oo-ui-indexLayout-stackLayout' ] );
		$this->tabPanel
			->addClasses( [ 'oo-ui-indexLayout-tabPanel' ] )
			->appendContent( $this->tabSelectWidget );
	}

	public function getConfig( &$config ) {
		$config = parent::getConfig( $config );
		if ( !$this->autoFocus ) {
			$config['autoFocus'] = $this->autoFocus;
		}
		if ( $this->continuous ) {
			$config['continuous'] = $this->continuous;
		}
		if ( count( $this->tabPanels ) ) {
			$config['tabPanels'] = $this->tabPanels;
		}
		// menuPosition is not configurable
		unset( $config['menuPosition'] );
		$config['tabSelectWidget'] = $this->tabSelectWidget;
		// stackLayout and tabPanel are identical to
		// contentPanel and menuPanel in MenuLayout
		return $config;
	}

	/**
	 * Add tab panels to the index layout
	 *
	 * When tab panels are added with the same names as existing tab panels, the existing tab panels
	 * will be automatically removed before the new tab panels are added.
	 *
	 * @param TabPanelLayout[] $tabPanels Tab panels to add
	 */
	public function addTabPanels( array $tabPanels ) {
		$tabItems = [];
		foreach ( $tabPanels as $i => $tabPanel ) {
			$this->tabPanels[ $tabPanel->getName() ] = $tabPanel;
			$labelElement = new Tag( 'span' );
			$tabItem = new TabOptionWidget( [
				'labelElement' => $labelElement,
				'label' => $tabPanel->getLabel(),
				'data' => $tabPanel->getName(),
				// Select the first item
				// TODO: Support selecting an arbitrary item
				'selected' => $this->tabSelectWidget->isEmpty() && $i === 0
			] );
			$tabItems[] = $tabItem;
		}
		$this->tabSelectWidget->addItems( $tabItems );
		$this->stackLayout->addItems( $tabPanels );
	}
}
