<?php

abstract class GadgetRepo {

	/**
	 * @var GadgetRepo|null
	 */
	private static $instance;

	/**
	 * Get the ids of the gadgets provided by this repository
	 *
	 * @return string[]
	 */
	abstract public function getGadgetIds();

	/**
	 * Get the Gadget object for a given gadget id
	 *
	 * @param string $id
	 * @throws InvalidArgumentException
	 * @return Gadget
	 */
	abstract public function getGadget( $id );

	/**
	 * Get a list of gadgets sorted by category
	 *
	 * @return array array( 'category' => array( 'name' => $gadget ) )
	 */
	public function getStructuredList() {
		$list = array();
		foreach ( $this->getGadgetIds() as $id ) {
			$gadget = $this->getGadget( $id );
			$list[$gadget->getCategory()][$gadget->getName()] = $gadget;
		}

		return $list;
	}

	/**
	 * Get the configured default GadgetRepo. Currently
	 * this hardcodes MediaWikiGadgetsDefinitionRepo since
	 * that is the only implementation
	 *
	 * @return GadgetRepo
	 */
	public static function singleton() {
		if ( self::$instance === null ) {
			self::$instance = new MediaWikiGadgetsDefinitionRepo();
		}
		return self::$instance;
	}

	/**
	 * Should only be used by unit tests
	 *
	 * @param GadgetRepo|null $repo
	 */
	public static function setSingleton( $repo = null ) {
		self::$instance = $repo;
	}
}
