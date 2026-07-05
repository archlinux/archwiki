<?php

namespace MediaWiki\Extension\Gadgets\Special;

use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Message\Message;
use Wikimedia\Message\MessageSpecifier;

/**
 * Abstract class to represent a particular subpage of Special:Gadgets.
 */
abstract class ActionPage implements MessageLocalizer {

	protected SpecialGadgets $specialPage;

	public function __construct( SpecialGadgets $specialPage ) {
		$this->specialPage = $specialPage;
	}

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	abstract public function execute( array $params );

	/**
	 * Relay for SpecialPage::msg
	 * @param string|string[]|MessageSpecifier $key Message key, or array of keys,
	 *   or a MessageSpecifier.
	 * @param mixed ...$params Normal message parameters
	 * @return Message
	 */
	public function msg( $key, ...$params ) {
		return $this->specialPage->msg( $key, ...$params );
	}
}
