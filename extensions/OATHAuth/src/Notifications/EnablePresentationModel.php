<?php
/**
 * Copyright (C) 2022 Kunal Mehta <legoktm@debian.org>
 *
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth\Notifications;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class EnablePresentationModel extends EchoEventPresentationModel {

	/** @inheritDoc */
	public function getIconType() {
		return 'site';
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => SpecialPage::getTitleFor( 'OATHManage' )->getLocalURL(),
			'label' => $this->msg( 'oathauth-notifications-enable-primary' )->text()
		];
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		$link = $this->msg( 'oathauth-notifications-enable-helplink' )->inContentLanguage();
		$title = Title::newFromText( $link->plain() );
		if ( !$title ) {
			// Invalid title, skip
			return [];
		}
		return [ [
			'url' => $title->getLocalURL(),
			'label' => $this->msg( 'oathauth-notifications-enable-help' )->text(),
			'icon' => 'help',
		] ];
	}

	/** @inheritDoc */
	public function getBodyMessage() {
		return $this->getMessageWithAgent( 'notification-body-oathauth-enable' );
	}
}
