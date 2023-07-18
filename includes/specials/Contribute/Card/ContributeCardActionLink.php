<?php

namespace MediaWiki\Specials\Contribute\Card;

class ContributeCardActionLink extends ContributeCardAction {

	/**
	 * @inheritDoc
	 */
	public function __construct( string $action, string $actionText ) {
		parent::__construct( $action, $actionText, 'link' );
	}
}
