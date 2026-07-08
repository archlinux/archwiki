<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth;

use Exception;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\Context\IContextSource;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;

/**
 * A helper class to facilitate writing to the logs. It primarily sends information to the 'oath' on-wiki log,
 * but it also records CheckUser data (if present) and ordinary (off-wiki) logs.
 */
class OATHAuthLogger {

	public function __construct(
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly IContextSource $context,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Creates a new log entry to resemble an implicit verification of a user's 2FA enrollment,
	 * when checking if user is eligible for being a member of some groups.
	 */
	public function logImplicitVerification( UserIdentity $performer, UserIdentity $target ): void {
		$comment = Message::newFromKey( 'oathauth-verify-automatic-comment' )
			->inContentLanguage()
			->text();

		// messages used: logentry-oath-verify, log-action-oath-verify
		$this->insertLogEntry( 'verify', $performer, $target, $comment );

		$this->logger->info(
			'OATHAuth status implicitly checked for {usertarget} by {user} from {clientip}', [
				'user' => $performer->getName(),
				'usertarget' => $target,
				'clientip' => $this->getClientIP(),
			]
		);
	}

	/**
	 * Creates a new log entry for when a user generates additional recovery keys for another user.
	 */
	public function logOATHRecovery(
		UserIdentity $performer,
		UserIdentity $target,
		string $reason,
		int $codesCount
	): void {
		// messages used: logentry-oath-recover, log-action-oath-recover
		$this->insertLogEntry( 'recover', $performer, $target, $reason, [ '4::count' => $codesCount ] );

		$this->logger->info(
			'{user} generated additional OATHAuth recovery keys for {usertarget} from {clientip}', [
				'user' => $performer->getName(),
				'usertarget' => $target,
				'clientip' => $this->getClientIP(),
			]
		);
	}

	/**
	 * Creates a CheckUser-only log entry for a failed 2FA verification attempt.
	 */
	public function logFailedVerification( UserIdentity $user ): void {
		if ( !$this->extensionRegistry->isLoaded( 'CheckUser' ) ) {
			// @codeCoverageIgnoreStart
			return;
			// @codeCoverageIgnoreEnd
		}

		$logEntry = new ManualLogEntry( 'oath', 'verify-failed' );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget(
			PageReferenceValue::localReference( NS_USER, $user->getName() )
		);

		$this->updateCheckUserData( $logEntry );
	}

	/**
	 * Creates a CheckUser-only log entry for a successful 2FA verification attempt.
	 */
	public function logSuccessfulVerification( UserIdentity $user ): void {
		if ( !$this->extensionRegistry->isLoaded( 'CheckUser' ) ) {
			// @codeCoverageIgnoreStart
			return;
			// @codeCoverageIgnoreEnd
		}

		$logEntry = new ManualLogEntry( 'oath', 'verify-success' );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget(
			PageReferenceValue::localReference( NS_USER, $user->getName() )
		);

		$this->updateCheckUserData( $logEntry );
	}

	private function insertLogEntry(
		string $subtype,
		UserIdentity $performer,
		UserIdentity $target,
		string $comment,
		array $params = []
	): void {
		$targetPage = PageReferenceValue::localReference( NS_USER, $target->getName() );

		$logEntry = new ManualLogEntry( 'oath', $subtype );
		$logEntry->setPerformer( $performer );
		$logEntry->setTarget( $targetPage );
		$logEntry->setComment( $comment );
		$logEntry->setParameters( $params );
		$logId = $logEntry->insert();

		$this->updateCheckUserData( $logEntry, $logId );
	}

	private function updateCheckUserData( ManualLogEntry $logEntry, ?int $logId = null ): void {
		if ( !$this->extensionRegistry->isLoaded( 'CheckUser' ) ) {
			// @codeCoverageIgnoreStart
			return;
			// @codeCoverageIgnoreEnd
		}

		/** @var CheckUserInsert $checkUserInsert */
		$checkUserInsert = MediaWikiServices::getInstance()->get( 'CheckUserInsert' );
		$recentChange = $logId === null
			? $logEntry->getRecentChange()
			: $logEntry->getRecentChange( $logId );
		$checkUserInsert->updateCheckUserData( $recentChange );
	}

	/**
	 * Returns the IP address from which the current request originated or 'unknown IP' if it cannot be determined.
	 */
	private function getClientIP(): string {
		try {
			$request = $this->context->getRequest();
			return $request->getIP();
		} catch ( Exception ) {
			// Let's log with unknown IP, it's not a serious condition, and it's better to have any
			// logs around 2FA than not
			return 'unknown IP';
		}
	}
}
