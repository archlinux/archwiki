<?php

/**
 * Class representing a list of resources for one gadget
 */
class GadgetResourceLoaderModule extends ResourceLoaderWikiModule {
	private $pages, $dependencies, $messages;

	/**
	 * Creates an instance of this class
	 *
	 * @param $pages Array: Associative array of pages in ResourceLoaderWikiModule-compatible
	 * format, for example:
	 * array(
	 *        'MediaWiki:Gadget-foo.js'  => array( 'type' => 'script' ),
	 *        'MediaWiki:Gadget-foo.css' => array( 'type' => 'style' ),
	 * )
	 * @param $dependencies Array: Names of resources this module depends on
	 * @param $targets Array: List of targets this module support
	 * @param $position String: 'bottom' or 'top'
	 * @param $messages Array
	 */
	public function __construct( $pages, $dependencies, $targets, $position, $messages ) {
		$this->pages = $pages;
		$this->dependencies = $dependencies;
		$this->targets = $targets;
		$this->position = $position;
		$this->isPositionDefined = true;
		$this->messages = $messages;
	}

	/**
	 * Overrides the abstract function from ResourceLoaderWikiModule class
	 * @param $context ResourceLoaderContext
	 * @return Array: $pages passed to __construct()
	 */
	protected function getPages( ResourceLoaderContext $context ) {
		return $this->pages;
	}

	/**
	 * Overrides ResourceLoaderModule::getDependencies()
	 * @param $context ResourceLoaderContext
	 * @return Array: Names of resources this module depends on
	 */
	public function getDependencies( ResourceLoaderContext $context = null ) {
		return $this->dependencies;
	}

	/**
	 * Overrides ResourceLoaderModule::getPosition()
	 * @return String: 'bottom' or 'top'
	 */
	public function getPosition() {
		return $this->position;
	}

	public function getMessages() {
		return $this->messages;
	}
}
