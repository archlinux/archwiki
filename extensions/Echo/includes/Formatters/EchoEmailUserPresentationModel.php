<?php

namespace MediaWiki\Extension\Notifications\Formatters;

use MediaWiki\Language\RawMessage;

class EchoEmailUserPresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'emailuser';
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return false;
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		return [ $this->getAgentLink() ];
	}

	/** @inheritDoc */
	public function getBodyMessage() {
		$preview = $this->event->getExtraParam( 'preview' );
		return $preview ? ( new RawMessage( '$1' ) )->plaintextParams( $preview ) : false;
	}
}
