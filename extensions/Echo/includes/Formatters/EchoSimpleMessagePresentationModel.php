<?php

namespace MediaWiki\Extension\Notifications\Formatters;

class EchoSimpleMessagePresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'placeholder';
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return false;
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		return $this->msg( $this->event->getExtraParam( 'message' ) );
	}

}
