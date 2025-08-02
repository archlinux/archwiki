<?php

namespace MediaWiki\CheckUser\Services;

use LogicException;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Title\Title;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityLookup;
use Psr\Log\LoggerInterface;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\DBError;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * A service for methods that interact with the cu_log table, either for insertion or
 * reading log entries.
 */
class CheckUserLogService {

	private IConnectionProvider $dbProvider;
	private CommentStore $commentStore;
	private CommentFormatter $commentFormatter;
	private LoggerInterface $logger;
	private ActorStore $actorStore;
	private UserIdentityLookup $userIdentityLookup;

	public function __construct(
		IConnectionProvider $dbProvider,
		CommentStore $commentStore,
		CommentFormatter $commentFormatter,
		LoggerInterface $logger,
		ActorStore $actorStore,
		UserIdentityLookup $userIdentityLookup
	) {
		$this->dbProvider = $dbProvider;
		$this->commentStore = $commentStore;
		$this->commentFormatter = $commentFormatter;
		$this->logger = $logger;
		$this->actorStore = $actorStore;
		$this->userIdentityLookup = $userIdentityLookup;
	}

	/**
	 * Adds a log entry to the CheckUserLog.
	 *
	 * @param UserIdentity $user
	 * @param string $logType
	 * @param string $targetType
	 * @param string $target
	 * @param string $reason
	 * @param int $targetID
	 * @return void
	 */
	public function addLogEntry(
		UserIdentity $user, string $logType, string $targetType, string $target, string $reason, int $targetID = 0
	) {
		if ( $targetType == 'ip' ) {
			[ $rangeStart, $rangeEnd ] = IPUtils::parseRange( $target );
			$targetHex = $rangeStart;
			if ( $rangeStart == $rangeEnd ) {
				$rangeStart = '';
				$rangeEnd = '';
			}
		} else {
			$targetHex = '';
			$rangeStart = '';
			$rangeEnd = '';
		}

		$timestamp = ConvertibleTimestamp::now();
		$dbw = $this->dbProvider->getPrimaryDatabase();

		$data = [
			'cul_actor' => $this->actorStore->acquireActorId( $user, $dbw ),
			'cul_type' => $logType,
			'cul_target_id' => $targetID,
			'cul_target_text' => trim( $target ),
			'cul_target_hex' => $targetHex,
			'cul_range_start' => $rangeStart,
			'cul_range_end' => $rangeEnd
		];

		$plaintextReason = $this->getPlaintextReason( $reason );

		$fname = __METHOD__;
		$commentStore = $this->commentStore;
		$logger = $this->logger;

		DeferredUpdates::addCallableUpdate(
			static function () use (
				$data, $timestamp, $reason, $plaintextReason, $fname, $dbw, $commentStore, $logger
			) {
				try {
					$data += $commentStore->insert( $dbw, 'cul_reason', $reason );
					$data += $commentStore->insert( $dbw, 'cul_reason_plaintext', $plaintextReason );
					$dbw->newInsertQueryBuilder()
						->insertInto( 'cu_log' )
						->row(
							[
								'cul_timestamp' => $dbw->timestamp( $timestamp )
							] + $data
						)
						->caller( $fname )
						->execute();
				} catch ( DBError $e ) {
					$logger->critical(
						'CheckUserLog entry was not recorded. This means checks can occur without being auditable. ' .
						'Immediate fix required.'
					);
					throw $e;
				}
			}
		);
	}

	/**
	 * Get the plaintext reason
	 *
	 * @param string $reason
	 * @return string
	 */
	public function getPlaintextReason( $reason ) {
		return Sanitizer::stripAllTags(
			$this->commentFormatter->formatBlock(
				$reason, Title::newFromText( 'Special:CheckUserLog' ),
				false, false, false
			)
		);
	}

	/**
	 * Get DB search conditions for the cu_log table according to the target given.
	 *
	 * @param string $target the username, IP address or range of the target.
	 * @return array|null array if valid target, null if invalid target given
	 */
	public function getTargetSearchConds( string $target ): ?array {
		$result = $this->verifyTarget( $target );
		if ( is_array( $result ) ) {
			$dbr = $this->dbProvider->getReplicaDatabase();
			switch ( count( $result ) ) {
				case 1:
					return [
						$dbr->expr( 'cul_target_hex', '=', $result[0] )
							->orExpr(
								$dbr->expr( 'cul_range_end', '>=', $result[0] )
									->and( 'cul_range_start', '<=', $result[0] )
							)
					];
				case 2:
					return [
						$dbr->orExpr( [
							$dbr->expr( 'cul_target_hex', '>=', $result[0] )
								->and( 'cul_target_hex', '<=', $result[1] ),
							$dbr->expr( 'cul_range_end', '>=', $result[0] )
								->and( 'cul_range_start', '<=', $result[1] ),
						] )
					];
				default:
					throw new LogicException(
						"Array returned from ::verifyTarget had the wrong number of items."
					);
			}
		} elseif ( is_int( $result ) ) {
			return [
				'cul_type' => [ 'userips', 'useredits', 'investigate' ],
				'cul_target_id' => $result,
			];
		}
		return null;
	}

	/**
	 * Verify if the target is a valid IP, IP range or user.
	 *
	 * @param string $target
	 * @return false|int|array If the target is a user, then the user's ID is returned.
	 *   If the target is valid IP address, then the IP address
	 *   in hexadecimal is returned as a one item array.
	 *   If the target is a valid IP address range, then the
	 *   start and end of the range in hexadecimal is returned
	 *   as an array.
	 *   Returns false for an invalid target.
	 */
	public function verifyTarget( string $target ) {
		[ $start, $end ] = IPUtils::parseRange( $target );

		if ( $start !== false ) {
			if ( $start === $end ) {
				return [ $start ];
			}

			return [ $start, $end ];
		}

		$user = $this->userIdentityLookup->getUserIdentityByName( $target );
		if ( $user && $user->getId() ) {
			return $user->getId();
		}

		return false;
	}
}
