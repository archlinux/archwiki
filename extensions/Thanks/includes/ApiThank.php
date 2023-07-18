<?php

namespace MediaWiki\Extension\Thanks;

use ApiBase;
use ExtensionRegistry;
use ManualLogEntry;
use MediaWiki\CheckUser\Hooks;
use MediaWiki\MediaWikiServices;
use Title;
use User;

/**
 * Base API module for Thanks
 *
 * @ingroup API
 * @ingroup Extensions
 */
abstract class ApiThank extends ApiBase {
	protected function dieOnBadUser( User $user ) {
		if ( $user->isAnon() ) {
			$this->dieWithError( 'thanks-error-notloggedin', 'notloggedin' );
		} elseif ( $user->pingLimiter( 'thanks-notification' ) ) {
			$this->dieWithError( [ 'thanks-error-ratelimited', $user->getName() ], 'ratelimited' );
		}
	}

	/**
	 * Check whether the user is blocked from this title. (This is not the same
	 * as checking whether they are sitewide blocked, because a sitewide blocked
	 * user may still be allowed to thank on their own talk page.)
	 *
	 * This is separate from dieOnBadUser because we need to know the title.
	 *
	 * @param User $user
	 * @param Title $title
	 */
	protected function dieOnUserBlockedFromTitle( User $user, Title $title ) {
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		if ( $permissionManager->isBlockedFrom( $user, $title ) ) {
			// Block should definitely exist
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
			$this->dieBlocked( $user->getBlock() );
		}
	}

	/**
	 * Check whether the user is sitewide blocked.
	 *
	 * This is separate from dieOnUserBlockedFromTitle because we need to know if the thank
	 * is related to a revision. (If it is, then use dieOnUserBlockedFromTitle instead.)
	 *
	 * @param User $user
	 */
	protected function dieOnUserBlockedFromThanks( User $user ) {
		$block = $user->getBlock();
		if (
			$block &&
			( $block->isSitewide() || $block->appliesToRight( 'thanks' ) )
		) {
			$this->dieBlocked( $block );
		}
	}

	protected function dieOnBadRecipient( User $user, User $recipient ) {
		if ( $user->getId() === $recipient->getId() ) {
			$this->dieWithError( 'thanks-error-invalidrecipient-self', 'invalidrecipient' );
		} elseif ( !$this->getConfig()->get( 'ThanksSendToBots' ) && $recipient->isBot() ) {
			$this->dieWithError( 'thanks-error-invalidrecipient-bot', 'invalidrecipient' );
		}
	}

	protected function markResultSuccess( $recipientName ) {
		$this->getResult()->addValue( null, 'result', [
			'success' => 1,
			'recipient' => $recipientName,
		] );
	}

	/**
	 * This checks the log_search data.
	 *
	 * @param User $thanker The user sending the thanks.
	 * @param string $uniqueId The identifier for the thanks.
	 * @return bool Whether thanks has already been sent
	 */
	protected function haveAlreadyThanked( User $thanker, $uniqueId ) {
		$dbw = wfGetDB( DB_PRIMARY );
		$thankerActor = MediaWikiServices::getInstance()->getActorNormalization()
			->acquireActorId( $thanker, $dbw );
		return (bool)$dbw->selectRow(
			[ 'log_search', 'logging' ],
			[ 'ls_value' ],
			[
				'log_actor' => $thankerActor,
				'ls_field' => 'thankid',
				'ls_value' => $uniqueId,
			],
			__METHOD__,
			[],
			[ 'logging' => [ 'INNER JOIN', 'ls_log_id=log_id' ] ]
		);
	}

	/**
	 * @param User $user The user performing the thanks (and the log entry).
	 * @param User $recipient The target of the thanks (and the log entry).
	 * @param string $uniqueId A unique Id to identify the event being thanked for, to use
	 *                         when checking for duplicate thanks
	 */
	protected function logThanks( User $user, User $recipient, $uniqueId ) {
		if ( !$this->getConfig()->get( 'ThanksLogging' ) ) {
			return;
		}
		$logEntry = new ManualLogEntry( 'thanks', 'thank' );
		$logEntry->setPerformer( $user );
		$logEntry->setRelations( [ 'thankid' => $uniqueId ] );
		$target = $recipient->getUserPage();
		$logEntry->setTarget( $target );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId, 'udp' );

		if ( ExtensionRegistry::getInstance()->isLoaded( 'CheckUser' ) ) {
			$recentChange = $logEntry->getRecentChange();
			Hooks::updateCheckUserData( $recentChange );
		}
	}

	public function needsToken() {
		return 'csrf';
	}

	public function isWriteMode() {
		// Writes to the Echo database and sometimes log tables.
		return true;
	}
}
