<?php

namespace MediaWiki\Extension\Notifications\Formatters;

use MediaWiki\Extension\Notifications\ForeignNotifications;
use MediaWiki\Language\RawMessage;

class EchoForeignPresentationModel extends EchoEventPresentationModel {
	/** @inheritDoc */
	public function getIconType() {
		return 'global';
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return false;
	}

	/** @inheritDoc */
	protected function getHeaderMessageKey() {
		$data = $this->event->getExtra();
		$section = $data['section'] == 'message' ? 'notice' : $data['section'];

		// notification-header-foreign-alert
		// notification-header-foreign-notice
		// notification-header-foreign-all
		return "notification-header-foreign-{$section}";
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();

		$data = $this->event->getExtra();
		$firstWiki = reset( $data['wikis'] );
		$names = $this->getWikiNames( [ $firstWiki ] );
		$msg->params( $names[0] );
		$msg->numParams( count( $data['wikis'] ) - 1 );
		$msg->numParams( count( $data['wikis'] ) );

		return $msg;
	}

	/** @inheritDoc */
	public function getBodyMessage() {
		$data = $this->event->getExtra();
		$msg = new RawMessage( '$1' );
		$msg->params( $this->language->listToText( $this->getWikiNames( $data['wikis'] ) ) );
		return $msg;
	}

	/**
	 * @param string[] $wikis
	 * @return string[]
	 */
	protected function getWikiNames( array $wikis ): array {
		$data = ForeignNotifications::getApiEndpoints( $wikis );
		$names = [];
		foreach ( $wikis as $wiki ) {
			$names[] = $data[$wiki]['title'];
		}
		return $names;
	}
}
