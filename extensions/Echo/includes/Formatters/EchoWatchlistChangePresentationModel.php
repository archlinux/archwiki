<?php

namespace MediaWiki\Extension\Notifications\Formatters;

use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class EchoWatchlistChangePresentationModel extends EchoEventPresentationModel {

	public function getIconType() {
		return 'watchlist-progressive';
	}

	public function getHeaderMessage() {
		if ( $this->isMultiTypeBundle() ) {
			$status = "changed";
		} else {
			$status = $this->event->getExtraParam( 'status' );
		}
		if ( $this->isMultiUserBundle() ) {
			// Messages: notification-header-watchlist-multiuser-changed,
			// notification-header-watchlist-multiuser-created
			// notification-header-watchlist-multiuser-deleted
			// notification-header-watchlist-multiuser-moved
			// notification-header-watchlist-multiuser-restored
			$msg = $this->msg( "notification-header-watchlist-multiuser-" . $status );
		} else {
			// Messages: notification-header-watchlist-changed,
			// notification-header-watchlist-created
			// notification-header-watchlist-deleted
			// notification-header-watchlist-moved
			// notification-header-watchlist-restored
			$msg = $this->getMessageWithAgent( "notification-header-watchlist-" . $status );
		}
		$msg->params( $this->getTruncatedTitleText( $this->getEventTitle(), true ) );
		$msg->params( $this->getViewingUserForGender() );
		$msg->numParams( $this->getBundleCount() );
		return $msg;
	}

	public function getPrimaryLink() {
		if ( $this->isBundled() ) {
			return [
				'url' => $this->getEventTitle()->getLocalUrl(),
				'label' => $this->msg( 'notification-link-text-view-page' )->text()
			];
		}
		return [
			'url' => $this->getViewChangesUrl(),
			'label' => $this->msg( 'notification-link-text-view-changes', $this->getViewingUserForGender() )
				->text(),
		];
	}

	public function getSecondaryLinks() {
		if ( $this->isBundled() ) {
			if ( $this->isMultiUserBundle() ) {
				return [];
			} else {
				return [ $this->getAgentLink() ];
			}
		} else {
			$viewChangesLink = [
				'url' => $this->getViewChangesUrl(),
				'label' => $this->msg( 'notification-link-text-view-changes', $this->getViewingUserForGender() )
					->text(),
				'description' => '',
				'icon' => 'changes',
				'prioritized' => true,
			];
			return [ $this->getAgentLink(), $viewChangesLink ];
		}
	}

	public function getBodyMessage() {
		if ( $this->event->getExtraParam( 'emailonce' ) && $this->getDistributionType() == 'email' ) {
			return $this->msg( 'notification-body-watchlist-once', $this->getViewingUserForGender() );
		}
		return false;
	}

	private function isMultiUserBundle() {
		foreach ( $this->getBundledEvents() as $bundled ) {
			if ( !$bundled->getAgent()->equals( $this->event->getAgent() ) ) {
				return true;
			}
		}
		return false;
	}

	private function isMultiTypeBundle() {
		foreach ( $this->getBundledEvents() as $bundled ) {
			if ( $bundled->getExtraParam( 'status' ) !== $this->event->getExtraParam( 'status' ) ) {
				return true;
			}
		}
		return false;
	}

	private function getViewChangesUrl() {
		$revid = $this->event->getExtraParam( 'revid' );
		if ( $revid === 0 ) {
			$url = SpecialPage::getTitleFor( 'Log' )->getLocalUrl( [
				'logid' => $this->event->getExtraParam( 'logid' )
			] );
		} else {
			$url = $this->getEventTitle()->getLocalURL( [
				'oldid' => 'prev',
				'diff' => $revid
			] );
		}
		return $url;
	}

	/**
	 * Returns Event's Title
	 * Fixes bug T286192, for the events created before the patch [1] applied here
	 * [1] - https://gerrit.wikimedia.org/r/c/mediawiki/extensions/Echo/+/736484
	 * @return Title
	 */
	private function getEventTitle(): Title {
		$title = $this->event->getTitle();
		if ( !$title ) {
			$pageId = $this->event->getPageId();
			if ( $pageId ) {
				$dbr = MediaWikiServices::getInstance()
					->getDBLoadBalancer()
					->getMaintenanceConnectionRef( DB_REPLICA );
				$row = $dbr->selectRow(
					'archive',
					[ 'ar_title', 'ar_namespace' ],
					[ 'ar_page_id' => $pageId ],
					__METHOD__,
					[ 'MAX' => 'ar_id', 'ar_id DESC' ]
				);
				if ( $row ) {
					$title = Title::makeTitleSafe( $row->ar_namespace, $row->ar_title );
				}
			}
			if ( !$title ) {
				$title = Title::makeTitleSafe( NS_MAIN, 'UNKNOWN TITLE, SEE THE T286192 BUG FOR DETAILS' );
			}
			if ( !$title ) {
				// The latest chance to return a Title object (paranoid mode on)
				$title = Title::newMainPage();
			}
		}
		return $title;
	}
}
