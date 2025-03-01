<?php

namespace MediaWiki\Extension\Notifications\Formatters;

class EchoManualPresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'robot';
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		return $this->msg( 'notification-header-api-triggered' )
			->plaintextParams( $this->event->getExtraParam( 'header' ), $this->event->getAgent()->getName() );
	}

	/** @inheritDoc */
	public function getSubjectMessage() {
		return $this->getHeaderMessage();
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		if ( !$this->event->getTitle() ) {
			return false;
		}
		return [
			'url' => $this->event->getTitle()->getLocalURL(),
			'label' => $this->msg( 'notification-link-api-triggered' )->text(),
		];
	}

	/** @inheritDoc */
	public function getBodyMessage() {
		$content = $this->event->getExtraParam( 'content' );

		// Content here passed through plaintextParams for sanitization
		return $content ? $this->msg( 'notification-body-api-triggered' )->plaintextParams( $content ) : false;
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		$agentLink = $this->getAgentLink();
		$agentLink['tooltip'] = $this->msg( 'notification-tooltip-api-triggered',
			$this->event->getAgent()->getName() );
		return [
			$agentLink
		];
	}
}
