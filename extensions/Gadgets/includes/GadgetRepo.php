<?php

abstract class GadgetRepo {

	/**
	 * @var GadgetRepo|null
	 */
	private static $instance;

	/**
	 * Get the ids of the gadgets provided by this repository
	 *
	 * It's possible this could be out of sync with what
	 * getGadget() will return due to caching
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
			try {
				$gadget = $this->getGadget( $id );
			} catch ( InvalidArgumentException $e ) {
				continue;
			}
			$list[$gadget->getCategory()][$gadget->getName()] = $gadget;
		}

		return $list;
	}

	/**
	 * Get the configured default GadgetRepo.
	 *
	 * @return GadgetRepo
	 */
	public static function singleton() {
		if ( self::$instance === null ) {
			global $wgGadgetsRepoClass; // @todo use Config here
			self::$instance = new $wgGadgetsRepoClass();
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
