<?php

namespace MediaWiki\Extension\Gadgets;

use InvalidArgumentException;
use MediaWiki\ResourceLoader as RL;

/**
 * Class representing a list of resources for one gadget, basically a wrapper
 * around the Gadget class.
 */
class GadgetResourceLoaderModule extends RL\WikiModule {
	/**
	 * @var string
	 */
	private $id;

	/**
	 * @var Gadget
	 */
	private $gadget;

	/**
	 * @param array $options
	 */
	public function __construct( array $options ) {
		$this->id = $options['id'];
	}

	/**
	 * @return Gadget instance this module is about
	 */
	private function getGadget() {
		if ( !$this->gadget ) {
			try {
				$this->gadget = GadgetRepo::singleton()->getGadget( $this->id );
			} catch ( InvalidArgumentException $e ) {
				// Fallback to a placeholder object...
				$this->gadget = Gadget::newEmptyGadget( $this->id );
			}
		}

		return $this->gadget;
	}

	/**
	 * Overrides the function from RL\WikiModule class
	 * @param RL\Context $context
	 * @return array
	 */
	protected function getPages( RL\Context $context ) {
		$gadget = $this->getGadget();
		$pages = [];

		foreach ( $gadget->getStyles() as $style ) {
			$pages[$style] = [ 'type' => 'style' ];
		}

		if ( $gadget->supportsResourceLoader() ) {
			foreach ( $gadget->getScripts() as $script ) {
				$pages[$script] = [ 'type' => 'script' ];
			}
			foreach ( $gadget->getJSONs() as $json ) {
				$pages[$json] = [ 'type' => 'data' ];
			}
		}

		return $pages;
	}

	/**
	 * Overrides RL\WikiModule::getRequireKey()
	 * @param string $titleText
	 * @return string
	 */
	public function getRequireKey( $titleText ): string {
		return GadgetRepo::singleton()->titleWithoutPrefix( $titleText );
	}

	/**
	 * Overrides RL\WikiModule::isPackaged()
	 * Returns whether this gadget is packaged.
	 * @return bool
	 */
	public function isPackaged(): bool {
		return $this->gadget->isPackaged();
	}

	/**
	 * Overrides RL\Module::getDependencies()
	 * @param RL\Context|null $context
	 * @return string[] Names of resources this module depends on
	 */
	public function getDependencies( RL\Context $context = null ) {
		return $this->getGadget()->getDependencies();
	}

	/**
	 * Overrides RL\WikiModule::getType()
	 * @return string RL\Module::LOAD_STYLES or RL\Module::LOAD_GENERAL
	 */
	public function getType() {
		return $this->getGadget()->getType() === 'styles'
			? RL\Module::LOAD_STYLES
			: RL\Module::LOAD_GENERAL;
	}

	public function getMessages() {
		return $this->getGadget()->getMessages();
	}

	public function getTargets() {
		return $this->getGadget()->getTargets();
	}

	public function getSkins(): ?array {
		return $this->getGadget()->getRequiredSkins() ?: null;
	}

	public function getGroup() {
		return 'site';
	}
}
