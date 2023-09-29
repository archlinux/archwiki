<?php

namespace MediaWiki\Extension\Notifications\Formatters;

class EchoEditThresholdPresentationModel extends EchoEventPresentationModel {

	public function getIconType() {
		return 'edit';
	}

	public function getHeaderMessageKey() {
		return 'notification-header-thank-you-' . $this->event->getExtraParam( 'editCount' ) . '-edit';
	}

	public function getPrimaryLink() {
		if ( !$this->event->getTitle() ) {
			return false;
		}

		if ( $this->event->getExtraParam( 'revid' ) ) {
			$params = [
				'oldid' => 'prev',
				'diff' => $this->event->getExtraParam( 'revid' )
			];
		} else {
			$params = [];
		}
		$url = $this->event->getTitle()->getLocalURL( $params );

		return [
			'url' => $url,
			'label' => $this->msg( 'notification-link-thank-you-edit', $this->getViewingUserForGender() )->text()
		];
	}
}
