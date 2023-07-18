<?php

namespace MediaWiki\Extension\Gadgets;

use InvalidArgumentException;
use MediaWiki\Linker\LinkTarget;
use Title;

abstract class GadgetRepo {

	/**
	 * @var GadgetRepo|null
	 */
	private static $instance;

	/**
	 * @var string
	 */
	protected $titlePrefix;

	/**
	 * Get the ids of the gadgets provided by this repository
	 *
	 * It's possible this could be out of sync with what
	 * getGadget() will return due to caching
	 *
	 * @return string[]
	 */
	abstract public function getGadgetIds(): array;

	/**
	 * Get the Gadget object for a given gadget ID
	 *
	 * @param string $id
	 * @return Gadget
	 * @throws InvalidArgumentException For unregistered ID, used by getStructuredList()
	 */
	abstract public function getGadget( string $id ): Gadget;

	/**
	 * Invalidate any caches based on the provided page (after create, edit, or delete).
	 *
	 * This must be called on create and delete as well (T39228).
	 *
	 * @param LinkTarget $target
	 * @return void
	 */
	public function handlePageUpdate( LinkTarget $target ): void {
	}

	/**
	 * Given a gadget ID, return the title of the page where the gadget is
	 * defined (or null if the given repo does not have per-gadget definition
	 * pages).
	 *
	 * @param string $id
	 * @return Title|null
	 */
	public function getGadgetDefinitionTitle( string $id ): ?Title {
		return null;
	}

	/**
	 * Get a lists of Gadget objects by category
	 *
	 * @return array<string,Gadget[]> `[ 'category' => [ 'name' => $gadget ] ]`
	 */
	public function getStructuredList() {
		$list = [];
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
	 * Get the script file name without the "MediaWiki:Gadget-" or "Gadget:" prefix.
	 * This name is used by the client-side require() so that require("Data.json") resolves
	 * to either "MediaWiki:Gadget-Data.json" or "Gadget:Data.json" depending on the
	 * $wgGadgetsRepoClass configuration, enabling easy migration between the configuration modes.
	 *
	 * @param string $titleText
	 * @return string
	 */
	public function titleWithoutPrefix( string $titleText ): string {
		$numReplaces = 1; // there will only one occurrence of the prefix
		return str_replace( $this->titlePrefix, '', $titleText, $numReplaces );
	}

	/**
	 * Get the configured default GadgetRepo.
	 *
	 * @return GadgetRepo
	 */
	public static function singleton() {
		if ( self::$instance === null ) {
			// @todo use Config here
			global $wgGadgetsRepoClass;
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
